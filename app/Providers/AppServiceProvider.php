<?php

namespace App\Providers;

use App\Support\CsdStatus;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('components.app.header', function ($view) {
            $view->with('csdHealth', CsdStatus::forUser(auth()->id()));
        });
    }
}
