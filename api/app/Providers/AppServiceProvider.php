<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->letters()->numbers()->uncompromised()
            : Password::min(8));
    }
}
