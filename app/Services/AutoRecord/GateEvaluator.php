<?php

namespace App\Services\AutoRecord;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\Payee;
use App\Models\PayeeProfile;
use App\Models\TransactionTemplate;
use App\Enums\SameEventOutcome;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
use App\Services\AssetMatchingService;
use App\Services\PayeeProfileService;
use App\Services\SameEventClassifier;
use App\Services\TransactionDraftService;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Decides whether a processed document may be recorded without a person looking at it. Gates run in a fixed
 * order and evaluation stops at the first failure; each failure carries a reason the document viewer shows.
 * See the fast transaction entry specification, Phase 4.
 */
class GateEvaluator
{
    public const string GATE_SWITCH = 'switch';

    public const string GATE_EXTRACTION = 'extraction';

    public const string GATE_PAYEE = 'payee';

    public const string GATE_POLICY = 'policy';

    public const string GATE_HISTORY = 'history';

    public const string GATE_ACCOUNT = 'account';

    public const string GATE_COMPLETENESS = 'completeness';

    public const string GATE_AMOUNT = 'amount';

    public const string GATE_DUPLICATES = 'duplicates';

    public function __construct(
        private readonly AiUserSettingsResolver $settingsResolver,
        private readonly TransactionDraftService $draftService,
        private readonly PayeeProfileService $profileService,
        private readonly SameEventClassifier $classifier,
    ) {
    }

    public function evaluate(AiDocument $document): GateResult
    {
        $user = $document->user;
        $settings = $this->settingsResolver->resolveForUser($user);

        if (! $settings['ai_enabled'] || ! $settings['auto_record_enabled']) {
            return GateResult::fail(self::GATE_SWITCH, __('Automatic recording is turned off.'));
        }

        $extracted = $this->extraction($document);
        if ($extracted instanceof GateResult) {
            return $extracted;
        }
        ['draft' => $draft, 'amount' => $amount, 'payeeText' => $payeeText] = $extracted;

        $matching = new AssetMatchingService($user);

        $payee = $this->identifiedPayee($draft, $payeeText, $user, $matching);
        if ($payee instanceof GateResult) {
            return $payee;
        }

        /** @var Payee $payeeConfig */
        $payeeConfig = $payee->config;

        if ($payeeConfig->auto_record_policy === Payee::POLICY_NEVER) {
            return GateResult::fail(self::GATE_POLICY, __('Policy: automatic recording is set to never for :payee.', ['payee' => $payee->name]));
        }

        $template = $this->singleTemplate($user, $payee, $draft['transaction_type']);
        $profile = PayeeProfile::query()->where('account_entity_id', $payee->id)->first() ?? $this->profileService->calculate($payee);
        $qualification = $this->profileService->qualification($profile, $payeeConfig->auto_record_policy, $settings);
        if (! $qualification['qualifies']) {
            return GateResult::fail(self::GATE_HISTORY, __('History: :reason', ['reason' => $qualification['reason']]));
        }
        $reasons = [$qualification['reason']];

        $account = $this->identifiedAccount($user, $draft, $template, $matching);
        if ($account === null) {
            return GateResult::fail(self::GATE_ACCOUNT, __('Account: no account could be identified for certain (no card ending or account name matched, and the payee has no single template with an account).'));
        }
        [$accountId, $accountReason] = $account;
        $reasons[] = $accountReason;

        if ($payeeConfig->itemization_expected && ! $document->isItemized()) {
            return GateResult::fail(self::GATE_COMPLETENESS, __('Waiting for the itemized receipt.'), hold: true);
        }

        if ($document->isItemized()) {
            $itemsFailure = $this->itemsFailure($draft, $amount);
            if ($itemsFailure !== null) {
                return GateResult::fail(self::GATE_EXTRACTION, $itemsFailure);
            }
        }

        $items = $document->isItemized()
            ? $this->itemizedItems($draft)
            : $this->summaryItems($user, $draft, $amount, $profile, $template);
        if ($items === null) {
            return GateResult::fail(self::GATE_HISTORY, __('History: there is no usual category to record this under.'));
        }

        $amountFailure = $this->amountFailure($amount, $profile, $settings, $draft, $accountId);
        if ($amountFailure !== null) {
            return GateResult::fail(self::GATE_AMOUNT, $amountFailure[0], context: $amountFailure[1]);
        }
        $reasons[] = __('Amount within the usual range.');

        // The caller may have classified before, but the lock-protected re-check is what makes this race safe
        // against the payee and account the gates resolved, which the draft itself may not carry
        $key = $this->classifier->keyFromEntry($draft['transaction_type'], $accountId, $payee->id, $draft['date'], (string) $amount);
        if ($this->classifier->classify($document, $key)->outcome !== SameEventOutcome::None) {
            return GateResult::fail(self::GATE_DUPLICATES, __('Duplicates: a similar purchase or document already exists.'));
        }

        return new GateResult(true, reasons: $reasons, draft: $draft, payee: $payee, accountId: $accountId, profile: $profile, template: $template, items: $items);
    }

