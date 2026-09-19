<?php

namespace App\Providers;

use App\Auth\ScryptHasher;
use Illuminate\Support\ServiceProvider;

class ScryptHashServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->app->make('hash')->extend('scrypt', function () {
            return new ScryptHasher();
        });
    }
}
