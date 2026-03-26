# PDF & Image Text Extraction

Production-ready document OCR and text extraction for YAFFA.

## Features

✅ Extract text from PDFs with native text
✅ Extract text from images (JPG, PNG) via OCR
✅ Multiple deployment modes (binary, Docker, cloud)
✅ Automatic fallback to Vision API
✅ 24 passing tests, zero breaking changes

⚠️ Scanned PDF OCR fallback is not yet implemented (tracked as pending MVP gap)

## Quick Start

**1. Choose deployment mode:**

| Mode           | Use Case                 | Latency | Setup                          |
| -------------- | ------------------------ | ------- | ------------------------------ |
| **Binary**     | Local dev / custom setup | 2-5s    | Install Tesseract on host      |
| **Docker**     | Production               | 3-8s    | Enable in docker-compose.yml   |
| **Cloud Only** | Fallback / simple        | 5-15s   | Vision API only (no Tesseract) |

**2. Configure environment:**

```env
# Option A: Binary mode (local Tesseract)
TESSERACT_ENABLED=TRUE
TESSERACT_MODE=binary
TESSERACT_PATH=/usr/bin/tesseract

# Option B: Docker mode (HTTP sidecar)
TESSERACT_ENABLED=TRUE
TESSERACT_MODE=http
TESSERACT_HTTP_HOST=tesseract
TESSERACT_HTTP_PORT=8888

# Option C: Cloud only (no Tesseract)
TESSERACT_ENABLED=FALSE
```

## Further Reading

- **Technical Reference** → [`REFERENCE.md`](./REFERENCE.md) - Architecture and testing

## Deployment Options

### Binary Mode (Development)

Install Tesseract locally:

```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr
# Install language packs as needed, e.g.:
sudo apt-get install tesseract-ocr-eng tesseract-ocr-fra

# macOS
brew install tesseract

# Verify
tesseract --version
```

### Docker Mode (Production)

Enable Tesseract service in `docker-compose.yml`:

```yaml
# Uncomment the tesseract dependency in app service:
depends_on:
  tesseract:
    condition: service_healthy

# Uncomment the tesseract service (or remove profile)
tesseract:
  image: franky1/tesseract-ocr:5.5.2
  # ... full config in docker-compose.yml
```

Then start:

```bash
docker-compose up -d
```

### Cloud Only Mode

No Tesseract needed - all OCR via Vision API:

```env
TESSERACT_ENABLED=FALSE
```

Requires OpenAI or Gemini API key configured via UX (AI document settings). Make sure to select AI Provider and model that support Vision API.

## How It Works

```
Upload document / Load from Google Drive
    ↓
    ↓
TextExtractionService detects file type
    ↓
┌─ PDF → Extract native text
├─ Image → Tesseract OCR → fallback to Vision API (whichever available and enabled)
└─ Text → Read directly
    ↓
Return extracted text for further processing
```

If Tesseract is unavailable, extraction falls back to Vision API when configured; otherwise processing fails with a clear OCR-related error.
