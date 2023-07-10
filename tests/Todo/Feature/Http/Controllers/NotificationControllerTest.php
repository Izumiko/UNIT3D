<?php

namespace Tests\Todo\Feature\Http\Controllers;

use PHPUnit\Framework\Attributes\Test;
use App\Models\Notification;
use App\Models\User;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\NotificationController
 */
final class NotificationControllerTest extends TestCase
{
    #[Test]
    public function destroy_returns_an_ok_response(): void
    {
        $this->markTestIncomplete('This test case was generated by Shift. When you are ready, remove this line and complete this test case.');

        $notification = Notification::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('users.notifications.destroy', ['user' => $user, 'notification' => $notification]));

        $response->assertRedirect(withSuccess('Notification Deleted!'));
        $this->assertModelMissing($notifications);

        // TODO: perform additional assertions
    }

    #[Test]
    public function destroy_all_returns_an_ok_response(): void
    {
        $this->markTestIncomplete('This test case was generated by Shift. When you are ready, remove this line and complete this test case.');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('users.notifications.mass_destroy', ['user' => $user]));

        $response->assertRedirect(withSuccess('All Notifications Deleted!'));

        // TODO: perform additional assertions
    }

    #[Test]
    public function index_returns_an_ok_response(): void
    {
        $this->markTestIncomplete('This test case was generated by Shift. When you are ready, remove this line and complete this test case.');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('users.notifications.index', ['user' => $user]));

        $response->assertOk();
        $response->assertViewIs('notification.index');
        $response->assertViewHas('notifications');

        // TODO: perform additional assertions
    }

    #[Test]
    public function show_returns_an_ok_response(): void
    {
        $this->markTestIncomplete('This test case was generated by Shift. When you are ready, remove this line and complete this test case.');

        $notification = Notification::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('users.notifications.show', ['user' => $user, 'notification' => $notification]));

        $response->assertRedirect(withSuccess('Notification Marked As Read!'));

        // TODO: perform additional assertions
    }

    #[Test]
    public function update_returns_an_ok_response(): void
    {
        $this->markTestIncomplete('This test case was generated by Shift. When you are ready, remove this line and complete this test case.');

        $notification = Notification::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('users.notifications.update', ['user' => $user, 'notification' => $notification]), [
            // TODO: send request data
        ]);

        $response->assertRedirect(withErrors('Notification Does Not Exist!'));

        // TODO: perform additional assertions
    }

    #[Test]
    public function update_all_returns_an_ok_response(): void
    {
        $this->markTestIncomplete('This test case was generated by Shift. When you are ready, remove this line and complete this test case.');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('users.notifications.mass_update', ['user' => $user]), [
            // TODO: send request data
        ]);

        $response->assertRedirect(withSuccess('All Notifications Marked As Read!'));

        // TODO: perform additional assertions
    }

    // test cases...
}
