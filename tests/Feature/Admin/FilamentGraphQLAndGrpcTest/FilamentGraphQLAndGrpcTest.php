<?php

namespace Tests\Feature\Admin\FilamentGraphQLAndGrpcTest;

use App\Filament\Admin\Resources\DeveloperStudioResource\DeveloperStudioResource;
use App\Filament\Admin\Resources\DeveloperStudioResource\Pages\ListDeveloperStudio\ListDeveloperStudio;
use App\Filament\Concerns\InteractsWithGraphQLAndGrpc\InteractsWithGraphQLAndGrpc;
use App\Models\Admin\Admin;
use App\Models\DeveloperStudio\DeveloperStudioEndpoint;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentGraphQLAndGrpcTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->superAdmin = Admin::factory()->create([
            'email' => 'superadmin.graphql_grpc@test.local',
            'active_status' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_component_can_use_interacts_with_graphql_and_grpc_trait(): void
    {
        $component = new class {
            use InteractsWithGraphQLAndGrpc;
        };

        $result = $component->executeGraphQL('{ __typename }');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertSame('Query', $result['data']['__typename'] ?? null);

        $data = $component->executeGraphQLData('{ __typename }');
        $this->assertIsArray($data);
        $this->assertSame('Query', $data['__typename'] ?? null);

        $health = $component->checkApiEnginesHealth();
        $this->assertIsArray($health);
        $this->assertArrayHasKey('graphql', $health);
        $this->assertArrayHasKey('grpc', $health);
        $this->assertArrayHasKey('overall', $health);
        $this->assertSame('healthy', $health['graphql']['status']);
        $this->assertSame('healthy', $health['overall']);
    }

    public function test_developer_studio_header_actions_for_graphql_and_grpc_exist(): void
    {
        $this->actingAs($this->superAdmin, 'admin');

        Livewire::test(ListDeveloperStudio::class)
            ->assertTableActionExists('run_graphql_query')
            ->assertTableActionExists('check_grpc_status');
    }

    public function test_super_admin_can_call_check_grpc_status_header_action(): void
    {
        $this->actingAs($this->superAdmin, 'admin');

        Livewire::test(ListDeveloperStudio::class)
            ->callTableAction('check_grpc_status')
            ->assertHasNoActionErrors();
    }

    public function test_super_admin_can_call_run_graphql_query_header_action(): void
    {
        $this->actingAs($this->superAdmin, 'admin');

        Livewire::test(ListDeveloperStudio::class)
            // `callTableAction($nama, $record, $data)` -- parameter kedua
            // adalah RECORD, jadi data aksi dikirim di parameter KETIGA.
            ->callTableAction('run_graphql_query', null, [
                'query' => '{ __typename }',
                'variables' => '{}',
            ])
            ->assertHasNoActionErrors();
    }

    public function test_developer_studio_table_actions_for_graphql_record(): void
    {
        $graphqlEndpoint = DeveloperStudioEndpoint::create([
            'type' => 'graphql',
            'method' => 'POST',
            'uri' => 'graphql',
            'name' => 'graphql.query.typename',
            'action' => 'GraphQL Schema Execution',
            'status' => 'active',
            'payload' => json_encode(['query' => '{ __typename }']),
        ]);

        $this->actingAs($this->superAdmin, 'admin');

        Livewire::test(ListDeveloperStudio::class)
            ->assertTableActionExists('test_graphql')
            ->callTableAction('test_graphql', $graphqlEndpoint)
            ->assertHasNoTableActionErrors();
    }

    public function test_developer_studio_table_actions_for_grpc_record(): void
    {
        $grpcEndpoint = DeveloperStudioEndpoint::create([
            'type' => 'grpc',
            'method' => 'gRPC',
            'uri' => 'App\Grpc\User\V1\UserServiceClient',
            'name' => 'grpc.user.v1.userservice',
            'action' => 'App\Grpc\Services\UserService',
            'status' => 'active',
        ]);

        $this->actingAs($this->superAdmin, 'admin');

        Livewire::test(ListDeveloperStudio::class)
            ->assertTableActionExists('test_grpc')
            ->callTableAction('test_grpc', $grpcEndpoint)
            ->assertHasNoTableActionErrors();
    }
}