    /**
     * Gate 1. Returns the normalized draft with its parsed amount and the payee text, or the failure.
     *
     * @return GateResult|array{draft: array<string, mixed>, amount: BigDecimal, payeeText: string}
     */
    private function extraction(AiDocument $document): GateResult|array
    {
        if (! is_array($document->processed_transaction_data)) {
            return GateResult::fail(self::GATE_EXTRACTION, __('Extraction: the document has no extracted data.'));
        }

        try {
            $draft = $this->draftService->normalize($document->processed_transaction_data, $document->user)['draft'];
        } catch (ValidationException) {
            return GateResult::fail(self::GATE_EXTRACTION, __('Extraction: the extracted data is invalid.'));
        }

        if (($draft['config_type'] ?? null) !== 'standard' || ! in_array($draft['transaction_type'] ?? null, ['withdrawal', 'deposit'], true)) {
            return GateResult::fail(self::GATE_EXTRACTION, __('Extraction: only withdrawals and deposits are recorded automatically.'));
        }

        $payeeText = $draft['raw']['payee'] ?? null;
        $date = $draft['date'] ?? null;
        $rawAmount = $draft['config']['amount_from'] ?? null;

        if (! is_string($payeeText) || mb_trim($payeeText) === '' || ! is_string($date) || $rawAmount === null) {
            return GateResult::fail(self::GATE_EXTRACTION, __('Extraction: the date, amount or payee is missing.'));
        }

        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $date);
            $amount = BigDecimal::of((string) $rawAmount);
        } catch (Throwable) {
            return GateResult::fail(self::GATE_EXTRACTION, __('Extraction: the date or amount could not be read.'));
        }

        if (! $parsedDate || $parsedDate->startOfDay()->isFuture()) {
            return GateResult::fail(self::GATE_EXTRACTION, __('Extraction: the date is missing or in the future.'));
        }

        if (! $amount->isGreaterThan(0)) {
            return GateResult::fail(self::GATE_EXTRACTION, __('Extraction: the amount is not positive.'));
        }

        return ['draft' => $draft, 'amount' => $amount, 'payeeText' => mb_trim($payeeText)];
    }

    /**
     * The payee's only template of this transaction type, if there is exactly one.
     */
    private function singleTemplate(User $user, AccountEntity $payee, string $transactionType): ?TransactionTemplate
    {
        $templates = $user->transactionTemplates()
            ->where('payee_id', $payee->id)
            ->get()
            ->filter(fn (TransactionTemplate $template) => ($template->draft['transaction_type'] ?? null) === $transactionType);

        return $templates->count() === 1 ? $templates->first() : null;
    }

    /**
     * Gate 2: the payee the draft carries, when the document processing matched it for certain. The draft's
     * payee came out of the same matching the gate repeats here, so this only confirms that the match was good
     * enough to act on (a payee the AI picked among uncertain candidates is left to a person).
     */
    private function identifiedPayee(array $draft, string $payeeText, User $user, AssetMatchingService $matching): AccountEntity|GateResult
    {
        $payeeId = $this->draftService->payeeIdFromDraft($draft, $user);

        if ($payeeId === null) {
            return GateResult::fail(self::GATE_PAYEE, __('Payee: no payee matched ":text".', ['text' => $payeeText]));
        }

        $candidates = $matching->matchPayees($payeeText);
        $certain = $matching->certainMatch($candidates);

        if ($certain === null || $certain['id'] !== $payeeId) {
            $payee = AccountEntity::query()->findOrFail($payeeId);
            $score = collect($candidates)->firstWhere('id', $payeeId)['similarity'] ?? null;

            return GateResult::fail(self::GATE_PAYEE, $score === null
                ? __('Payee: ":text" is not a certain match for :payee.', ['text' => $payeeText, 'payee' => $payee->name])
                : __('Payee: ":text" only resembles :payee (score :score), which is not certain enough.', [
                    'text' => $payeeText,
                    'payee' => $payee->name,
                    'score' => number_format($score, 2),
                ]));
        }

        return AccountEntity::query()->with('config')->findOrFail($payeeId);
    }

    /**
     * Gate 5: the account of the purchase. The draft's account when it is a certain match for the account text
     * or card ending of the document (the same matching as for the draft); otherwise the account of the
     * payee's single template.
     *
     * @return array{0: int, 1: string}|null
     */
    private function identifiedAccount(User $user, array $draft, ?TransactionTemplate $template, AssetMatchingService $matching): ?array
    {
        $key = $draft['transaction_type'] === 'withdrawal' ? 'account_from_id' : 'account_to_id';
        $draftAccountId = $draft['config'][$key] ?? null;

        if ($draftAccountId !== null) {
            $digits = AssetMatchingService::cardDigits($draft['raw']['card_last_digits'] ?? null, $draft['raw']['account'] ?? null);
            $certain = $matching->certainMatch($matching->matchAccounts((string) ($draft['raw']['account'] ?? ''), $digits));
            $account = $user->accounts()->active()->find($draftAccountId);

            if ($account !== null && $certain !== null && $certain['id'] === (int) $draftAccountId) {
                return [$account->id, $digits !== null
                    ? __('account :name from card ending :digits', ['name' => $account->name, 'digits' => $digits])
                    : __('account :name', ['name' => $account->name])];
            }
        }

        if ($template !== null) {
            $accountId = $this->draftService->normalize($template->draft, $user)['draft']['config'][$key] ?? null;

            if ($accountId !== null) {
                return [(int) $accountId, __('account from the template ":name"', ['name' => $template->name])];
            }
        }

        return null;
    }

    /**
     * An itemized document is recorded with its own items, so every item needs an exact category and the
     * items must add up to the total.
     */
    private function itemsFailure(array $draft, BigDecimal $amount): ?string
    {
        $items = $draft['transaction_items'] ?? [];
        $sum = BigDecimal::zero();

        foreach ($items as $item) {
            if (empty($item['recommended_category_id']) || ($item['match_type'] ?? null) !== 'exact' || ! isset($item['amount'])) {
                return __('Extraction: uncategorized items.');
            }
            $sum = $sum->plus($item['amount']);
        }

        return $items === [] || ! $sum->isEqualTo($amount)
            ? __('Extraction: the items do not add up to the total.')
            : null;
    }

    /**
     * The document's own items, each in its exact category (checked by itemsFailure()).
     *
     * @return list<array{amount: string, category_id: int, comment: ?string}>
     */
    private function itemizedItems(array $draft): array
    {
        return array_map(fn (array $item) => [
            'amount' => (string) BigDecimal::of($item['amount']),
            'category_id' => (int) $item['recommended_category_id'],
            'comment' => $item['description'] ?? null,
        ], $draft['transaction_items']);
    }

    /**
     * One item of the full amount: in the category of the payee's single template when it has one, else in
     * the payee's dominant category. Null when neither is an active category of the user.
     *
     * @return list<array{amount: string, category_id: int, comment: ?string, tags: list<int>}>|null
     */
    private function summaryItems(User $user, array $draft, BigDecimal $amount, PayeeProfile $profile, ?TransactionTemplate $template): ?array
    {
        $templateItem = $template === null ? null : collect($this->draftService->normalize($template->draft, $user)['draft']['transaction_items'] ?? [])
            ->first(fn (array $item) => ! empty($item['category_id']));

        $categoryId = $templateItem['category_id'] ?? $profile->dominant_category_id;

        if ($categoryId === null || ! $user->categories()->where('active', true)->whereKey($categoryId)->exists()) {
            return null;
        }

        return [[
            'amount' => (string) $amount,
            'category_id' => (int) $categoryId,
            'comment' => $templateItem['comment'] ?? null,
            'tags' => array_map('intval', $templateItem['tag_ids'] ?? []),
        ]];
    }

    /**
     * Gate 7: within the tolerance of the usual amount, or exactly one of the known amounts. Amounts are
     * compared in the account currency; a document in another currency goes to review.
     *
     * @param  array<string, mixed>  $settings
     */
    private function amountFailure(BigDecimal $amount, PayeeProfile $profile, array $settings, array $draft, int $accountId): ?array
    {
        $currency = $draft['raw']['currency'] ?? null;
        if (is_string($currency) && $currency !== '') {
            /** @var Account|null $accountConfig */
            $accountConfig = AccountEntity::query()->with('config.currency')->find($accountId)?->config;
            $accountCurrency = $accountConfig?->currency->iso_code;
            if ($accountCurrency !== null && strcasecmp($accountCurrency, $currency) !== 0) {
                return [__('Amount: the document is in :document, the account in :account.', ['document' => $currency, 'account' => $accountCurrency]), []];
            }
        }

        if (collect($profile->known_amounts)->contains(fn (string $known) => BigDecimal::of($known)->isEqualTo($amount))) {
            return null;
        }

        if ($profile->amount_median === null) {
            return [__('Amount: there is no usual amount to compare with.'), []];
        }

        $median = BigDecimal::of($profile->amount_median);
        $tolerance = BigDecimal::of((string) $settings['auto_record_amount_tolerance_percent']);

        // |amount - median| * 100 <= median * tolerance
        if ($amount->minus($median)->abs()->multipliedBy(100)->isLessThanOrEqualTo($median->multipliedBy($tolerance))) {
            return null;
        }

        return [
            __('Amount: :amount is outside the usual range (around :median).', ['amount' => (string) $amount, 'median' => (string) $median]),
            ['amount' => (string) $amount, 'median' => (string) $median],
        ];
    }
}
