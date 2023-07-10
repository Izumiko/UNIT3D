<?php

namespace Tests\Todo\Feature\Http\Controllers\Staff;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Staff\AuthenticationController
 */
final class AuthenticationControllerTest extends TestCase
{
    #[Test]
    public function index_returns_an_ok_response(): void
    {
        $this->markTestIncomplete('This test case was generated by Shift. When you are ready, remove this line and complete this test case.');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('staff.authentications.index'));

        $response->assertOk();
        $response->assertViewIs('Staff.authentication.index');
        $response->assertViewHas('attempts');

        // TODO: perform additional assertions
    }

    // test cases...
}
