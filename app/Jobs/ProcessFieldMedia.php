<?php

namespace App\Jobs;

use App\Models\FieldReport;
use App\Models\FieldReportMedia;
use App\Services\GoogleVisionService;
use App\Services\ThumbnailService;
use App\Services\WhisperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessFieldMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    public int $timeout = 300;

    public function __construct(
        protected int $mediaId,
    ) {
        $this->onQueue('field-reports');
    }

    public function handle(
        ThumbnailService $thumbnailService,
        WhisperService $whisperService,
        GoogleVisionService $visionService,
    ): void {
        $media = FieldReportMedia::find($this->mediaId);
        if (!$media) {
            Log::warning('ProcessFieldMedia: media not found', ['id' => $this->mediaId]);
            return;
        }

        $media->update(['processing_status' => 'processing']);

        $report = $media->fieldReport;
        if ($report) {
            $report->update(['status' => 'processing']);
        }

        $result = [];

        try {
            $mimeType = $media->mime_type ?? '';

            if (str_starts_with($mimeType, 'image/')) {
                $result = $this->processImage($media, $thumbnailService, $visionService);
            } elseif (str_starts_with($mimeType, 'audio/')) {
                $result = $this->processAudio($media, $thumbnailService, $whisperService);
            } elseif (str_starts_with($mimeType, 'video/')) {
                $result = $this->processVideo($media, $thumbnailService, $whisperService);
            }

            $media->update([
                'processing_status' => 'completed',
                'processing_result' => $result,
            ]);

            $this->updateReportStatus($media);
            $this->updateReportBody($media, $result);

        } catch (\Throwable $e) {
            Log::error('ProcessFieldMedia failed', [
                'media_id' => $this->mediaId,
                'error' => $e->getMessage(),
            ]);

            $media->update([
                'processing_status' => 'failed',
                'processing_result' => array_merge($result, [
                    'error' => $e->getMessage(),
                ]),
            ]);

            if ($report) {
                $report->update(['status' => 'submitted']);
            }

            throw $e;
        }
    }

    private function processImage(
        FieldReportMedia $media,
        ThumbnailService $thumbnailService,
        GoogleVisionService $visionService,
    ): array {
        $result = [];

        // Generate thumbnail
        $thumbnailUrl = $thumbnailService->generateImageThumbnail($media->disk, $media->path);
        if ($thumbnailUrl) {
            $media->update(['thumbnail_url' => $thumbnailUrl]);
        }

        // OCR + labels via Google Vision
        $visionResult = $visionService->analyze($media->disk, $media->path);
        if ($visionResult['success']) {
            $result['ocr_text'] = $visionResult['ocr_text'] ?? '';
            $result['labels'] = array_map(fn ($l) => $l['name'], $visionResult['labels'] ?? []);
            $result['label_details'] = $visionResult['labels'] ?? [];
        } else {
            $result['vision_error'] = $visionResult['error'] ?? 'Unknown error';
        }

        return $result;
    }

    private function processAudio(
        FieldReportMedia $media,
        ThumbnailService $thumbnailService,
        WhisperService $whisperService,
    ): array {
        $result = [];

        // Extract duration
        $duration = $thumbnailService->extractAudioDuration($media->disk, $media->path);
        if ($duration) {
            $media->update(['duration' => $duration]);
        }

        // Transcribe via Whisper
        $whisperResult = $whisperService->transcribe($media->disk, $media->path);
        if ($whisperResult['success']) {
            $result['transcription'] = $whisperResult['transcription'] ?? '';
            $result['language'] = $whisperResult['language'] ?? 'en';
            if (isset($whisperResult['duration'])) {
                $media->update(['duration' => (int) round($whisperResult['duration'])]);
            }
        } else {
            $result['whisper_error'] = $whisperResult['error'] ?? 'Unknown error';
        }

        return $result;
    }

    private function processVideo(
        FieldReportMedia $media,
        ThumbnailService $thumbnailService,
        WhisperService $whisperService,
    ): array {
        $result = [];

        // Generate video thumbnail
        $thumbnailUrl = $thumbnailService->generateVideoThumbnail($media->disk, $media->path);
        if ($thumbnailUrl) {
            $media->update(['thumbnail_url' => $thumbnailUrl]);
        }

        // Extract duration
        $duration = $thumbnailService->extractAudioDuration($media->disk, $media->path);
        if ($duration) {
            $media->update(['duration' => $duration]);
        }

        // Extract audio and transcribe
        $audioPath = $thumbnailService->extractVideoAudio($media->disk, $media->path);
        if ($audioPath && file_exists($audioPath)) {
            // Store extracted audio temporarily for Whisper
            $tempAudioStoragePath = "temp/audio_{$media->id}.mp3";
            Storage::disk($media->disk)->put($tempAudioStoragePath, file_get_contents($audioPath));
            @unlink($audioPath);

            $whisperResult = $whisperService->transcribe($media->disk, $tempAudioStoragePath);

            // Clean up temp audio
            Storage::disk($media->disk)->delete($tempAudioStoragePath);

            if ($whisperResult['success']) {
                $result['transcription'] = $whisperResult['transcription'] ?? '';
                $result['language'] = $whisperResult['language'] ?? 'en';
            } else {
                $result['whisper_error'] = $whisperResult['error'] ?? 'Unknown error';
            }
        }

        return $result;
    }

    private function updateReportStatus(FieldReportMedia $media): void
    {
        $report = $media->fieldReport;
        if (!$report) {
            return;
        }

        // Check if all media for this report are processed
        $pendingCount = $report->media()
            ->whereIn('processing_status', ['pending', 'processing'])
            ->count();

        if ($pendingCount === 0) {
            $failedCount = $report->media()
                ->where('processing_status', 'failed')
                ->count();

            $report->update([
                'status' => $failedCount > 0 ? 'flagged' : 'processed',
            ]);
        }
    }

    private function updateReportBody(FieldReportMedia $media, array $result): void
    {
        $report = $media->fieldReport;
        if (!$report || !empty($report->body)) {
            return;
        }

        // Auto-populate report body from transcription or OCR
        $text = $result['transcription'] ?? $result['ocr_text'] ?? '';
        if (!empty($text)) {
            $report->update(['body' => $text]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessFieldMedia job permanently failed', [
            'media_id' => $this->mediaId,
            'error' => $exception->getMessage(),
        ]);

        $media = FieldReportMedia::find($this->mediaId);
        if ($media) {
            $media->update([
                'processing_status' => 'failed',
                'processing_result' => [
                    'error' => $exception->getMessage(),
                    'permanently_failed' => true,
                ],
            ]);
        }
    }
}
