# YAFFA NLP Service

This microservice provides NLP-based classification for payee management in YAFFA.

## Features

1. **Duplicate Detection**: Uses semantic similarity to find duplicate payees
2. **Transfer Classification**: Identifies transactions that should be transfers

## Technology Stack

- **Framework**: Flask (Python web framework)
- **NLP Model**: sentence-transformers (all-MiniLM-L6-v2)
  - Lightweight: ~80MB
  - Fast inference: ~50ms per request
  - Accurate semantic similarity
- **Similarity**: Cosine similarity for embeddings

## API Endpoints

### Health Check
```
GET /health
```

### Classify Transfer
```
POST /api/classify/transfer
Content-Type: application/json

{
  "payee_name": "Transfer to Savings Account",
  "transaction_type": "withdrawal",
  "description": "Monthly transfer"
}
```

Response:
```json
{
  "is_transfer": true,
  "confidence": 0.95,
  "matched_patterns": ["transfer", "to.*account"],
  "recommendation": "Convert withdrawal to transfer transaction"
}
```

### Find Duplicates
```
POST /api/find-duplicates
Content-Type: application/json

{
  "payees": [
    {"id": 1, "name": "Amazon.com"},
    {"id": 2, "name": "Amazon UK"},
    {"id": 3, "name": "Tesco PLC"}
  ],
  "threshold": 0.85
}
```

Response:
```json
{
  "duplicate_groups": [
    {
      "primary": {"id": 1, "name": "Amazon.com"},
      "duplicates": [
        {"id": 2, "name": "Amazon UK", "similarity": 0.92}
      ],
      "recommendation": "Merge payee 2 into payee 1"
    }
  ],
  "total_duplicates_found": 1
}
```

### Calculate Similarity
```
POST /api/similarity
Content-Type: application/json

{
  "name1": "Amazon.com",
  "name2": "Amazon UK",
  "threshold": 0.85
}
```

## Building and Running

### Local Development
```bash
cd docker/nlp-service
pip install -r requirements.txt
python app.py
```

### Docker Build
```bash
docker build -t yaffa-nlp:latest -f docker/nlp-service/Dockerfile docker/nlp-service
```

### Docker Run
```bash
docker run -p 5000:5000 yaffa-nlp:latest
```

### Docker Compose (recommended)
```bash
docker-compose up nlp-service
```

## Model Information

**all-MiniLM-L6-v2**:
- 384-dimensional embeddings
- 22.7M parameters
- ~80MB download size
- Trained on 1B+ sentence pairs
- Optimized for semantic similarity

## Performance

- Cold start: ~5 seconds (model loading)
- Single similarity: ~20-30ms
- Batch of 100 payees: ~200-300ms
- Memory usage: ~250MB

## Integration with YAFFA

The Laravel application calls this service via HTTP:

```php
// app/Services/NlpService.php
$response = Http::post('http://nlp-service:5000/api/find-duplicates', [
    'payees' => $payees,
    'threshold' => 0.85
]);
```

See the Laravel service integration in `app/Services/NlpService.php`.
