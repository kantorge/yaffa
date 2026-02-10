<?php

/**
 * Simple verification script to test TransactionType enum functionality
 * This can be run without database connection
 */

require __DIR__ . '/vendor/autoload.php';

use App\Enums\TransactionType;

echo "=== Transaction Type Enum Verification ===\n\n";

// Test 1: All enum cases exist
echo "1. Testing enum cases...\n";
$cases = TransactionType::cases();
$expectedCount = 11; // 9 active + 2 unused
$actualCount = count($cases);

if ($actualCount === $expectedCount) {
    echo "   ✓ All {$expectedCount} enum cases present\n";
} else {
    echo "   ✗ FAIL: Expected {$expectedCount} cases, found {$actualCount}\n";
    exit(1);
}

// Test 2: Check specific enum values
echo "\n2. Testing enum values...\n";
$expectedValues = [
    'withdrawal', 'deposit', 'transfer',
    'buy', 'sell', 'add_shares', 'remove_shares',
    'dividend', 'unused_1', 'unused_2', 'interest_yield'
];

foreach ($expectedValues as $value) {
    $enum = TransactionType::tryFrom($value);
    if ($enum) {
        echo "   ✓ {$value}: " . $enum->label() . "\n";
    } else {
        echo "   ✗ FAIL: Value '{$value}' not found\n";
        exit(1);
    }
}

// Test 3: Test multipliers
echo "\n3. Testing multipliers...\n";
$multiplierTests = [
    ['type' => TransactionType::WITHDRAWAL, 'amount' => -1, 'quantity' => null],
    ['type' => TransactionType::DEPOSIT, 'amount' => 1, 'quantity' => null],
    ['type' => TransactionType::TRANSFER, 'amount' => null, 'quantity' => null],
    ['type' => TransactionType::BUY, 'amount' => -1, 'quantity' => 1],
    ['type' => TransactionType::SELL, 'amount' => 1, 'quantity' => -1],
    ['type' => TransactionType::ADD_SHARES, 'amount' => null, 'quantity' => 1],
    ['type' => TransactionType::REMOVE_SHARES, 'amount' => null, 'quantity' => -1],
    ['type' => TransactionType::DIVIDEND, 'amount' => 1, 'quantity' => null],
    ['type' => TransactionType::INTEREST_YIELD, 'amount' => 1, 'quantity' => null],
    ['type' => TransactionType::UNUSED_1, 'amount' => null, 'quantity' => null],
    ['type' => TransactionType::UNUSED_2, 'amount' => null, 'quantity' => null],
];

foreach ($multiplierTests as $test) {
    $type = $test['type'];
    $expectedAmount = $test['amount'];
    $expectedQuantity = $test['quantity'];
    
    $actualAmount = $type->amountMultiplier();
    $actualQuantity = $type->quantityMultiplier();
    
    if ($actualAmount === $expectedAmount && $actualQuantity === $expectedQuantity) {
        echo "   ✓ {$type->value}: amount={$actualAmount}, quantity={$actualQuantity}\n";
    } else {
        echo "   ✗ FAIL: {$type->value} - Expected amount={$expectedAmount}, quantity={$expectedQuantity}, ";
        echo "Got amount={$actualAmount}, quantity={$actualQuantity}\n";
        exit(1);
    }
}

// Test 4: Test categories
echo "\n4. Testing categories...\n";
$categoryTests = [
    ['type' => TransactionType::WITHDRAWAL, 'expected' => 'standard'],
    ['type' => TransactionType::DEPOSIT, 'expected' => 'standard'],
    ['type' => TransactionType::TRANSFER, 'expected' => 'standard'],
    ['type' => TransactionType::BUY, 'expected' => 'investment'],
    ['type' => TransactionType::SELL, 'expected' => 'investment'],
    ['type' => TransactionType::DIVIDEND, 'expected' => 'investment'],
    ['type' => TransactionType::UNUSED_1, 'expected' => 'unused'],
    ['type' => TransactionType::UNUSED_2, 'expected' => 'unused'],
];

