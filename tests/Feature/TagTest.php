<?php

namespace Tests\Feature;

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
use Tests\Feature\Concerns\AuthorizesResourceCrud;
use Tests\Feature\Concerns\AuthorizesResourceCrudForUnverifiedUsers;
use Tests\TestCase;

class TagTest extends TestCase
{
    use AuthorizesResourceCrud;
    use AuthorizesResourceCrudForUnverifiedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('tags');
        $this->setBaseModel(Tag::class);
    }

    public function test_user_can_view_list_of_tags(): void
    {
        $user = User::factory()->create();
        $this->createForUser($user, $this->base_model, [], 5);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs('tags.index');
    }

    public function test_user_can_access_create_form(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('tags.form');
    }

    public function test_user_cannot_create_a_tag_with_missing_data(): void
    {
        $user = User::factory()->create();
        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store"),
                [
                    'name' => '',
                ]
            );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_create_a_tag(): void
    {
        $user = User::factory()->create();
        $this->assertCreateForUser($user);
    }

    public function test_user_can_edit_an_existing_tag(): void
    {
        $user = User::factory()->create();
        $tag = $this->createForUser($user, $this->base_model);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.edit", $tag));

        $response->assertStatus(200);
        $response->assertViewIs('tags.form');
    }

    public function test_user_cannot_update_a_tag_with_missing_data(): void
    {
        $user = User::factory()->create();
        $tag = $this->createForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $tag),
                [
                    'id' => $tag->id,
                    'name' => '',
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_a_tag_with_proper_data(): void
    {
        $user = User::factory()->create();
        $tag = $this->createForUser($user, $this->base_model);
        $tag2 = $this->rawForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $tag),
                [
                    'id' => $tag->id,
                    'name' => $tag2['name'],
                ]
            );

        $response->assertRedirect(route("{$this->base_route}.index"));
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }

    public function test_user_can_delete_an_existing_tag(): void
    {
        $user = User::factory()->create();
        $this->assertDestroyWithUser($user);
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
            ->delete(route("{$this->base_route}.destroy", ['tag' => $tag->id]));

        $response->assertStatus(Response::HTTP_FOUND);

        // Check that the tag was deleted, and the pivot row cascaded with it
        $this->assertNull($tag->fresh());
        $this->assertDatabaseMissing('transaction_items_tags', [
            'tag_id' => $tag->id,
        ]);
    }
}
