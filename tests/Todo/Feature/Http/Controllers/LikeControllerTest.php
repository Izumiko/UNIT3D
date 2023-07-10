<?php

namespace Tests\Todo\Feature\Http\Controllers;

use PHPUnit\Framework\Attributes\Test;
use App\Models\Like;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\LikeController
 */
final class LikeControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function destroy_returns_an_ok_response(): void
    {
        $this->markTestIncomplete('This test case was generated by Shift. When you are ready, remove this line and complete this test case.');

        $like = Like::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('dislike', ['postId' => $like->postId]));

        $response->assertRedirect(withErrors('You have already liked/disliked this post!'));
        $this->assertModelMissing($dislike);

        // TODO: perform additional assertions
    }

    #[Test]
    public function store_returns_an_ok_response(): void
    {
        $this->markTestIncomplete('This test case was generated by Shift. When you are ready, remove this line and complete this test case.');

        $like = Like::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('like', ['postId' => $like->postId]));

        $response->assertRedirect(withErrors('You have already liked/disliked this post!'));

        // TODO: perform additional assertions
    }

    // test cases...
}
