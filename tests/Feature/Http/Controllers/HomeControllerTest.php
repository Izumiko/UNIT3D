<?php

namespace Tests\Feature\Http\Controllers;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use Database\Seeders\GroupsTableSeeder;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\HomeController
 */
final class HomeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GroupsTableSeeder::class);
    }

    #[Test]
    public function whenNotAuthenticatedHomepageRedirectsToLogin(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function whenAuthenticatedHomepageReturns200(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertOk()
            ->assertViewIs('home.index')
            ->assertViewHas('user')
            ->assertViewHas('personal_freeleech')
            ->assertViewHas('users')
            ->assertViewHas('groups')
            ->assertViewHas('articles')
            ->assertViewHas('newest')
            ->assertViewHas('seeded')
            ->assertViewHas('dying')
            ->assertViewHas('leeched')
            ->assertViewHas('dead')
            ->assertViewHas('topics')
            ->assertViewHas('posts')
            ->assertViewHas('featured')
            ->assertViewHas('poll')
            ->assertViewHas('uploaders')
            ->assertViewHas('past_uploaders')
            ->assertViewHas('freeleech_tokens')
            ->assertViewHas('bookmarks');
    }

    #[Test]
    public function whenAuthenticatedAndTwoStepRequiredHomepageRedirectsToTwoStep(): void
    {
        $user = User::factory()->create([
            'twostep' => true,
        ]);

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertRedirect(route('verificationNeeded'));
    }
}
