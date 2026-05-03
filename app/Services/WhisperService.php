<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhisperService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
    }

    public function transcribe(string $disk, string $path, string $language = 'en'): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'OpenAI API key not configured',
            ];
        }

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'whisper_');
            $contents = Storage::disk($disk)->get($path);
            file_put_contents($tempFile, $contents);

            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $tempFileWithExt = $tempFile . '.' . $extension;
            rename($tempFile, $tempFileWithExt);

            $response = Http::timeout(120)
                ->withToken($this->apiKey)
                ->attach('file', file_get_contents($tempFileWithExt), basename($path))
                ->post("{$this->baseUrl}/audio/transcriptions", [
                    'model' => 'whisper-1',
                    'language' => $language,
                    'response_format' => 'verbose_json',
                ]);

            @unlink($tempFileWithExt);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'transcription' => $data['text'] ?? '',
                    'language' => $data['language'] ?? $language,
                    'duration' => $data['duration'] ?? null,
                    'segments' => $data['segments'] ?? [],
                ];
            }

            Log::warning('Whisper API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Whisper API returned ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('Whisper transcription failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
