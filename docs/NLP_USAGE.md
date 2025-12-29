# YAFFA NLP Integration - Usage Guide

This guide explains how to use the NLP service for payee management.

## Prerequisites

### Docker Setup
The NLP service runs as a separate Docker container alongside YAFFA.

**Build and Start:**
```bash
# Build the NLP service image
docker-compose build nlp-service

# Start the NLP service
docker-compose up -d nlp-service

# Check health
curl http://localhost:5000/health
```

**Environment Variables** (add to `.env`):
```env
NLP_SERVICE_URL=http://nlp-service:5000
NLP_SERVICE_TIMEOUT=30
NLP_SERVICE_PORT=5000
```

## Usage

### 1. Find Duplicate Payees

Identifies payees that are likely duplicates using semantic similarity.

**Dry-run** (see what would be merged):
```bash
php artisan payees:find-duplicates
```

**Options:**
```bash
# Adjust similarity threshold (0.0-1.0, default 0.85)
php artisan payees:find-duplicates --threshold=0.90

# Check specific user only
php artisan payees:find-duplicates --user=1

# Actually merge duplicates (prompts for confirmation)
php artisan payees:find-duplicates --merge
```

**Example Output:**
```
Analyzing 234 payees with threshold 0.85...
Found 3 duplicate group(s):

Primary: Amazon.com (ID: 45)
  → Amazon UK (ID: 52) - Similarity: 0.92
  → AMAZON.CO.UK (ID: 67) - Similarity: 0.88

Primary: Tesco PLC (ID: 12)
  → TESCO Limited (ID: 89) - Similarity: 0.96

Primary: Sainsbury's (ID: 34)
  → Sainsburys (ID: 78) - Similarity: 0.94
```

**How it Works:**
- Uses sentence-transformers to convert payee names to semantic embeddings
- Calculates cosine similarity between all pairs
- Groups similar payees together
- When merging: moves all transactions to primary payee, deletes duplicates

### 2. Identify Transfer Transactions

Finds deposits/withdrawals that should be transfers based on payee names.

**Dry-run** (see what would be converted):
```bash
php artisan transactions:identify-transfers
```

**Options:**
```bash
# Adjust confidence threshold (0.0-1.0, default 0.7)
php artisan transactions:identify-transfers --confidence=0.8

# Check specific user only
php artisan transactions:identify-transfers --user=1

# Limit how many to check
php artisan transactions:identify-transfers --limit=500

# Actually convert transactions (prompts for confirmation)
php artisan transactions:identify-transfers --convert
```

**Example Output:**
```
Analyzing 150 transaction(s)...
Found 12 transaction(s) that should be transfers:

Transaction #3456
  Date: 2024-12-01
  Type: withdrawal
  Payee: Transfer to Savings Account
  Confidence: 0.95
  Matched Patterns: transfer, to.*account

Transaction #3478
  Date: 2024-11-15
  Type: deposit
  Payee: From Current Account
  Confidence: 0.88
  Matched Patterns: from.*account
```

**How it Works:**
- Uses regex patterns to identify transfer-like language
- Checks for keywords: transfer, account, internal, etc.
- Calculates confidence based on matches
- When converting: removes transaction items, changes type to transfer

## Programmatic Usage

### In Your Laravel Code

**Find Duplicates:**
```php
use App\Services\NlpService;

$nlpService = app(NlpService::class);

// Get duplicates for current user
$result = $nlpService->findUserPayeeDuplicates(
    userId: auth()->id(),
    threshold: 0.85,
    cacheTtl: 3600  // Cache for 1 hour
);

foreach ($result['duplicate_groups'] as $group) {
    echo "Primary: {$group['primary']['name']}\n";
    
    foreach ($group['duplicates'] as $dup) {
        echo "  → {$dup['name']} (similarity: {$dup['similarity']})\n";
    }
}
```

**Classify Transfer:**
```php
$result = $nlpService->classifyTransfer(
    payeeName: 'Transfer to Savings',
    transactionType: 'withdrawal',
    description: 'Monthly transfer'
);

if ($result['is_transfer'] && $result['confidence'] > 0.8) {
    echo "This should be a transfer transaction\n";
    echo "Confidence: {$result['confidence']}\n";
}
```

**Check Similarity:**
```php
$result = $nlpService->calculateSimilarity(
    name1: 'Amazon.com',
    name2: 'Amazon UK',
    threshold: 0.85
);

if ($result['are_similar']) {
    echo "These payees are duplicates (similarity: {$result['similarity']})\n";
}
```

## Performance Tips

1. **Batch Processing**: Process payees in batches if you have thousands
2. **Caching**: Results are cached by default (1 hour for duplicate detection)
3. **Threshold Tuning**: 
   - Lower threshold (0.75-0.80) = more matches, more false positives
   - Higher threshold (0.90-0.95) = fewer matches, higher confidence
4. **Background Jobs**: For large datasets, consider using queue workers

## Troubleshooting

**Service Not Available:**
```bash
# Check if container is running
docker-compose ps nlp-service

# View logs
docker-compose logs nlp-service

# Restart service
docker-compose restart nlp-service
```

**Out of Memory:**
If processing thousands of payees, the NLP service may need more memory:
```yaml
# In docker-compose.yml, add to nlp-service:
deploy:
  resources:
    limits:
      memory: 1G
```

**Slow Performance:**
- First request takes ~5 seconds (model loading)
- Subsequent requests: 20-50ms per payee
- For 1000+ payees, consider splitting into batches

## Model Details

**sentence-transformers/all-MiniLM-L6-v2:**
- Size: ~80MB
- Parameters: 22.7M
- Embedding dimensions: 384
- Training: 1B+ sentence pairs
- Speed: ~20ms per inference
- Accuracy: SOTA for semantic similarity tasks

## API Endpoints

If you want to call the service directly:

```bash
# Health check
curl http://localhost:5000/health

# Find duplicates
curl -X POST http://localhost:5000/api/find-duplicates \
  -H "Content-Type: application/json" \
  -d '{
    "payees": [
      {"id": 1, "name": "Amazon.com"},
      {"id": 2, "name": "Amazon UK"}
    ],
    "threshold": 0.85
  }'

# Classify transfer
curl -X POST http://localhost:5000/api/classify/transfer \
  -H "Content-Type: application/json" \
  -d '{
    "payee_name": "Transfer to Savings",
    "transaction_type": "withdrawal"
  }'

# Check similarity
curl -X POST http://localhost:5000/api/similarity \
  -H "Content-Type: application/json" \
  -d '{
    "name1": "Amazon.com",
    "name2": "Amazon UK"
  }'
```

## Future Enhancements

Potential improvements:
- Auto-categorization of transactions
- Anomaly detection for unusual spending
- Budget predictions
- Smart payee suggestions based on description
- Recurring transaction pattern detection
