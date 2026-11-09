<?php

namespace Tests\Feature\GraphQL;

use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\User\User;
use App\Models\UserFcmDevice\UserFcmDevice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Kontrak GraphQL untuk screen mobile yang sebelumnya hanya punya REST:
 * notifikasi dan chat.
 *
 * Sengaja TIDAK memanggil `$this->seed()`. Seeder penuh butuh ~1000 detik per
 * test, dan yang diverifikasi di sini hanyalah bentuk respons resolver, bukan
 * data katalog. Setiap test membuat baris yang dibutuhkannya saja.
 */
class GraphQLMobileContractTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tabel ini dipakai NotificationController (REST) dan registerFcmToken
        // (GraphQL), tetapi tidak punya migration. Test membuat sendiri hanya
        // kalau benar-benar belum ada, supaya sqlite in-memory bisa menjalankan
        // kontrak yang sama dengan MySQL development.
        if (! Schema::hasTable('user_fcm_devices')) {
            Schema::create('user_fcm_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->text('fcm_token');
                $table->string('platform')->nullable();
                $table->string('device_name')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function actAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function postGraphql(string $query, array $variables = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/graphql', ['query' => $query, 'variables' => $variables]);
    }

    public function test_private_notification_fields_require_login(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => '{ myNotifications { total } }',
        ]);

        $response->assertStatus(401);
    }

    public function test_my_notifications_returns_rows_and_counters(): void
    {
        $user = $this->actAsUser();
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\OrderCompleted',
            'data' => ['title' => 'Pesanan selesai', 'body' => 'Terima kasih sudah memesan.'],
        ]);

        $response = $this->postGraphql('
            query {
                myNotifications(first: 10) {
                    data { id title body readAt createdAt }
                    currentPage
                    lastPage
                    perPage
                    total
                    unreadCount
                }
                unreadNotificationCount
            }
        ');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertSame(1, $data['unreadNotificationCount']);
        $this->assertSame(1, $data['myNotifications']['total']);
        $this->assertSame(1, $data['myNotifications']['unreadCount']);
        $this->assertSame('Pesanan selesai', $data['myNotifications']['data'][0]['title']);
        $this->assertNotEmpty($data['myNotifications']['data'][0]['id']);
    }

    public function test_unreadOnly_filter_and_mark_as_read_mutation(): void
    {
        $user = $this->actAsUser();
        $unread = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\OrderShipped',
            'data' => ['title' => 'Dikirim'],
        ]);
        $read = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\OrderShipped',
            'data' => ['title' => 'Sudah dibaca'],
        ]);
        $read->markAsRead();

        $filtered = $this->postGraphql('
            query { myNotifications(first: 10, unreadOnly: true) { data { title } total } }
        ');
        $filtered->assertStatus(200);
        $this->assertSame(1, $filtered->json('data.myNotifications.total'));
        $this->assertSame('Dikirim', $filtered->json('data.myNotifications.data.0.title'));

        $mutation = $this->postGraphql('
            mutation ($id: ID!) { markNotificationAsRead(id: $id) }
        ', ['id' => (string) $unread->id]);
        $mutation->assertStatus(200);
        $this->assertTrue($mutation->json('data.markNotificationAsRead'));
        $this->assertNotNull($unread->fresh()->read_at);

        $markAll = $this->postGraphql('mutation { markAllNotificationsAsRead }');
        $markAll->assertStatus(200);
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_delete_notification_mutation_removes_the_row(): void
    {
        $user = $this->actAsUser();
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Generic',
            'data' => ['title' => 'Sesaat lagi hilang'],
        ]);

        $response = $this->postGraphql('
            mutation ($id: ID!) { deleteNotification(id: $id) }
        ', ['id' => (string) $notification->id]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.deleteNotification'));
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_register_fcm_token_stores_device_and_user_token(): void
    {
        $user = $this->actAsUser();

        $response = $this->postGraphql('
            mutation ($input: RegisterFcmTokenInput!) { registerFcmToken(input: $input) }
        ', ['input' => ['token' => 'demo-token', 'platform' => 'android', 'deviceName' => 'Test Device']]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.registerFcmToken'));
        $this->assertDatabaseHas('user_fcm_devices', ['user_id' => $user->id, 'platform' => 'android']);
        $this->assertSame('demo-token', $user->fresh()->fcm_token);
    }

    public function test_register_fcm_token_rejects_unknown_platform(): void
    {
        $this->actAsUser();

        $response = $this->postGraphql('
            mutation ($input: RegisterFcmTokenInput!) { registerFcmToken(input: $input) }
        ', ['input' => ['token' => 'demo-token', 'platform' => 'windows']]);

        $response->assertStatus(200);
        $this->assertNull($response->json('data.registerFcmToken'));
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_my_conversations_exposes_inbox_with_last_message(): void
    {
        $user = $this->actAsUser();
        $other = User::factory()->create();
        $inbox = Inbox::create(['user_ids' => [(int) $user->id, (int) $other->id], 'title' => 'Chat Bantuan']);
        Message::create(['inbox_id' => $inbox->id, 'user_id' => $other->id, 'message' => 'Halo, ada yang bisa dibantu?']);

        $response = $this->postGraphql('
            query {
                myConversations {
                    data {
                        id
                        title
                        unreadCount
                        lastMessage { id message senderId }
                        otherUser { id name }
                    }
                    currentUserIsStaff
                }
                conversationUnreadCount
            }
        ');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.myConversations.data.0.unreadCount'));
        $this->assertSame('Halo, ada yang bisa dibantu?', $response->json('data.myConversations.data.0.lastMessage.message'));
        $this->assertSame(1, $response->json('data.conversationUnreadCount'));
        $this->assertFalse($response->json('data.myConversations.currentUserIsStaff'));
    }

    public function test_conversation_messages_returns_the_whole_thread_for_members(): void
    {
        $user = $this->actAsUser();
        $other = User::factory()->create();
        $inbox = Inbox::create(['user_ids' => [(int) $user->id, (int) $other->id], 'title' => 'Chat Bantuan']);
        Message::create(['inbox_id' => $inbox->id, 'user_id' => $other->id, 'message' => 'Pertanyaan']);
        Message::create(['inbox_id' => $inbox->id, 'user_id' => $user->id, 'message' => 'Jawaban']);

        $response = $this->postGraphql('
            query ($inboxId: ID!) {
                conversationMessages(inboxId: $inboxId, first: 50) {
                    data { id message senderId isMe createdAt }
                    otherUser { id name }
                }
            }
        ', ['inboxId' => (string) $inbox->id]);

        $response->assertStatus(200);
        $rows = $response->json('data.conversationMessages.data');
        $this->assertCount(2, $rows);
        // oldest-first: pertanyaan sebelum jawaban
        $this->assertSame('Pertanyaan', $rows[0]['message']);
        $this->assertFalse($rows[0]['isMe']);
        $this->assertTrue($rows[1]['isMe']);
        $this->assertSame((string) $other->id, $response->json('data.conversationMessages.otherUser.id'));
    }

    public function test_non_participant_cannot_read_a_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $inbox = Inbox::create(['user_ids' => [(int) $owner->id, (int) $other->id], 'title' => 'Chat Bantuan']);
        Message::create(['inbox_id' => $inbox->id, 'user_id' => $other->id, 'message' => 'Rahasia']);

        $stranger = $this->actAsUser();

        $response = $this->postGraphql('
            query ($inboxId: ID!) { conversationMessages(inboxId: $inboxId) { data { id } } }
        ', ['inboxId' => (string) $inbox->id]);

        $response->assertStatus(200);
        $this->assertNull($response->json('data.conversationMessages'));
        $this->assertNotEmpty($response->json('errors'));
        $this->assertFalse(str_contains(json_encode($response->json(), JSON_THROW_ON_ERROR), 'Rahasia'));
        unset($stranger);
    }

    public function test_send_chat_message_creates_a_row_and_returns_it(): void
    {
        Queue::fake();

        $user = $this->actAsUser();
        $other = User::factory()->create();
        $inbox = Inbox::create(['user_ids' => [(int) $user->id, (int) $other->id], 'title' => 'Chat Bantuan']);

        $response = $this->postGraphql('
            mutation ($input: SendChatMessageInput!) {
                sendChatMessage(input: $input) { id message isMe inboxId createdAt }
            }
        ', ['input' => ['inboxId' => (string) $inbox->id, 'message' => 'Terima kasih!']]);

        $response->assertStatus(200);
        $this->assertSame('Terima kasih!', $response->json('data.sendChatMessage.message'));
        $this->assertTrue($response->json('data.sendChatMessage.isMe'));
        $this->assertDatabaseHas('fm_messages', ['inbox_id' => $inbox->id, 'message' => 'Terima kasih!']);
        // Bot reply adalah efek samping jalur non-staf; pastikan tidak jalan di test ini.
        Queue::assertPushed(\App\Jobs\SendBotReply\SendBotReply::class);
    }

    public function test_start_chat_conversation_reuses_existing_inbox(): void
    {
        Queue::fake();
        $user = $this->actAsUser();
        $other = User::factory()->create();
        $inbox = Inbox::create(['user_ids' => [(int) $user->id, (int) $other->id], 'title' => 'Chat Bantuan']);

        $response = $this->postGraphql('
            mutation ($input: StartChatConversationInput!) {
                startChatConversation(input: $input) { id unreadCount }
            }
        ', ['input' => ['withUserId' => (string) $other->id]]);

        $response->assertStatus(200);
        $this->assertSame((string) $inbox->id, $response->json('data.startChatConversation.id'));
        $this->assertSame(1, Inbox::count());
        unset($user, $other, $inbox, $response);
    }

    public function test_chat_message_reactions_and_stars_are_persisted_in_meta(): void
    {
        $user = $this->actAsUser();
        $other = User::factory()->create();
        $inbox = Inbox::create(['user_ids' => [(int) $user->id, (int) $other->id], 'title' => 'Chat Bantuan']);
        $message = Message::create(['inbox_id' => $inbox->id, 'user_id' => $other->id, 'message' => 'Tolong dicek']);

        $reaction = $this->postGraphql('
            mutation ($id: ID!) { addChatReaction(messageId: $id, emoji: "❤️") }
        ', ['id' => (string) $message->id]);
        $reaction->assertStatus(200);
        $this->assertTrue($reaction->json('data.addChatReaction'));

        $star = $this->postGraphql('
            mutation ($id: ID!) { starChatMessage(messageId: $id) }
        ', ['id' => (string) $message->id]);
        $star->assertStatus(200);
        $this->assertTrue($star->json('data.starChatMessage'));

        $meta = $message->fresh()->meta ?? [];
        $this->assertNotEmpty($meta['reactions'] ?? []);
        $this->assertNotEmpty($meta['starred_by'] ?? []);
        unset($user, $other, $inbox, $message, $reaction, $star, $meta);
    }

    public function test_mark_conversation_as_read_clears_unread_count(): void
    {
        $user = $this->actAsUser();
        $other = User::factory()->create();
        $inbox = Inbox::create(['user_ids' => [(int) $user->id, (int) $other->id], 'title' => 'Chat Bantuan']);
        Message::create(['inbox_id' => $inbox->id, 'user_id' => $other->id, 'message' => 'Halo']);
        $this->assertSame(1, Message::whereIn('inbox_id', [$inbox->id])->count());

        $response = $this->postGraphql('
            mutation ($inboxId: ID!) { markConversationAsRead(inboxId: $inboxId) }
        ', ['inboxId' => (string) $inbox->id]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.markConversationAsRead'));
        $this->assertSame(0, $this->postGraphql('query { conversationUnreadCount }')->json('data.conversationUnreadCount'));
    }

    public function test_home_and_catalog_contract_still_resolves_for_mobile(): void
    {
        // Pertahanan terhadap regresi schema: screen Home/Catalog membaca field
        // ini lewat GraphQL; kalau salah satu hilang, seluruh query gagal.
        $catalog = \App\Models\Catalog\Catalog::factory()->create();

        $response = $this->postGraphql('
            query {
                homeData {
                    stats { totalCatalogs totalPackages }
                    categories { id name }
                }
                catalogs(first: 5) {
                    data { id name stock }
                    paginatorInfo { total currentPage }
                }
            }
        ');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.homeData.stats.totalPackages'));
        $this->assertSame((string) $catalog->id, $response->json('data.catalogs.data.0.id'));
    }

    public function test_search_returns_grouped_results_for_mobile(): void
    {
        // Field `search` pernah dideklarasikan `[SearchResult!]!` sementara
        // resolvernya mengembalikan bentuk grouped. Setiap panggilan mobile
        // gagal dengan "Cannot return null for non-nullable field SearchResult.id".
        // Bentuk kanoniknya adalah grouped, sama seperti REST /api/search dan
        // pesan protobuf `weddingapp.Search.SearchResults`.
        $catalog = \App\Models\Catalog\Catalog::factory()->create([
            'name' => 'Buket Mawar Verifikasi Kontrak',
            'is_active' => true,
            'stock' => 5,
        ]);

        $package = \App\Models\Package\Package::factory()->create([
            'name' => 'Paket Mawar Verifikasi Kontrak',
            'is_active' => true,
            'catalog_id' => $catalog->id,
        ]);

        $category = \App\Models\Category\Category::factory()->create([
            'name' => 'Kategori Mawar Verifikasi Kontrak',
        ]);

        $response = $this->postGraphql('
            query Search($query: String!, $first: Int) {
                search(query: $query, first: $first) {
                    query
                    took
                    totalCatalogs
                    totalPackages
                    catalogs { id name tiers { id finalPrice } }
                    packages { id name finalPrice }
                    categories { id name }
                }
            }
        ', ['query' => 'Mawar Verifikasi Kontrak', 'first' => 5]);

        $response->assertStatus(200);
        $this->assertNull($response->json('errors'), 'Field search harus selesai tanpa error.');

        $this->assertSame((string) $catalog->id, $response->json('data.search.catalogs.0.id'));
        $this->assertSame((string) $package->id, $response->json('data.search.packages.0.id'));
        $this->assertSame((string) $category->id, $response->json('data.search.categories.0.id'));
        $this->assertSame('Mawar Verifikasi Kontrak', $response->json('data.search.query'));
        $this->assertSame(1, $response->json('data.search.totalCatalogs'));
        $this->assertSame(1, $response->json('data.search.totalPackages'));
        $this->assertIsNumeric($response->json('data.search.took'));
    }

    public function test_business_rejections_reach_the_client_with_their_message(): void
    {
        // Resolver GraphQL melempar `\Exception` polos untuk penolakan bisnis.
        // Tanpa App\Http\GraphQL\Errors\RevealBusinessErrorHandler, mobile hanya
        // melihat "Internal server error" dan tidak bisa menampilkan alasannya.
        $this->actAsUser();

        // `cbirStats` admin-only: penolakannya tidak menulis apa pun ke database.
        $response = $this->postGraphql('{ cbirStats { totalImages } }');

        $response->assertStatus(200);
        $this->assertSame('Unauthorized', $response->json('errors.0.message'));
        $this->assertSame('business', $response->json('errors.0.extensions.category'));
        $this->assertNull($response->json('data.cbirStats'));
    }
}
