<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->loadParameters();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Load all parameters from the database into Config.
     * Accessible via config('constants.KEY_NAME').
     */
    protected function loadParameters(): void
    {
        try {
            if (! Schema::hasTable('parameters')) {
                return;
            }

            $parameters = DB::table('parameters')->get();

            foreach ($parameters as $param) {
                Config::set('constants.' . $param->key, $param->value);
            }
        } catch (\Exception $e) {
            // Silently fail during migrations or when DB is unavailable
        }
    }
}
