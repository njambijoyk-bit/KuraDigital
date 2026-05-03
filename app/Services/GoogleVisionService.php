<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleVisionService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://vision.googleapis.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.google_vision.api_key', '');
    }

    public function analyze(string $disk, string $path): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'Google Cloud Vision API key not configured',
            ];
        }

        try {
            $contents = Storage::disk($disk)->get($path);
            $base64Image = base64_encode($contents);

            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/images:annotate?key={$this->apiKey}", [
                    'requests' => [
                        [
                            'image' => [
                                'content' => $base64Image,
                            ],
                            'features' => [
                                ['type' => 'TEXT_DETECTION', 'maxResults' => 10],
                                ['type' => 'LABEL_DETECTION', 'maxResults' => 10],
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $annotations = $data['responses'][0] ?? [];

                $ocrText = '';
                if (!empty($annotations['textAnnotations'])) {
                    $ocrText = $annotations['textAnnotations'][0]['description'] ?? '';
                }

                $labels = [];
                if (!empty($annotations['labelAnnotations'])) {
                    foreach ($annotations['labelAnnotations'] as $label) {
                        $labels[] = [
                            'name' => $label['description'],
                            'confidence' => round($label['score'] ?? 0, 3),
                        ];
                    }
                }

                $error = $annotations['error'] ?? null;
                if ($error) {
                    return [
                        'success' => false,
                        'error' => $error['message'] ?? 'Vision API error',
                    ];
                }

                return [
                    'success' => true,
                    'ocr_text' => $ocrText,
                    'labels' => $labels,
                ];
            }

            Log::warning('Google Vision API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Vision API returned ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('Google Vision analysis failed', [
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
