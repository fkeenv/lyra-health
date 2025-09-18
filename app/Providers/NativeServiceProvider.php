<?php

namespace App\Providers;

use Native\Laravel\Facades\Window;
use Native\Laravel\Contracts\ProvidesPhpIni;
use Illuminate\Support\ServiceProvider;

class NativeServiceProvider extends ServiceProvider implements ProvidesPhpIni
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
        // Only configure window in native environment
        if (app()->bound('native.settings')) {
            Window::open()
                ->id('main')
                ->url('/')
                ->width(1200)
                ->height(800)
                ->titleBarHidden()
                ->resizable();
        }
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'display_errors' => '1',
            'display_startup_errors' => '1',
            'max_execution_time' => '0',
            'max_input_time' => '0',
        ];
    }
}