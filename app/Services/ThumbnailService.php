<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ThumbnailService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function generateImageThumbnail(string $disk, string $path, int $width = 300): ?string
    {
        try {
            $contents = Storage::disk($disk)->get($path);
            $image = $this->manager->read($contents);

            $image->scaleDown(width: $width);

            $thumbnailContents = $image->toJpeg(80)->toString();

            $pathInfo = pathinfo($path);
            $thumbnailPath = $pathInfo['dirname'] . '/thumb_' . $pathInfo['filename'] . '.jpg';

            Storage::disk($disk)->put($thumbnailPath, $thumbnailContents);

            return Storage::disk($disk)->url($thumbnailPath);
        } catch (\Throwable $e) {
            Log::error('Image thumbnail generation failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);
            return null;
        }
    }

    public function generateVideoThumbnail(string $disk, string $path): ?string
    {
        try {
            $ffmpeg = $this->findFfmpeg();
            if (!$ffmpeg) {
                Log::warning('FFmpeg not found, skipping video thumbnail');
                return null;
            }

            $tempInput = tempnam(sys_get_temp_dir(), 'vid_');
            $tempOutput = tempnam(sys_get_temp_dir(), 'thumb_') . '.jpg';

            $contents = Storage::disk($disk)->get($path);
            file_put_contents($tempInput, $contents);

            $cmd = escapeshellcmd($ffmpeg)
                . ' -i ' . escapeshellarg($tempInput)
                . ' -ss 00:00:01 -vframes 1 -vf scale=300:-1'
                . ' -y ' . escapeshellarg($tempOutput)
                . ' 2>&1';

            exec($cmd, $output, $returnCode);

            @unlink($tempInput);

            if ($returnCode !== 0 || !file_exists($tempOutput)) {
                Log::warning('FFmpeg thumbnail extraction failed', [
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                ]);
                return null;
            }

            $pathInfo = pathinfo($path);
            $thumbnailPath = $pathInfo['dirname'] . '/thumb_' . $pathInfo['filename'] . '.jpg';

            Storage::disk($disk)->put($thumbnailPath, file_get_contents($tempOutput));
            @unlink($tempOutput);

            return Storage::disk($disk)->url($thumbnailPath);
        } catch (\Throwable $e) {
            Log::error('Video thumbnail generation failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);
            return null;
        }
    }

    public function extractAudioDuration(string $disk, string $path): ?int
    {
        try {
            $ffprobe = $this->findFfprobe();
            if (!$ffprobe) {
                return null;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'audio_');
            $contents = Storage::disk($disk)->get($path);
            file_put_contents($tempFile, $contents);

            $cmd = escapeshellcmd($ffprobe)
                . ' -v error -show_entries format=duration'
                . ' -of default=noprint_wrappers=1:nokey=1'
                . ' ' . escapeshellarg($tempFile)
                . ' 2>&1';

            $duration = trim(shell_exec($cmd) ?? '');
            @unlink($tempFile);

            return $duration && is_numeric($duration) ? (int) round((float) $duration) : null;
        } catch (\Throwable $e) {
            Log::error('Audio duration extraction failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);
            return null;
        }
    }

    public function extractVideoAudio(string $disk, string $path): ?string
    {
        try {
            $ffmpeg = $this->findFfmpeg();
            if (!$ffmpeg) {
                return null;
            }

            $tempInput = tempnam(sys_get_temp_dir(), 'vid_');
            $tempOutput = tempnam(sys_get_temp_dir(), 'audio_') . '.mp3';

            $contents = Storage::disk($disk)->get($path);
            file_put_contents($tempInput, $contents);

            $cmd = escapeshellcmd($ffmpeg)
                . ' -i ' . escapeshellarg($tempInput)
                . ' -vn -acodec libmp3lame -ab 128k'
                . ' -y ' . escapeshellarg($tempOutput)
                . ' 2>&1';

            exec($cmd, $output, $returnCode);

            @unlink($tempInput);

            if ($returnCode !== 0 || !file_exists($tempOutput)) {
                return null;
            }

            return $tempOutput;
        } catch (\Throwable $e) {
            Log::error('Video audio extraction failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);
            return null;
        }
    }

    private function findFfmpeg(): ?string
    {
        foreach (['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg'] as $path) {
            if (@is_executable($path) || ($path === 'ffmpeg' && shell_exec('which ffmpeg 2>/dev/null'))) {
                return $path;
            }
        }
        return null;
    }

    private function findFfprobe(): ?string
    {
        foreach (['/usr/bin/ffprobe', '/usr/local/bin/ffprobe', 'ffprobe'] as $path) {
            if (@is_executable($path) || ($path === 'ffprobe' && shell_exec('which ffprobe 2>/dev/null'))) {
                return $path;
            }
        }
        return null;
    }
}
