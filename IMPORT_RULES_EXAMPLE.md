# Transaction Import Rules - Usage Guide

## Creating Rules

You can create import rules to automatically transform transactions during import.

### Example: Convert "JAN FORSTER CLT-TPJFE LETTINGS" to a Transfer

```php
php artisan tinker

// Find your user and accounts
$user = \App\Models\User::find(1);
$importAccount = \App\Models\AccountEntity::where('name', 'First Direct - Expenses & Rent')->first();
$janForsterAccount = \App\Models\AccountEntity::where('name', 'LIKE', '%Jan%')->first(); // Find the Jan Forster account

// Create the rule
\App\Models\TransactionImportRule::create([
    'user_id' => $user->id,
    'account_id' => $importAccount->id, // Apply only when importing to this account
    'description_pattern' => 'JAN FORSTER CLT-TPJFE LETTINGS',
    'use_regex' => false,
    'action' => 'convert_to_transfer',
    'transfer_account_id' => $janForsterAccount->id,
    'transaction_type_id' => 3, // 3 = transfer
    'priority' => 10,
    'active' => true,
]);
```

### Rule Fields

- **user_id**: Owner of the rule
- **account_id**: If set, rule only applies when importing to this specific account. NULL = applies to all accounts
- **description_pattern**: Text to match (case-insensitive) or regex pattern
- **use_regex**: false = contains match, true = regex match
- **action**: 
  - `convert_to_transfer`: Transform payee transaction into transfer between accounts
  - `skip`: Don't import this transaction at all
  - `modify`: (Future: modify category, description, etc.)
- **transfer_account_id**: For transfers, the other account involved
- **transaction_type_id**: Transaction type (1=withdrawal, 2=deposit, 3=transfer)
- **priority**: Lower number = higher priority (processed first)
- **active**: Enable/disable the rule

## How It Works

1. During import, each transaction description is checked against active rules
2. Rules for the specific account are checked first, then global rules
3. Rules are processed in priority order (lowest number first)
4. First matching rule wins
5. If no rules match, normal payee matching/creation happens

## Handling Duplicate Transfers

When you import from both sides of a transfer:
- Import to Account A: Creates transfer from B → A
- Import to Account B: Creates transfer from A → B (duplicate!)

**Solution**: Use the `skip` action on one side:

```php
// Skip JAN FORSTER deposits when importing Jan Forster's account
\App\Models\TransactionImportRule::create([
    'user_id' => $user->id,
    'account_id' => $janForsterAccount->id, // Only applies to Jan Forster account imports
    'description_pattern' => 'RENTAL PAYMENT', // Whatever appears on that side
    'use_regex' => false,
    'action' => 'skip', // Don't import from this side
    'priority' => 10,
    'active' => true,
]);
```

## Advanced: Regex Patterns

For more complex matching:

```php
\App\Models\TransactionImportRule::create([
    'user_id' => $user->id,
    'account_id' => null, // Global rule
    'description_pattern' => '/^(PAYPAL|PP\*).*/i', // Match any PayPal transaction
    'use_regex' => true,
    'action' => 'convert_to_transfer',
    'transfer_account_id' => $paypalAccount->id,
    'transaction_type_id' => 3,
    'priority' => 50,
    'active' => true,
]);
```

## Viewing Rules

```php
php artisan tinker

$user = \App\Models\User::find(1);
$rules = \App\Models\TransactionImportRule::where('user_id', $user->id)->get();

foreach ($rules as $rule) {
    echo "Rule #{$rule->id}: {$rule->description_pattern} -> {$rule->action}\n";
    if ($rule->account) {
        echo "  Account: {$rule->account->name}\n";
    }
    if ($rule->transferAccount) {
        echo "  Transfer to: {$rule->transferAccount->name}\n";
    }
}
```
