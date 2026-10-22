<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class GraphQLServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge GraphQL config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/graphql.php',
            'graphql'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register GraphQL validation rules
        $this->registerValidationRules();

        // Register GraphQL macros
        $this->registerMacros();
    }

    /**
     * Register GraphQL validation rules.
     */
    protected function registerValidationRules(): void
    {
        // GraphQL query validation
        Validator::extend('graphql_query', function ($attribute, $value, $parameters, $validator) {
            if (!is_string($value) || empty($value)) {
                return false;
            }

            // Basic GraphQL query validation
            // Check for common GraphQL keywords
            $hasQueryKeyword = preg_match('/\b(query|mutation|subscription)\b/i', $value);
            $hasValidSyntax = strpos($value, '{') !== false && strpos($value, '}') !== false;

            return $hasQueryKeyword && $hasValidSyntax;
        }, 'The :attribute must be a valid GraphQL query.');

        // GraphQL variables validation
        Validator::extend('graphql_variables', function ($attribute, $value, $parameters, $validator) {
            if ($value === null) {
                return true;
            }

            return is_array($value);
        }, 'The :attribute must be a valid JSON object.');
    }

    /**
     * Register GraphQL macros.
     */
    protected function registerMacros(): void
    {
        // Add any custom macros here if needed
    }
}