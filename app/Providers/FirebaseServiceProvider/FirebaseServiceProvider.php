<?php

namespace App\Providers\FirebaseServiceProvider;

use App\Services\FirebaseService\FirebaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FirebaseService::class, function ($app) {
            try {
                return new FirebaseService;
            } catch (\Exception $e) {
                Log::error('Failed to initialize FirebaseService', ['error' => $e->getMessage()]);

                // Return a mock service if credentials are not available
                return new class
                {
                    public function read($path)
                    {
                        Log::warning('Firebase service not initialized. Returning null for path: '.$path);

                        return null;
                    }

                    public function write($path, $data)
                    {
                        Log::warning('Firebase service not initialized. Write failed for path: '.$path);

                        return false;
                    }

                    public function getDatabase()
                    {
                        throw new \Exception('Firebase service not initialized');
                    }

                    public function getAuth()
                    {
                        throw new \Exception('Firebase service not initialized');
                    }

                    public function getStorage()
                    {
                        throw new \Exception('Firebase service not initialized');
                    }

                    public function isConnected()
                    {
                        return false;
                    }
                };
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // You can add additional Firebase initialization here if needed
    }
}
