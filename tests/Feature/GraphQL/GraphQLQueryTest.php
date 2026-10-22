<?php

namespace Tests\Feature\GraphQL;

use App\Models\Catalog\Catalog;
use App\Models\Category\Category;
use App\Models\Package\Package;
use App\Models\User\User;
use App\Services\GraphQLExecutor\GraphQLExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphQLQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }
    public function test_can_query_categories_via_graphql(): void
    {
        $category = Category::factory()->create([
            'name' => 'Dekorasi Pelaminan Eksklusif',
        ]);

        $query = '
            query {
                allCategories {
                    id
                    name
                    isActive
                }
            }
        ';

        $response = $this->postJson('/graphql', [
            'query' => $query,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'allCategories' => [
                    '*' => ['id', 'name', 'isActive'],
                ],
            ],
        ]);
    }

    public function test_can_query_home_data(): void
    {
        $query = '
            query {
                homeData {
                    stats {
                        totalCatalogs
                        totalPackages
                    }
                }
            }
        ';

        $response = $this->postJson('/graphql', [
            'query' => $query,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'homeData' => [
                    'stats' => ['totalCatalogs', 'totalPackages'],
                ],
            ],
        ]);
    }

    public function test_can_query_catalogs_pagination(): void
    {
        $query = '
            query {
                catalogs(first: 5) {
                    data {
                        id
                        name
                        price
                    }
                    paginatorInfo {
                        total
                        currentPage
                    }
                }
            }
        ';

        $response = $this->postJson('/graphql', [
            'query' => $query,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'catalogs' => [
                    'data',
                    'paginatorInfo' => ['total', 'currentPage'],
                ],
            ],
        ]);
    }

    public function test_graphql_executor_service_executes_query(): void
    {
        $executor = app(GraphQLExecutor::class);
        $result = $executor->execute('
            query {
                homeData {
                    stats {
                        totalCatalogs
                    }
                }
            }
        ');

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayNotHasKey('errors', $result);
    }
}
