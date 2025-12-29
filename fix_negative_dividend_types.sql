-- SQL to remap negative Dividend (type 8) transactions to:
-- - Type 12 (Purchased Interest) if in a WiseAlpha account
-- - Type 14 (Product Fee) if not in a WiseAlpha account

-- First, let's see what we're dealing with
SELECT 
    t.id,
    t.date,
    t.transaction_type_id,
    t.cashflow_value,
    ae.name as account_name,
    CASE 
        WHEN ae.name LIKE '%WiseAlpha%' THEN 12
        ELSE 14
    END as new_type_id
FROM transactions t
INNER JOIN transaction_detail_investments tdi ON t.config_id = tdi.id AND t.config_type = 'investment'
INNER JOIN account_entities ae ON tdi.account_id = ae.id
WHERE t.transaction_type_id = 8
AND t.cashflow_value < 0
ORDER BY t.date DESC;

-- Update WiseAlpha accounts: Type 8 → Type 12 (Purchased Interest)
UPDATE transactions t
INNER JOIN transaction_detail_investments tdi ON t.config_id = tdi.id AND t.config_type = 'investment'
INNER JOIN account_entities ae ON tdi.account_id = ae.id
SET t.transaction_type_id = 12
WHERE t.transaction_type_id = 8
AND t.cashflow_value < 0
AND ae.name LIKE '%WiseAlpha%';

-- Update non-WiseAlpha accounts: Type 8 → Type 14 (Product Fee)
UPDATE transactions t
INNER JOIN transaction_detail_investments tdi ON t.config_id = tdi.id AND t.config_type = 'investment'
INNER JOIN account_entities ae ON tdi.account_id = ae.id
SET t.transaction_type_id = 14
WHERE t.transaction_type_id = 8
AND t.cashflow_value < 0
AND ae.name NOT LIKE '%WiseAlpha%';

-- Verify the changes
SELECT 
    t.id,
    t.date,
    t.transaction_type_id,
    tt.name as type_name,
    t.cashflow_value,
    ae.name as account_name
FROM transactions t
INNER JOIN transaction_types tt ON t.transaction_type_id = tt.id
INNER JOIN transaction_detail_investments tdi ON t.config_id = tdi.id AND t.config_type = 'investment'
INNER JOIN account_entities ae ON tdi.account_id = ae.id
WHERE t.transaction_type_id IN (12, 14)
AND t.cashflow_value < 0
ORDER BY t.date DESC;
