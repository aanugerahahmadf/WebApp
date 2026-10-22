<?php

namespace App\Http\Controllers\Api\User\Controller;

use App\Services\GraphQLExecutor\GraphQLExecutor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class GraphQLDelegatingController extends Controller
{
    protected GraphQLExecutor $graphql;
    
    public function __construct(GraphQLExecutor $graphql)
    {
        $this->graphql = $graphql;
    }
    
    /**
     * Get the authenticated user for GraphQL context.
     */
    protected function getGraphQLUser(): ?object
    {
        return Auth::user();
    }
    
    /**
     * Execute a GraphQL query and return formatted JSON response.
     */
    protected function graphqlQuery(
        string $query,
        array $variables = [],
        int $successCode = 200
    ): JsonResponse {
        $user = $this->getGraphQLUser();
        $contextData = $user ? ['user' => $user] : [];
        
        $result = $this->graphql->execute($query, $variables, null, $contextData);
        
        if (!empty($result['errors'])) {
            $error = $result['errors'][0];
            $message = $error['message'] ?? 'GraphQL query failed';
            $code = $error['extensions']['code'] ?? 500;
            
            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], $code);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $result['data'],
        ], $successCode);
    }
    
    /**
     * Execute a GraphQL mutation and return formatted JSON response.
     */
    protected function graphqlMutation(
        string $mutation,
        array $variables = [],
        int $successCode = 200
    ): JsonResponse {
        $user = $this->getGraphQLUser();
        $contextData = $user ? ['user' => $user] : [];
        
        $result = $this->graphql->execute($mutation, $variables, null, $contextData);
        
        if (!empty($result['errors'])) {
            $error = $result['errors'][0];
            $message = $error['message'] ?? 'GraphQL mutation failed';
            $code = $error['extensions']['code'] ?? 500;
            
            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], $code);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $result['data'],
        ], $successCode);
    }
    
    /**
     * Execute GraphQL and return raw result for custom handling.
     */
    protected function graphqlRaw(
        string $query,
        array $variables = []
    ): array {
        $user = $this->getGraphQLUser();
        $contextData = $user ? ['user' => $user] : [];
        
        return $this->graphql->execute($query, $variables, null, $contextData);
    }
    
    /**
     * Build pagination response from GraphQL paginated result.
     */
    protected function paginatedResponse(array $graphqlPaginationData, string $dataKey): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $graphqlPaginationData[$dataKey]['data'] ?? $graphqlPaginationData[$dataKey],
            'pagination' => [
                'current_page' => $graphqlPaginationData[$dataKey]['paginatorInfo']['currentPage'] ?? 1,
                'last_page' => $graphqlPaginationData[$dataKey]['paginatorInfo']['lastPage'] ?? 1,
                'per_page' => $graphqlPaginationData[$dataKey]['paginatorInfo']['perPage'] ?? 10,
                'total' => $graphqlPaginationData[$dataKey]['paginatorInfo']['total'] ?? 0,
                'has_more_pages' => $graphqlPaginationData[$dataKey]['paginatorInfo']['hasMorePages'] ?? false,
            ],
        ]);
    }
}