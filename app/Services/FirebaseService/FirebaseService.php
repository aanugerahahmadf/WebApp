<?php

namespace App\Services\FirebaseService;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Database;
use Kreait\Firebase\Exception\DatabaseException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Messaging;
use Kreait\Firebase\Storage;

class FirebaseService
{
    protected Factory $factory;

    protected ?Database $database = null;

    protected ?Auth $auth = null;

    protected ?Storage $storage = null;

    protected ?Messaging $messaging = null;

    protected string $databaseUrl;

    protected bool $initialized = false;

    public function __construct()
    {
        try {
            $credentialsPath = config('firebase.credentials');

            if (! file_exists($credentialsPath)) {
                throw new Exception("Firebase credentials file not found at: {$credentialsPath}");
            }

            $this->factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->databaseUrl = config('firebase.database_url');
            $this->initialized = true;
        } catch (Exception $e) {
            Log::error('Firebase initialization failed', ['error' => $e->getMessage()]);
            $this->initialized = false;
        }
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get Firebase Realtime Database instance
     */
    public function getDatabase(): Database
    {
        if (! $this->database) {
            $this->database = $this->factory->createDatabase();
        }

        return $this->database;
    }

    /**
     * Get Firebase Authentication instance
     */
    public function getAuth(): Auth
    {
        if (! $this->auth) {
            $this->auth = $this->factory->createAuth();
        }

        return $this->auth;
    }

    /**
     * Get Firebase Storage instance
     */
    public function getStorage(): Storage
    {
        if (! $this->storage) {
            $this->storage = $this->factory->createStorage();
        }

        return $this->storage;
    }

    /**
     * Get Firebase Cloud Messaging instance
     */
    public function getMessaging(): ?Messaging
    {
        if (! $this->initialized) {
            return null;
        }
        if (! $this->messaging) {
            $this->messaging = $this->factory->createMessaging();
        }

        return $this->messaging;
    }

    /**
     * Send a push notification via FCM
     */
    public function sendPushNotification(string $token, string $title, string $body, array $data = [], array $androidConfig = []): bool
    {
        $messaging = $this->getMessaging();
        if (! $messaging) {
            Log::warning('FCM not available (Firebase not initialized)');

            return false;
        }
        try {
            $notification = [
                'title' => $title,
                'body' => $body,
            ];

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            $defaultAndroid = [
                'priority' => 'high',
                'ttl' => '600s',
                'notification' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'channel_id' => $androidConfig['channel_id'] ?? 'wedding_notifications',
                ],
            ];
            $finalAndroid = array_replace_recursive($defaultAndroid, $androidConfig);
            $message = $message->withAndroidConfig($finalAndroid);

            $messaging->send($message);
            Log::info('FCM sent successfully', ['token_prefix' => substr($token, 0, 20).'...', 'title' => $title]);

            return true;
        } catch (\Throwable $e) {
            Log::error('FCM send failed', ['error' => $e->getMessage(), 'token' => substr($token, 0, 20).'...']);

            return false;
        }
    }

    /**
     * Write data to Realtime Database
     */
    public function write(string $path, array $data): bool
    {
        try {
            $this->getDatabase()
                ->getReference($path)
                ->set($data);

            Log::info("Firebase write successful: {$path}");

            return true;
        } catch (DatabaseException $e) {
            Log::error("Firebase write failed for path: {$path}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update data in Realtime Database
     */
    public function update(string $path, array $data): bool
    {
        try {
            $this->getDatabase()
                ->getReference($path)
                ->update($data);

            Log::info("Firebase update successful: {$path}");

            return true;
        } catch (DatabaseException $e) {
            Log::error("Firebase update failed for path: {$path}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Read data from Realtime Database
     */
    public function read(string $path): ?array
    {
        try {
            $cacheKey = "firebase:{$path}";
            $ttl = config('firebase.cache.ttl', 3600);

            return Cache::remember($cacheKey, $ttl, function () use ($path) {
                $snapshot = $this->getDatabase()->getReference($path)->getSnapshot();

                return $snapshot->getValue();
            });
        } catch (DatabaseException $e) {
            Log::error("Firebase read failed for path: {$path}", ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Read data without caching
     */
    public function readDirect(string $path): ?array
    {
        try {
            $snapshot = $this->getDatabase()->getReference($path)->getSnapshot();

            return $snapshot->getValue();
        } catch (DatabaseException $e) {
            Log::error("Firebase direct read failed for path: {$path}", ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Delete data from Realtime Database
     */
    public function delete(string $path): bool
    {
        try {
            $this->getDatabase()
                ->getReference($path)
                ->remove();

            Cache::forget("firebase:{$path}");
            Log::info("Firebase delete successful: {$path}");

            return true;
        } catch (DatabaseException $e) {
            Log::error("Firebase delete failed for path: {$path}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create or update child node
     */
    public function push(string $path, array $data): ?string
    {
        try {
            $newRef = $this->getDatabase()
                ->getReference($path)
                ->push($data);

            $key = $newRef->getKey();
            Log::info("Firebase push successful: {$path}, key: {$key}");

            return $key;
        } catch (DatabaseException $e) {
            Log::error("Firebase push failed for path: {$path}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Check if path exists
     */
    public function exists(string $path): bool
    {
        try {
            $snapshot = $this->getDatabase()->getReference($path)->getSnapshot();

            return $snapshot->exists();
        } catch (DatabaseException $e) {
            Log::error("Firebase exists check failed for path: {$path}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Get all children at path
     */
    public function getChildren(string $path): array
    {
        try {
            $snapshot = $this->getDatabase()->getReference($path)->getSnapshot();
            $children = [];

            foreach ($snapshot->getChildren() as $child) {
                $children[$child->getKey()] = $child->getValue();
            }

            return $children;
        } catch (DatabaseException $e) {
            Log::error("Firebase getChildren failed for path: {$path}", ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Query data with ordering
     */
    public function orderByChild(string $path, string $childKey): array
    {
        try {
            $query = $this->getDatabase()
                ->getReference($path)
                ->orderByChild($childKey);

            $snapshot = $query->getSnapshot();
            $results = [];

            foreach ($snapshot->getChildren() as $child) {
                $results[$child->getKey()] = $child->getValue();
            }

            return $results;
        } catch (DatabaseException $e) {
            Log::error('Firebase orderByChild failed', ['path' => $path, 'childKey' => $childKey, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Clear cache for a path
     */
    public function clearCache(string $path): void
    {
        Cache::forget("firebase:{$path}");
        Log::info("Firebase cache cleared for: {$path}");
    }

    /**
     * Get connection status
     */
    public function isConnected(): bool
    {
        try {
            $this->readDirect('.info/connected');

            return true;
        } catch (Exception $e) {
            Log::warning('Firebase connection check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
