<?php

namespace App\Grpc\User\V1;

use App\Models\User\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\ServiceInterface;

abstract class BaseGrpcService implements ServiceInterface
{
    /**
     * Get authenticated user from gRPC context
     */
    protected function getUser(ContextInterface $context): ?User
    {
        // Try to get user from metadata (Authorization header)
        $metadata = $context->getMetadata();
        
        // Check for authorization header
        $authHeader = $metadata->get('authorization');
        if ($authHeader) {
            $token = str_replace('Bearer ', '', $authHeader[0] ?? '');
            if ($token) {
                $accessToken = PersonalAccessToken::findToken($token);
                if ($accessToken?->tokenable instanceof User) {
                    return $accessToken->tokenable;
                }
            }
        }
        
        // Fallback to Laravel's auth guard
        return Auth::guard('sanctum')->user();
    }
    
    /**
     * Require authentication, throw exception if not authenticated
     */
    protected function requireAuth(ContextInterface $context): User
    {
        $user = $this->getUser($context);
        if (!$user) {
            throw new \Exception('Unauthorized: Authentication required', 401);
        }
        return $user;
    }
    
    /**
     * Require admin role
     */
    protected function requireAdmin(ContextInterface $context): User
    {
        $user = $this->requireAuth($context);
        if (!$user->hasRole('admin') && !$user->hasRole('super_admin')) {
            throw new \Exception('Forbidden: Admin access required', 403);
        }
        return $user;
    }
    
    /**
     * Convert Laravel model to protobuf message
     */
    protected function modelToArray($model, array $relations = []): array
    {
        if (!$model) {
            return [];
        }
        
        $array = $model->toArray();
        
        foreach ($relations as $relation) {
            if ($model->relationLoaded($relation)) {
                $related = $model->getRelation($relation);
                if ($related) {
                    if (method_exists($related, 'toArray')) {
                        $array[$relation] = $related->toArray();
                    } elseif ($related instanceof \Illuminate\Support\Collection) {
                        $array[$relation] = $related->map(fn($item) => $item->toArray())->all();
                    }
                }
            }
        }
        
        return $array;
    }
    
    /**
     * Format timestamp for protobuf
     */
    protected function formatTimestamp($date): string
    {
        if (!$date) {
            return '';
        }
        if (is_string($date)) {
            return $date;
        }
        return $date->toISOString();
    }
    
    /**
     * Handle gRPC errors consistently
     */
    protected function handleError(\Throwable $e, string $operation = 'operation'): array
    {
        Log::error("gRPC {$operation} failed", [
            'code' => $e->getCode(),
        ]);
        
        return [
            'success' => false,
            'message' => 'The requested operation could not be completed.',
        ];
    }
    
    /**
     * Create pagination metadata
     */
    protected function createPagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}