foreach ($categoryTests as $test) {
    $type = $test['type'];
    $expected = $test['expected'];
    $actual = $type->category();
    
    if ($actual === $expected) {
        echo "   ✓ {$type->value}: {$actual}\n";
    } else {
        echo "   ✗ FAIL: {$type->value} - Expected '{$expected}', got '{$actual}'\n";
        exit(1);
    }
}

// Test 5: Test helper methods
echo "\n5. Testing helper methods...\n";
$standardTypes = TransactionType::standardTypes();
if (count($standardTypes) === 3) {
    echo "   ✓ standardTypes() returns 3 types\n";
} else {
    echo "   ✗ FAIL: standardTypes() - Expected 3, got " . count($standardTypes) . "\n";
    exit(1);
}

$investmentTypes = TransactionType::investmentTypes();
if (count($investmentTypes) === 6) {
    echo "   ✓ investmentTypes() returns 6 types\n";
} else {
    echo "   ✗ FAIL: investmentTypes() - Expected 6, got " . count($investmentTypes) . "\n";
    exit(1);
}

// Test 6: Test legacy mapping
echo "\n6. Testing legacy ID mapping...\n";
$legacyTests = [
    ['id' => 1, 'expected' => TransactionType::WITHDRAWAL],
    ['id' => 2, 'expected' => TransactionType::DEPOSIT],
    ['id' => 3, 'expected' => TransactionType::TRANSFER],
    ['id' => 4, 'expected' => TransactionType::BUY],
    ['id' => 5, 'expected' => TransactionType::SELL],
    ['id' => 6, 'expected' => TransactionType::ADD_SHARES],
    ['id' => 7, 'expected' => TransactionType::REMOVE_SHARES],
    ['id' => 8, 'expected' => TransactionType::DIVIDEND],
    ['id' => 9, 'expected' => TransactionType::UNUSED_1],
    ['id' => 10, 'expected' => TransactionType::UNUSED_2],
    ['id' => 11, 'expected' => TransactionType::INTEREST_YIELD],
];

foreach ($legacyTests as $test) {
    $id = $test['id'];
    $expected = $test['expected'];
    $actual = TransactionType::fromLegacyId($id);
    
    if ($actual === $expected) {
        echo "   ✓ ID {$id} -> {$actual->value}\n";
    } else {
        $actualValue = $actual ? $actual->value : 'null';
        echo "   ✗ FAIL: ID {$id} - Expected '{$expected->value}', got '{$actualValue}'\n";
        exit(1);
    }
}

// Test 7: Test toArray method
echo "\n7. Testing toArray method...\n";
$array = TransactionType::WITHDRAWAL->toArray();
$requiredKeys = ['value', 'label', 'amount_multiplier', 'quantity_multiplier', 'category'];
$hasAllKeys = true;

foreach ($requiredKeys as $key) {
    if (!array_key_exists($key, $array)) {
        echo "   ✗ FAIL: Missing key '{$key}' in toArray() output\n";
        $hasAllKeys = false;
    }
}

if ($hasAllKeys) {
    echo "   ✓ toArray() returns all required keys\n";
    echo "     Example: " . json_encode($array) . "\n";
}

// Test 8: Test all() method for JavaScript export
echo "\n8. Testing all() method...\n";
$allTypes = TransactionType::all();
if (is_array($allTypes) && count($allTypes) === 11) {
    echo "   ✓ all() returns array with 11 types\n";
    echo "     Sample keys: " . implode(', ', array_slice(array_keys($allTypes), 0, 3)) . "...\n";
} else {
    echo "   ✗ FAIL: all() - Expected array with 11 items\n";
    exit(1);
}

echo "\n=== All Tests Passed! ✓ ===\n";
echo "\nThe TransactionType enum is correctly configured and ready for use.\n";
echo "Next steps: Run full test suite in Sail environment with 'sail test'\n";
