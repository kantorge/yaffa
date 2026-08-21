<?php

namespace Tests\Feature\API;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class TagApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_active_status_of_a_tag(): void
    {
        // Create a user and a tag
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Tag $tag */
        $tag = Tag::factory()
            ->for($user)
            ->create([
                'active' => false,
            ]);

        $this->actingAs($user);
        $response = $this->patchJson(route('api.v1.tags.patch-active', [
            'tag' => $tag->id,
        ]), [
            'active' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertTrue($tag->fresh()->active);
    }

    public function test_it_does_not_update_a_tag_for_an_unauthenticated_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Tag $tag */
        $tag = Tag::factory()
            ->for($user)
            ->create([
                'active' => false,
            ]);

        $response = $this->patchJson(
            route('api.v1.tags.patch-active', [
                'tag' => $tag->id,
            ]),
            ['active' => true],
            [
                'Accept' => 'application/json'
            ]
        );

        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(Response::HTTP_UNAUTHORIZED),
                $this->equalTo(Response::HTTP_FORBIDDEN)
            )
        );

        $this->assertFalse($tag->fresh()->active);
    }

    public function test_it_throws_an_authorization_exception_if_user_is_not_the_owner_of_a_tag(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Tag $tag */
        $tag = Tag::factory()
            ->for($user)
            ->create([
                'active' => false,
            ]);

        /** @var User $user2 */
        $user2 = User::factory()->create();

        $this->actingAs($user2);
        $response = $this->patchJson(route('api.v1.tags.patch-active', [
            'tag' => $tag->id,
        ]), [
            'active' => true,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertFalse($tag->fresh()->active);
    }

    public function test_it_deletes_a_tag(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Tag $tag */
        $tag = Tag::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('api.v1.tags.destroy', ['tag' => $tag->id]));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_it_does_not_delete_another_users_tag(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();

        /** @var Tag $tag */
        $tag = Tag::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('api.v1.tags.destroy', ['tag' => $tag->id]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }

    public function test_it_deletes_a_tag_even_if_it_is_used_in_a_transaction(): void
    {
        // Create a user and a category
        /** @var User $user */
        $user = User::factory()->create();

        $categoryParent = Category::factory()
            ->for($user)
            ->create();
        $categoryChild = Category::factory()
            ->for($user)
            ->create([
                'parent_id' => $categoryParent->id,
            ]);

        // Create a transaction for this category, which also needs other models:
        // account group, currency, account, payee
        AccountGroup::factory()
            ->for($user)
            ->create();

        Currency::factory()
            ->for($user)
            ->create();

        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        // Create a standard transaction with specific data
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal($user)
            ->create();

        /** @var TransactionItem $transactionItem */
        $transactionItem = TransactionItem::factory()->create([
            'transaction_id' => $transaction->id,
            'category_id' => $categoryChild->id,
        ]);

        // Create the tag to be tested
        $tag = Tag::factory()
            ->for($user)
            ->create();

        // Attach the tag to the transaction item
        $transactionItem->tags()->attach($tag->id);

        // Delete the tag, even though it is attached to a transaction item
        $response = $this->actingAs($user)
            ->deleteJson(route('api.v1.tags.destroy', ['tag' => $tag->id]));

        $response->assertStatus(Response::HTTP_OK);

        // Check that the tag was deleted, and the pivot row cascaded with it
        $this->assertNull($tag->fresh());
        $this->assertDatabaseMissing('transaction_items_tags', [
            'tag_id' => $tag->id,
        ]);
    }
}
