# Balance Checkpoints & Transaction Reconciliation

## Overview

The Balance Checkpoints feature provides robust financial data integrity controls by allowing you to:

1. **Set balance checkpoints**: Define known account balances at specific dates
2. **Validate transactions**: Automatically prevent transactions that would violate checkpoints
3. **Reconcile transactions**: Lock transactions to prevent accidental modifications
4. **Maintain audit trail**: Track when and by whom transactions were reconciled

This feature is particularly useful for:
- Reconciling bank statements
- Ensuring imported transactions don't corrupt known balances
- Preventing accidental modifications to historical data
- Maintaining financial accuracy for tax and compliance purposes

## Database Schema

### account_balance_checkpoints Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Foreign key to users table |
| account_entity_id | bigint | Foreign key to account_entities table |
| checkpoint_date | date | The date of the checkpoint |
| balance | decimal(15,2) | The expected balance on this date |
| note | text | Optional note explaining the checkpoint |
| active | boolean | Whether this checkpoint is active (default: true) |
| created_at | timestamp | When the checkpoint was created |
| updated_at | timestamp | When the checkpoint was last updated |

**Unique Constraint**: `(account_entity_id, checkpoint_date, active)` - Only one active checkpoint per account per date

### transactions Table (New Columns)

| Column | Type | Description |
|--------|------|-------------|
| reconciled | boolean | Whether this transaction is reconciled (default: false) |
| reconciled_at | timestamp | When the transaction was reconciled (nullable) |
| reconciled_by | bigint | Foreign key to users table - who reconciled it (nullable) |

## How It Works

### Balance Checkpoint Validation

When a transaction is created, updated, or deleted:

1. **Check if feature is enabled**: Respects `BALANCE_CHECKPOINT_ENABLED` environment variable
2. **Identify affected accounts**: Determines which accounts are impacted by the transaction
3. **Find relevant checkpoints**: Locates active checkpoints on or before the transaction date
4. **Calculate balances**: Computes the account balance at the checkpoint date
5. **Validate integrity**: 
   - If calculated balance matches checkpoint: Transaction must not break this match
   - If balances don't match: Allow transaction (might be a correction)

### Transaction Reconciliation

Reconciled transactions:
- Cannot be edited or deleted without first unreconciling
- Track who reconciled them and when
- Provide an audit trail for compliance

## Setup Instructions

### 1. Environment Configuration

Add to your `.env` file:

```env
# Balance checkpoint feature toggle
# Set to false to disable validation (emergency override only)
BALANCE_CHECKPOINT_ENABLED=true
```

### 2. Run Migrations

The migrations are automatically applied when you run:

```bash
php artisan migrate
```

This creates:
- `account_balance_checkpoints` table
- `reconciled`, `reconciled_at`, `reconciled_by` columns in `transactions` table

### 3. No Additional Setup Required

The feature is ready to use once migrations are complete.

## Usage Guide

### Creating a Balance Checkpoint

**Via API**:

```http
POST /api/balance-checkpoints
Content-Type: application/json

{
  "account_entity_id": 59,
  "checkpoint_date": "2025-12-31",
  "balance": 15432.50,
  "note": "Year-end bank statement balance"
}
```

**Programmatically**:

```php
use App\Services\BalanceCheckpointService;

$service = new BalanceCheckpointService();

$checkpoint = $service->createCheckpoint(
    userId: auth()->id(),
    accountEntityId: 59,
    date: Carbon::parse('2025-12-31'),
    balance: 15432.50,
    note: 'Year-end bank statement balance'
);
```

### Listing Checkpoints for an Account

```http
GET /api/balance-checkpoints?account_entity_id=59
```

Response:
```json
[
  {
    "id": 1,
    "user_id": 1,
    "account_entity_id": 59,
    "checkpoint_date": "2025-12-31",
    "balance": "15432.50",
    "note": "Year-end bank statement balance",
    "active": true,
    "created_at": "2026-01-18T14:30:00.000000Z",
    "updated_at": "2026-01-18T14:30:00.000000Z"
  }
]
```

### Updating a Checkpoint

```http
PUT /api/balance-checkpoints/{id}
Content-Type: application/json

{
  "balance": 15450.00,
  "note": "Corrected balance after finding missed transaction"
}
```

### Deactivating a Checkpoint

```http
DELETE /api/balance-checkpoints/{id}
```

Note: This doesn't delete the checkpoint, just sets `active = false`.

### Reconciling a Transaction

```http
POST /api/balance-checkpoints/toggle-reconciliation
Content-Type: application/json

{
  "transaction_id": 1234
}
```

