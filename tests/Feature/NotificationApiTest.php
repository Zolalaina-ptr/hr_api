<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }

    public function test_can_list_notifications(): void
    {
        Sanctum::actingAs($this->user);

        $this->user->notify(new TestDatabaseNotification('first'));
        $this->user->notify(new TestDatabaseNotification('second'));

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'type', 'data', 'read_at'],
                    ],
                    'meta',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.meta.total'));
    }

    public function test_list_only_returns_own_notifications(): void
    {
        $other = User::factory()->create();
        $other->notify(new TestDatabaseNotification('other'));

        Sanctum::actingAs($this->user);

        $this->user->notify(new TestDatabaseNotification('mine'));

        $response = $this->getJson('/api/notifications');

        $this->assertEquals(1, $response->json('data.meta.total'));
        $this->assertStringContainsString('mine', $response->json('data.data.0.data.message'));
    }

    public function test_unread_returns_only_unread_notifications(): void
    {
        Sanctum::actingAs($this->user);

        $this->user->notify(new TestDatabaseNotification('a'));
        $this->user->notify(new TestDatabaseNotification('b'));
        $this->user->notify(new TestDatabaseNotification('c'));

        $this->user->notifications()->first()->markAsRead();

        $response = $this->getJson('/api/notifications/unread');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_mark_notification_as_read(): void
    {
        Sanctum::actingAs($this->user);

        $this->user->notify(new TestDatabaseNotification('to read'));
        $id = $this->user->notifications()->first()->id;

        $response = $this->patchJson("/api/notifications/{$id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $id);

        $this->assertNotNull($this->user->notifications()->first()->read_at);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        Sanctum::actingAs($this->user);

        $this->user->notify(new TestDatabaseNotification('1'));
        $this->user->notify(new TestDatabaseNotification('2'));

        $response = $this->patchJson('/api/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    public function test_can_delete_notification(): void
    {
        Sanctum::actingAs($this->user);

        $this->user->notify(new TestDatabaseNotification('delete me'));
        $id = $this->user->notifications()->first()->id;

        $response = $this->deleteJson("/api/notifications/{$id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals(0, $this->user->notifications()->count());
    }

    public function test_cannot_access_another_users_notification(): void
    {
        $other = User::factory()->create();
        $other->notify(new TestDatabaseNotification('theirs'));
        $otherId = $other->notifications()->first()->id;

        Sanctum::actingAs($this->user);

        $this->patchJson("/api/notifications/{$otherId}/read")->assertStatus(404);
        $this->deleteJson("/api/notifications/{$otherId}")->assertStatus(404);
    }

    public function test_unknown_notification_returns_404(): void
    {
        Sanctum::actingAs($this->user);

        $this->patchJson('/api/notifications/999999999/read')->assertStatus(404);
    }
}

class TestDatabaseNotification extends Notification
{
    public function __construct(private string $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['message' => $this->message];
    }
}
