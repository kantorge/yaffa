# Technical Reference

Complete architecture, configuration, and testing documentation.

## Architecture

### Service Overview

```
TextExtractionService (Main Orchestrator)
├─ PdfExtractionService (smalot/pdfparser)
│  ├─ extract(filePath): string
│  └─ hasExtractableText(filePath): bool
│
├─ OcrService (Tesseract + Vision API)
│  ├─ extract(filePath, visionConfig): string
│  ├─ isAvailable(visionConfig): bool
│  ├─ extractWithTesseractBinary(filePath): string
│  ├─ extractWithTesseractHttp(filePath): string
│  └─ extractWithVisionApi(filePath, config): string
│
└─ ImagePreprocessingService
   ├─ resizeForVisionApi(filePath, maxDimension): string
   ├─ getDimensions(filePath): array
   └─ cleanup(filePath): void
```

### Request Flow

```
1. User uploads document
        ↓
2. TextExtractionService::extractFromFile()
        ↓
3. File type detection (PDF, image, text)
        ↓
4a. PDF → PdfExtractionService::extract()
    ├─ Native text found? → Return text
    └─ Scanned PDF → Continue to OCR (step 4b)
        ↓
4b. Image/Scanned PDF → OcrService::extract()
    ├─ TESSERACT_ENABLED=true
    │   ├─ Binary mode → exec tesseract binary
    │   ├─ HTTP mode → POST to tesseract container
    │   └─ Success? → Return OCR text
    │       Failed? → Try Vision API fallback (step 5)
    │
    └─ TESSERACT_ENABLED=false → Vision API only
        ↓
5. Vision API Fallback (if configured)
    ├─ ImagePreprocessingService::resizeForVisionApi() if >2048px
    ├─ Send to gpt-4o / gemini-1.5
    └─ Return extracted text
        ↓
6. All methods failed → throw OcrUnavailableException
```

## Service API Reference

### TextExtractionService

**File:** `app/Services/TextExtractionService.php`

```php
extractFromDocument(AiDocument $document, ?AiProviderConfig $visionConfig): string
```

Extract text from stored document. Handles file path resolution and type detection.

```php
extractFromFile(string $filePath, string $fileType, ?AiProviderConfig $visionConfig): string
```

Extract text from any supported file. Main entry point.

**Supported file types:** `pdf`, `jpg`, `jpeg`, `png`, `txt`

**Throws:** `OcrUnavailableException` if image requires OCR but none available

---

### OcrService

**File:** `app/Services/OcrService.php`

```php
extract(string $filePath, ?AiProviderConfig $visionConfig): string
```

Orchestrates OCR with automatic fallback. Tries Tesseract first, then Vision API.

```php
isAvailable(?AiProviderConfig $visionConfig): bool
```

Check if any OCR method available (Tesseract or Vision API).

```php
extractWithTesseractBinary(string $filePath): string
```

Execute local Tesseract binary. Used when `TESSERACT_MODE=binary`.

```php
extractWithTesseractHttp(string $filePath): string
```

Send image to Tesseract HTTP service. Used when `TESSERACT_MODE=http`.

```php
extractWithVisionApi(string $filePath, AiProviderConfig $config): string
```

Use Vision API (gpt-4o/gemini). Automatically preprocesses large images.

**Throws:** `OcrUnavailableException` if all methods unavailable/failed

---

### PdfExtractionService

**File:** `app/Services/PdfExtractionService.php`

```php
extract(string $filePath): string
```

Extract all text from PDF pages. Concatenates with newlines.

```php
hasExtractableText(string $filePath): bool
```

Check if PDF contains native (non-scanned) text.

**Throws:** `Exception` if PDF parsing fails

---

### ImagePreprocessingService

**File:** `app/Services/ImagePreprocessingService.php`

```php
resizeForVisionApi(string $filePath, int $maxDimension = 2048): string
```

Resize image to max dimension while maintaining aspect ratio. Returns temp file path.

```php
getDimensions(string $filePath): array<int, int>
```

Get image dimensions as `[width, height]`.

```php
cleanup(string $filePath): void
```

Delete temporary resized image file.

## Configuration

### Full Config File

**File:** `config/ai-documents.php`

```php
return [
    'ocr' => [
        // Enable/disable Tesseract OCR entirely
        'enabled' => env('TESSERACT_ENABLED', false),

        // Mode: 'binary' (local exec) or 'http' (Docker sidecar)
        'mode' => env('TESSERACT_MODE', 'binary'),

        // Binary mode configuration
        'binary' => [
            'path' => env('TESSERACT_PATH', '/usr/bin/tesseract'),
        ],

        // HTTP mode configuration
        'http' => [
            'enabled' => env('TESSERACT_HTTP_ENABLED', true),
            'host' => env('TESSERACT_HTTP_HOST', 'tesseract'),
            'port' => env('TESSERACT_HTTP_PORT', 8888),
            'timeout' => env('TESSERACT_HTTP_TIMEOUT', 30),
        ],
    ],
];
```

### Environment Variables

| Variable                 | Default              | Description                    |
| ------------------------ | -------------------- | ------------------------------ |
| `TESSERACT_ENABLED`      | `false`              | Enable Tesseract OCR           |
| `TESSERACT_MODE`         | `binary`             | Mode: `binary` or `http`       |
| `TESSERACT_PATH`         | `/usr/bin/tesseract` | Binary path (binary mode)      |
| `TESSERACT_HTTP_ENABLED` | `true`               | Enable HTTP mode               |
| `TESSERACT_HTTP_HOST`    | `tesseract`          | HTTP service hostname          |
| `TESSERACT_HTTP_PORT`    | `8888`               | HTTP service port              |
| `TESSERACT_HTTP_TIMEOUT` | `30`                 | HTTP request timeout (seconds) |

