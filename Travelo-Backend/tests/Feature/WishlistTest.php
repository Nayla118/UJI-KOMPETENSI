<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Wishlist/favourites: owner-scoped CRUD, item validation, and the optional
 * `is_wishlisted` enrichment on public catalog endpoints.
 */
class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Destination $destination;

    private TourPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Traveller',
            'email' => 'traveller-'.uniqid().'@example.com',
        ]);

        $this->destination = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
            'image' => 'destinations/bali.jpg',
        ]);

        $this->package = TourPackage::create([
            'destination_id' => $this->destination->id,
            'title' => 'Bali Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
            'image' => 'tour-packages/bali-trip.jpg',
        ]);
    }

    private function otherUser(): User
    {
        return User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Other Traveller',
            'email' => 'other-'.uniqid().'@example.com',
        ]);
    }

    // ---------- Toggle ----------

    public function test_toggle_adds_then_removes_a_destination(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/wishlist/toggle', [
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_wishlisted', true);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ]);

        $this->postJson('/api/wishlist/toggle', [
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_wishlisted', false);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ]);
    }

    public function test_toggle_works_for_a_tour_package(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/wishlist/toggle', [
            'item_type' => Wishlist::TYPE_TOUR_PACKAGE,
            'item_id' => $this->package->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_wishlisted', true);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_TOUR_PACKAGE,
            'item_id' => $this->package->id,
        ]);
    }

    public function test_duplicate_rows_are_rejected_by_the_unique_constraint(): void
    {
        $attributes = [
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ];

        Wishlist::create($attributes);

        $this->expectException(QueryException::class);

        Wishlist::create($attributes);
    }

    public function test_toggle_rejects_invalid_item_type_and_missing_item(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/wishlist/toggle', [
            'item_type' => 'hotel',
            'item_id' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('item_type');

        $this->postJson('/api/wishlist/toggle', [
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors('item_id');

        $this->postJson('/api/wishlist/toggle', [
            'item_type' => Wishlist::TYPE_TOUR_PACKAGE,
            'item_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors('item_id');

        $this->assertDatabaseCount('wishlists', 0);
    }

    // ---------- List / delete ownership ----------

    public function test_list_is_paginated_newest_first_with_item_summaries(): void
    {
        Sanctum::actingAs($this->user);

        Wishlist::create([
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        Wishlist::create([
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_TOUR_PACKAGE,
            'item_id' => $this->package->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/wishlist')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.item_type', Wishlist::TYPE_TOUR_PACKAGE)
            ->assertJsonPath('data.0.item.name', 'Bali Trip')
            ->assertJsonPath('data.0.item.price', 1000000)
            ->assertJsonPath('data.1.item.name', 'Bali');

        $this->assertNotNull($response->json('data.0.item.image'));
        $this->assertNotNull($response->json('data.1.item.image'));
        $this->assertSame(3, $response->json('data.0.item.duration_days'));
    }

    public function test_list_can_be_filtered_by_type(): void
    {
        Sanctum::actingAs($this->user);

        Wishlist::create([
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ]);
        Wishlist::create([
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_TOUR_PACKAGE,
            'item_id' => $this->package->id,
        ]);

        $this->getJson('/api/wishlist?type=destination')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.item_type', Wishlist::TYPE_DESTINATION);

        $this->getJson('/api/wishlist?type=tour_package')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.item_type', Wishlist::TYPE_TOUR_PACKAGE);

        $this->getJson('/api/wishlist?type=hotel')
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_a_user_cannot_see_or_delete_another_users_wishlist(): void
    {
        $other = $this->otherUser();

        $wishlist = Wishlist::create([
            'user_id' => $other->id,
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ]);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/wishlist')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');

        $this->deleteJson('/api/wishlist/'.$wishlist->id)
            ->assertNotFound();

        $this->assertDatabaseHas('wishlists', ['id' => $wishlist->id]);
    }

    public function test_owner_can_delete_a_wishlist_row(): void
    {
        Sanctum::actingAs($this->user);

        $wishlist = Wishlist::create([
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_TOUR_PACKAGE,
            'item_id' => $this->package->id,
        ]);

        $this->deleteJson('/api/wishlist/'.$wishlist->id)
            ->assertOk()
            ->assertJsonPath('data.id', $wishlist->id);

        $this->assertDatabaseMissing('wishlists', ['id' => $wishlist->id]);
    }

    public function test_wishlist_endpoints_require_authentication(): void
    {
        $this->getJson('/api/wishlist')->assertUnauthorized();
        $this->postJson('/api/wishlist/toggle', [
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ])->assertUnauthorized();
    }

    // ---------- is_wishlisted enrichment ----------

    public function test_public_endpoints_work_without_a_token_and_omit_is_wishlisted(): void
    {
        $this->getJson('/api/destinations')
            ->assertOk()
            ->assertJsonMissingPath('data.0.is_wishlisted');

        $this->getJson('/api/destinations/'.$this->destination->id)
            ->assertOk()
            ->assertJsonMissingPath('data.is_wishlisted');

        $this->getJson('/api/tour-packages')
            ->assertOk()
            ->assertJsonMissingPath('data.0.is_wishlisted');

        $this->getJson('/api/tour-packages/'.$this->package->id)
            ->assertOk()
            ->assertJsonMissingPath('data.is_wishlisted');
    }

    public function test_is_wishlisted_is_true_for_the_owner_and_false_for_another_user(): void
    {
        Wishlist::create([
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ]);
        Wishlist::create([
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_TOUR_PACKAGE,
            'item_id' => $this->package->id,
        ]);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/destinations')
            ->assertOk()
            ->assertJsonPath('data.0.is_wishlisted', true);

        $this->getJson('/api/destinations/'.$this->destination->id)
            ->assertOk()
            ->assertJsonPath('data.is_wishlisted', true);

        $this->getJson('/api/tour-packages')
            ->assertOk()
            ->assertJsonPath('data.0.is_wishlisted', true);

        $this->getJson('/api/tour-packages/'.$this->package->id)
            ->assertOk()
            ->assertJsonPath('data.is_wishlisted', true);

        Sanctum::actingAs($this->otherUser());

        $this->getJson('/api/destinations/'.$this->destination->id)
            ->assertOk()
            ->assertJsonPath('data.is_wishlisted', false);

        $this->getJson('/api/tour-packages/'.$this->package->id)
            ->assertOk()
            ->assertJsonPath('data.is_wishlisted', false);
    }

    public function test_is_wishlisted_uses_the_token_user_on_public_endpoints(): void
    {
        // No Sanctum::actingAs: this exercises the optional-auth middleware.
        $token = $this->user->createToken('wishlist-test')->plainTextToken;

        Wishlist::create([
            'user_id' => $this->user->id,
            'item_type' => Wishlist::TYPE_DESTINATION,
            'item_id' => $this->destination->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/destinations/'.$this->destination->id)
            ->assertOk()
            ->assertJsonPath('data.is_wishlisted', true);

        // Drop the guard's cached user so this simulates a fresh request with
        // an invalid bearer token (each real HTTP request has a new guard).
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/destinations/'.$this->destination->id)
            ->assertOk()
            ->assertJsonMissingPath('data.is_wishlisted');
    }
}