This toggles the reconciliation status. Response:

```json
{
  "message": "Transaction reconciled successfully",
  "reconciled": true
}
```

To unreconcile, call the same endpoint again.

### Checking Balance Integrity

Verify if calculated balance matches checkpoint:

```http
POST /api/balance-checkpoints/check-integrity
Content-Type: application/json

{
  "account_entity_id": 59,
  "checkpoint_date": "2025-12-31"
}
```

Response:
```json
{
  "calculated_balance": 15432.50,
  "checkpoint_balance": 15432.50,
  "matches": true
}
```

## Validation Behavior

### When Creating/Editing a Transaction

The system will **reject** the transaction if:

1. **Transaction is reconciled**: You must unreconcile it first
2. **Checkpoint violation**: The transaction would cause the calculated balance to no longer match a checkpoint where balances currently match

The system will **allow** the transaction if:

1. **No checkpoints exist** for the affected accounts
2. **Checkpoints exist but balances don't match**: Assumes you're making corrections
3. **Feature is disabled**: `BALANCE_CHECKPOINT_ENABLED=false` in `.env`

### When Deleting a Transaction

Same rules as editing, but validates that the deletion won't break checkpoint integrity.

### Error Messages

When validation fails, you'll see messages like:

```
This transaction would violate the balance checkpoint on 2025-12-31. 
Expected balance: 15432.50, would be: 15500.00
```

```
This transaction is reconciled and cannot be modified. 
Please unreconcile it first.
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/balance-checkpoints` | List checkpoints (requires `account_entity_id` param) |
| POST | `/api/balance-checkpoints` | Create new checkpoint |
| GET | `/api/balance-checkpoints/{id}` | Get single checkpoint |
| PUT | `/api/balance-checkpoints/{id}` | Update checkpoint balance/note |
| DELETE | `/api/balance-checkpoints/{id}` | Deactivate checkpoint |
| POST | `/api/balance-checkpoints/toggle-reconciliation` | Toggle transaction reconciliation |
| POST | `/api/balance-checkpoints/check-integrity` | Check if balance matches checkpoint |

All endpoints require authentication and enforce user ownership.

## Service Layer Methods

### BalanceCheckpointService

```php
// Check if feature is enabled
public function isEnabled(): bool

// Validate a transaction against checkpoints
public function validateTransaction(Transaction $transaction, bool $isUpdate = false): array

// Validate a transaction deletion
public function validateDeletion(Transaction $transaction): array

// Calculate account balance at a specific date
public function calculateBalanceAtDate(int $accountId, Carbon $date, ?int $excludeTransactionId = null): float

// Create a new checkpoint
public function createCheckpoint(int $userId, int $accountEntityId, Carbon $date, float $balance, ?string $note = null): AccountBalanceCheckpoint

// Reconcile a transaction
public function reconcileTransaction(Transaction $transaction, int $userId): void

// Unreconcile a transaction
public function unreconcileTransaction(Transaction $transaction): void

// Check if transaction can be modified
public function canModifyTransaction(Transaction $transaction): array
```

## Model Scopes

### AccountBalanceCheckpoint

```php
// Get only active checkpoints
AccountBalanceCheckpoint::active()->get();

// Get checkpoints for a specific account
AccountBalanceCheckpoint::forAccount($accountId)->get();

// Get checkpoints on or before a date
AccountBalanceCheckpoint::beforeOrOn($date)->get();

// Combined example
AccountBalanceCheckpoint::active()
    ->forAccount(59)
    ->beforeOrOn(Carbon::parse('2025-12-31'))
    ->orderBy('checkpoint_date', 'desc')
    ->first();
```

### Transaction

```php
// Get reconciled transactions
Transaction::where('reconciled', true)->get();

// Get unreconciled transactions
Transaction::where('reconciled', false)->get();

// Access who reconciled it
$transaction->reconciledBy; // Returns User model
```

## Emergency Override

If you encounter a critical issue where the checkpoint validation is preventing necessary corrections:

### Temporary Disable

1. Set in `.env`:
   ```env
   BALANCE_CHECKPOINT_ENABLED=false
   ```

2. Clear config cache:
   ```bash
   php artisan config:clear
   ```

3. Make your corrections

4. Re-enable:
   ```env
   BALANCE_CHECKPOINT_ENABLED=true
   ```

5. Clear cache again:
   ```bash
   php artisan config:clear
   ```

### Permanent Disable (Not Recommended)

Remove or comment out the checkpoint validation in:
- `app/Observers/TransactionObserver.php` (creating, updating, deleting methods)