## Testing

### Test Coverage

**23 tests across 5 files:**

1. `tests/Unit/Helpers/OcrHelpersTest.php` - 5 tests
   - Binary path validation
   - Availability checks
   - Version detection

2. `tests/Unit/Helpers/OcrHelpersHttpTest.php` - 4 tests
   - HTTP health checks
   - Connection availability
   - Timeout handling

3. `tests/Unit/Services/OcrServiceTest.php` - 9 tests
   - Mode dispatching
   - Binary execution
   - HTTP communication
   - Vision API fallback
   - Error handling

4. `tests/Unit/Services/PdfExtractionServiceTest.php` - 3 tests
   - Text extraction
   - Multi-page PDFs
   - Scanned PDFs

5. `tests/Unit/Services/TextExtractionServiceTest.php` - 2 tests
   - File type routing
   - End-to-end extraction

### Running Tests

```bash
# All OCR tests
vendor/bin/sail artisan test --filter Ocr --compact

# Specific test classes
vendor/bin/sail artisan test tests/Unit/Services/OcrServiceTest.php
vendor/bin/sail artisan test tests/Unit/Helpers/OcrHelpersTest.php

# With coverage
vendor/bin/sail artisan test --coverage --min=80
```

### Test Data

Test fixtures in `tests/fixtures/`:

- `sample.pdf` - Native text PDF
- `scanned.pdf` - Image-based PDF
- `test-image.jpg` - Sample OCR image
- `test-corrupted.jpg` - Invalid image

## Helper Functions

**File:** `app/Helpers/OcrHelpers.php`

### tesseract_is_available()

```php
function tesseract_is_available(): bool
```

Check if Tesseract binary is available and executable.

**Returns:** `true` if binary exists at configured path
**Config:** Uses `TESSERACT_PATH` from config

---

### tesseract_http_available()

```php
function tesseract_http_available(): bool
```

Check if Tesseract HTTP service is responding.

**Returns:** `true` if `/health` endpoint responds with 200
**Config:** Uses `TESSERACT_HTTP_HOST` and `TESSERACT_HTTP_PORT`
**Timeout:** Quick check (2 seconds)

---

### tesseract_version()

```php
function tesseract_version(): ?string
```

Get installed Tesseract version.

**Returns:** Version string (e.g., "5.3.0") or `null` if unavailable
**Example:** `"tesseract 5.3.0"`

## Exception Handling

### OcrUnavailableException

**File:** `app/Exceptions/OcrUnavailableException.php`

Thrown when:

- Image/scanned PDF requires OCR
- `TESSERACT_ENABLED=false` AND no Vision API configured
- Tesseract fails AND Vision API unavailable

**Message examples:**

- "OCR is disabled and no Vision API fallback configured"
- "Tesseract binary not found at /usr/bin/tesseract"
- "Tesseract HTTP service unavailable and no Vision API fallback"

**Handle in controllers:**

```php
try {
    $text = $extractor->extractFromFile($path, $type, $config);
} catch (OcrUnavailableException $e) {
    return response()->json([
        'error' => 'OCR not available',
        'message' => $e->getMessage(),
    ], 503);
}
```

## Performance Characteristics

| Metric         | Binary    | HTTP       | Vision API        |
| -------------- | --------- | ---------- | ----------------- |
| **Latency**    | 2-5s      | 3-8s       | 5-15s             |
| **Cost**       | Free      | Free       | $0.003-0.01/image |
| **Memory**     | 50-100MB  | <5MB (app) | <1MB (app)        |
| **Accuracy**   | Good      | Good       | Excellent         |
| **Resolution** | Original  | Original   | Max 2048px        |
| **Best For**   | Dev/small | Production | Complex/fallback  |

### Optimization Tips

1. **Prefer binary mode for development** - Fastest, simplest
2. **Use HTTP mode for production** - Better isolation, scalability
3. **Enable Vision API fallback** - Ensures 100% success rate
4. **Cache extracted text** - Avoid re-processing same documents
5. **Queue heavy workloads** - Don't block HTTP requests

## Dependencies

| Package              | Version | Purpose                 |
| -------------------- | ------- | ----------------------- |
| `smalot/pdfparser`   | ^2.0    | PDF text extraction     |
| `guzzlehttp/guzzle`  | ^7.0    | HTTP client (built-in)  |
| `intervention/image` | -       | Not required (using GD) |

Tesseract OCR is external dependency (binary or Docker).

## Database Schema

No database changes required. Uses existing `AiDocument` model with columns:

- `file_path` - Document storage path
- `file_type` - File extension

No new migrations needed.

## File Structure

```
app/
├── Exceptions/
│   └── OcrUnavailableException.php
├── Helpers/
│   └── OcrHelpers.php
├── Services/
│   ├── TextExtractionService.php
│   ├── OcrService.php
│   ├── PdfExtractionService.php
│   └── ImagePreprocessingService.php
tests/
├── Unit/
│   ├── Helpers/
│   │   ├── OcrHelpersTest.php
│   │   └── OcrHelpersHttpTest.php
│   └── Services/
│       ├── OcrServiceTest.php
│       ├── PdfExtractionServiceTest.php
│       └── TextExtractionServiceTest.php
config/
└── ai-documents.php (OCR section)
docker/
└── docker-compose.yml (tesseract service)
```

Total: 9 application files + 5 test files = 14 files, ~1,410 lines of code
