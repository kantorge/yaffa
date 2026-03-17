<?php

namespace App\Services;

use App\Http\Requests\AccountEntityRequest;
use App\Models\AccountEntity;
use App\Models\Payee;
use Illuminate\Support\Arr;

class PayeePersistenceService
{
    /**
     * Create a new payee account entity from the given request.
     */
    public function store(AccountEntityRequest $request): AccountEntity
    {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;

        $accountEntity = new AccountEntity($validated);

        $payeeConfig = Payee::create(Arr::only((array) data_get($validated, 'config', []), ['category_id']));
        $accountEntity->config()->associate($payeeConfig);

        $accountEntity->push();

        $this->syncCategoryPreferences($accountEntity, (array) data_get($validated, 'config', []));

        return $accountEntity;
    }

    /**
     * Update an existing payee account entity from the given request.
     *
     * @param bool $skipPreferences When true, existing category preferences are left untouched.
     *                              Pass true for simplified form submissions that do not include preference fields.
     */
    public function update(AccountEntity $accountEntity, AccountEntityRequest $request, bool $skipPreferences = false): AccountEntity
    {
        $validated = $request->validated();
        $config = (array) data_get($validated, 'config', []);

        $accountEntity->load(['config']);
        $accountEntity->fill($validated);

        if ($accountEntity->config instanceof Payee) {
            $accountEntity->config->fill(Arr::only($config, ['category_id']));
        }

        $accountEntity->push();

        if (! $skipPreferences) {
            $this->syncCategoryPreferences($accountEntity, $config);
        }

        return $accountEntity;
    }

    /**
     * @param array{preferred?: array<int, int|string>|null, not_preferred?: array<int, int|string>|null} $config
     */
    private function syncCategoryPreferences(AccountEntity $accountEntity, array $config): void
    {
        $preferences = [];

        foreach ((array) data_get($config, 'preferred', []) as $categoryId) {
            $preferences[(int) $categoryId] = ['preferred' => true];
        }

        foreach ((array) data_get($config, 'not_preferred', []) as $categoryId) {
            $preferences[(int) $categoryId] = ['preferred' => false];
        }

        $accountEntity->categoryPreference()->sync($preferences);
    }
}
