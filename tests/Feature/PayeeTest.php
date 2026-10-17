<?php

namespace Tests\Feature;

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\Feature\Concerns\AuthorizesResourceCrud;
use Tests\TestCase;

class PayeeTest extends TestCase
{
    use AuthorizesResourceCrud;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('account-entity');
        $this->setBaseModel(AccountEntity::class);
    }

    protected function resourceAuthCollectionRouteParams(): array
    {
        return ['type' => 'payee'];
    }

    protected function resourceAuthMemberRouteParams(mixed $resource): array
    {
        return ['account_entity' => $resource->id];
    }

    protected function resourceAuthSupportsDestroy(): bool
    {
        // account-entity.destroy does not exist - payees are only removed via the merge flow.
        return false;
    }

    protected function createResourceForAuthTest(User $user): AccountEntity
    {
        return AccountEntity::factory()->asPayee($user)->create();
    }

    public function test_user_can_view_list_of_payees(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        AccountEntity::factory()->asPayee($user)->count(5)
            ->create();

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index", ['type' => 'payee']));

        $response->assertStatus(200);
        $response->assertViewIs('payees.index');
    }

    public function test_user_cannot_open_merge_form_for_other_users_payee(): void
    {
        $sourceOwner = User::factory()->create();
        $payee = AccountEntity::factory()->asPayee($sourceOwner)->create();

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('payees.merge.form', ['payeeSource' => $payee->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_cannot_merge_other_users_payee(): void
    {
        $sourceOwner = User::factory()->create();
        $targetOwner = User::factory()->create();

        $foreignPayee = AccountEntity::factory()->asPayee($sourceOwner)->create();

        $ownPayee = AccountEntity::factory()->asPayee($targetOwner)->create();

        $response = $this->actingAs($targetOwner)
            ->postJson(route('payees.merge.submit'), [
                'payee_source' => $foreignPayee->id,
                'payee_target' => $ownPayee->id,
                'action' => 'close',
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['payee_source']);
    }
}
