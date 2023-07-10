<?php

namespace Tests\Feature\Http\Controllers\Staff;

use PHPUnit\Framework\Attributes\Test;
use App\Models\Group;
use App\Models\Type;
use App\Models\User;
use Database\Seeders\GroupsTableSeeder;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Staff\TypeController
 */
final class TypeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function createStaffUser(): \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
    {
        return User::factory()->create([
            'group_id' => fn () => Group::factory()->create([
                'is_owner' => true,
                'is_admin' => true,
                'is_modo'  => true,
            ])->id,
        ]);
    }

    #[Test]
    public function create_returns_an_ok_response(): void
    {
        $this->seed(GroupsTableSeeder::class);

        $user = $this->createStaffUser();

        $response = $this->actingAs($user)->get(route('staff.types.create'));

        $response->assertOk();
        $response->assertViewIs('Staff.type.create');
    }

    #[Test]
    public function destroy_returns_an_ok_response(): void
    {
        $this->seed(GroupsTableSeeder::class);

        $user = $this->createStaffUser();
        $type = Type::factory()->create();

        $response = $this->actingAs($user)->delete(route('staff.types.destroy', ['type' => $type]));

        $response->assertRedirect(route('staff.types.index'));
    }

    #[Test]
    public function edit_returns_an_ok_response(): void
    {
        $this->seed(GroupsTableSeeder::class);

        $user = $this->createStaffUser();
        $type = Type::factory()->create();

        $response = $this->actingAs($user)->get(route('staff.types.edit', ['type' => $type]));

        $response->assertOk();
        $response->assertViewIs('Staff.type.edit');
        $response->assertViewHas('type');
    }

    #[Test]
    public function index_returns_an_ok_response(): void
    {
        $this->seed(GroupsTableSeeder::class);

        $user = $this->createStaffUser();

        $response = $this->actingAs($user)->get(route('staff.types.index'));

        $response->assertOk();
        $response->assertViewIs('Staff.type.index');
        $response->assertViewHas('types');
    }

    #[Test]
    public function store_returns_an_ok_response(): void
    {
        $this->seed(GroupsTableSeeder::class);

        $user = $this->createStaffUser();
        $type = Type::factory()->make();

        $response = $this->actingAs($user)->post(route('staff.types.store'), [
            'name'     => $type->name,
            'position' => $type->position,
        ]);

        $response->assertRedirect(route('staff.types.index'));
    }

    #[Test]
    public function update_returns_an_ok_response(): void
    {
        $this->seed(GroupsTableSeeder::class);

        $user = $this->createStaffUser();
        $type = Type::factory()->create();

        $response = $this->actingAs($user)->patch(route('staff.types.update', ['type' => $type]), [
            'name'     => $type->name,
            'position' => $type->position,
        ]);

        $response->assertRedirect(route('staff.types.index'));
    }
}
