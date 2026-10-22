<?php

namespace App\Services\GraphQLExecutor;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Execution\ContextFactory;
use Nuwave\Lighthouse\Schema\Schema;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GraphQLExecutor
{
    /**
     * Execute a GraphQL query/mutation and return the result.
     *
     * @param string $query GraphQL query string
     * @param array $variables Variables for the query
     * @param string|null $operationName Operation name (for queries with multiple operations)
     * @param array $contextData Additional context data (e.g., user for auth)
     * @return array ['data' => ..., 'errors' => ...]
     */
    public function execute(
        string $query,
        array $variables = [],
        ?string $operationName = null,
        array $contextData = []
    ): array {
        try {
            /** @var \Nuwave\Lighthouse\GraphQL $graphql */
            $graphql = App::make(\Nuwave\Lighthouse\GraphQL::class);
            
            /** @var ContextFactory $contextFactory */
            $contextFactory = App::make(ContextFactory::class);
            
            // Create GraphQL context with optional user
            $req = request()->duplicate();
            $req->merge([
                'query' => $query,
                'variables' => $variables,
                'operationName' => $operationName,
            ]);
            if (isset($contextData['user'])) {
                $req->setUserResolver(fn () => $contextData['user']);
            }
            $context = $contextFactory->generate($req);
            
            // Execute the query
            $result = $graphql->executeQueryString($query, $context, $variables, null, $operationName);
            
            return is_array($result) ? $result : (method_exists($result, 'toArray') ? $result->toArray() : (array) $result);
            
        } catch (\Throwable $e) {
            Log::error('GraphQLExecutor error: ' . $e->getMessage(), [
                'query' => $query,
                'variables' => $variables,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'data' => null,
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                        'extensions' => [
                            'category' => 'internal',
                        ],
                    ],
                ],
            ];
        }
    }
    
    /**
     * Execute a GraphQL query and return only the data portion.
     * Throws exception if there are errors.
     */
    public function executeOrFail(
        string $query,
        array $variables = [],
        ?string $operationName = null,
        array $contextData = []
    ): mixed {
        $result = $this->execute($query, $variables, $operationName, $contextData);
        
        if (!empty($result['errors'])) {
            throw new \Exception(
                $result['errors'][0]['message'] ?? 'GraphQL execution failed'
            );
        }
        
        return $result['data'] ?? null;
    }
    
    /**
     * Execute a mutation and return the result data.
     */
    public function mutate(
        string $mutation,
        array $variables = [],
        array $contextData = []
    ): mixed {
        return $this->executeOrFail($mutation, $variables, null, $contextData);
    }
    
    /**
     * Execute a query and return the result data.
     */
    public function query(
        string $query,
        array $variables = [],
        array $contextData = []
    ): mixed {
        return $this->executeOrFail($query, $variables, null, $contextData);
    }
}