<?php

use App\Models\Site;
use Illuminate\Support\Facades\Route;

Route::get('/{slug}', function (string $slug) {
    $site = Site::where('slug', $slug)->where('is_active', true)->first();
    if ($site) {
        return view('app', ['meta' => [
            'title' => $site->candidate_name . ' — ' . ($site->position ?? 'Campaign'),
            'description' => $site->slogan ?? ($site->candidate_name . ' campaign site'),
            'image' => $site->portrait_url ?? $site->hero_image_url,
            'color' => $site->primary_color ?? '#16a34a',
            'url' => url("/{$slug}"),
        ]]);
    }
    return view('app', ['meta' => null]);
})->where('slug', '^(?!admin|api)[\w-]+$');

Route::get('/{any}', function () {
    return view('app', ['meta' => null]);
})->where('any', '.*');
