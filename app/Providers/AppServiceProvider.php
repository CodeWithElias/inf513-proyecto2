<?php

namespace App\Providers;

use App\Models\TokenAcceso;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(TokenAcceso::class);

        // En tecnoweb la app vive en un subdirectorio (/inf513/grupo13sa/proyecto2):
        // todas las URL generadas (assets de Vite, api-base) salen de APP_URL.
        $url = config('app.url');
        if ($url) {
            URL::forceRootUrl($url);
            if (str_starts_with($url, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