However, this defeats the purpose of the feature and should only be done in extreme circumstances.

## Best Practices

### 1. Set Checkpoints Regularly

- After reconciling monthly bank statements
- At year-end for tax purposes
- Before bulk import operations
- After major data corrections

### 2. Use Descriptive Notes

```php
$service->createCheckpoint(
    userId: auth()->id(),
    accountEntityId: $accountId,
    date: Carbon::parse('2025-12-31'),
    balance: 15432.50,
    note: 'HSBC statement #4567, confirmed balance matches account summary'
);
```

### 3. Reconcile Transactions After Import

Once you've verified an imported batch:

```php
foreach ($verifiedTransactions as $transaction) {
    $service->reconcileTransaction($transaction, auth()->id());
}
```

### 4. Check Integrity Before Large Operations

Before bulk deletes or updates:

```http
POST /api/balance-checkpoints/check-integrity
```

Ensure balances match before proceeding.

### 5. Don't Rely Solely on Checkpoints

- Use reconciliation for verified transactions
- Keep regular backups
- Maintain audit logs
- Use version control for configuration changes

## Troubleshooting

### Problem: "Transaction would violate checkpoint" but I need to add a correction

**Solution**: This is expected behavior. If the checkpoint balance currently matches the calculated balance, any change that would break this match is rejected. You have two options:

1. **Update the checkpoint balance** to the expected final value first
2. **Temporarily disable** the feature, make corrections, verify balances, then re-enable

### Problem: Can't delete an old transaction

**Check**:
1. Is it reconciled? Unreconcile it first
2. Would deletion break a checkpoint? Update checkpoint or disable feature temporarily

### Problem: Checkpoint validation seems to be ignored

**Check**:
1. Is `BALANCE_CHECKPOINT_ENABLED=true` in `.env`?
2. Run `php artisan config:clear` to clear cached config
3. Verify the checkpoint is `active = true` in the database
4. Check if there's actually a checkpoint on or before the transaction date

### Problem: Balance doesn't match checkpoint but system allows transaction

**This is intentional**. When balances don't match, the system assumes you're making corrections and allows the transaction. Once balances match, it will start enforcing integrity.

### Problem: Getting validation errors in Observer

If you see exceptions during transaction save:

```
Illuminate\Validation\ValidationException: This transaction is reconciled and cannot be modified.
```

This is working as intended. Unreconcile the transaction first:

```php
$service->unreconcileTransaction($transaction);
```

## Testing

### Unit Tests

Located in `tests/Unit/Services/BalanceCheckpointServiceTest.php`:

```bash
php artisan test --filter=BalanceCheckpointServiceTest
```

### Feature Tests

Located in `tests/Feature/BalanceCheckpointTest.php`:

```bash
php artisan test --filter=BalanceCheckpointTest
```

### Manual Testing Workflow

1. Create a test account with opening balance
2. Add some transactions
3. Note the current balance
4. Create a checkpoint at today's date with the current balance
5. Try to add a transaction dated yesterday - should be rejected
6. Try to add a transaction dated tomorrow - should succeed
7. Try to edit a reconciled transaction - should be rejected
8. Unreconcile and try again - should succeed

## Architecture Notes

### Why Observer Pattern?

The validation is implemented in `TransactionObserver` because:
- It runs automatically on every transaction save/delete
- It can prevent operations by returning `false` or throwing exceptions
- It's centralized - all transaction modifications go through it
- It respects Eloquent's event system

### Why Check Calculated Balance?

The system calculates balance at checkpoint date by:
1. Starting with account opening balance
2. Adding all transaction amounts up to checkpoint date
3. Comparing with checkpoint expected balance

This allows you to:
- Detect data corruption
- Identify missing transactions
- Verify import accuracy

### Performance Considerations

- Validation runs on every transaction save/delete
- For large transaction volumes, consider:
  - Using fewer checkpoints
  - Placing checkpoints strategically (month/year end)
  - Temporarily disabling during bulk imports, then validating after
- Balance calculations are optimized but still query the database

### Future Enhancements

Potential improvements:
- Batch reconciliation API
- Automatic checkpoint creation after imports
- UI components for checkpoint management
- Export reconciliation report
- Integration with bank statement imports

## Support

For issues or questions:
1. Check this documentation first
2. Review `app/Services/BalanceCheckpointService.php` for implementation details
3. Check `app/Observers/TransactionObserver.php` for validation logic
4. Search issues in the project repository
5. Create a new issue with detailed reproduction steps

## License

This feature is part of YAFFA and follows the same license as the main application.
