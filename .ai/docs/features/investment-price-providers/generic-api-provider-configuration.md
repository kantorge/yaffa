# Generic API Provider Configuration

## Summary

The `generic_api` provider lets a user define how prices are fetched from any JSON HTTP API.

This document covers:

- API endpoints used to save and validate provider configuration
- all supported configuration fields
- sample request/response payloads
- both parsing modes:
  - object-item mode (single `items_path` collection)
  - parallel-array mode (separate date and price arrays)

All examples below use sample endpoints and sample payloads.

## Backend Endpoints

The following endpoints are used for provider-level configuration:

- `PATCH /api/v1/investment-provider-configs/generic_api`
  - Create or update the authenticated user's `generic_api` configuration.
- `POST /api/v1/investment-provider-configs/generic_api/test`
  - Validate credentials/configuration for `generic_api`.
- `GET /api/v1/investment-provider-configs/generic_api`
  - Read current saved configuration metadata (credentials are not returned).

Optional runtime test endpoint (investment form test action):

- `POST /api/v1/investment-price-providers/generic_api/test-fetch`
  - Runs a provider fetch using a symbol and provider settings.

## Configuration Fields (credentials)

`credentials` is a JSON object stored encrypted at rest.

- `endpoint_url` (string, required)
  - URL for the upstream API request.
  - Placeholders supported: `{symbol}`, `{from}`, `{to}`.
- `http_method` (string, optional)
  - Allowed: `GET`, `POST`.
  - Default: `GET`.
- `headers_json` (string, optional)
  - JSON object string for request headers.
- `query_json` (string, optional)
  - JSON object string for query parameters.
- `body_json` (string, optional)
  - JSON object string for POST body.
- `date_format` (string, optional)
  - Allowed: `auto`, `Y-m-d`, `timestamp_seconds`, `timestamp_milliseconds`.
  - Default: `auto`.

Object-item mode fields:

- `items_path` (string, optional)
  - Path to array of objects. If empty, full response is treated as one item.
- `date_path` (string, conditionally required)
  - Path to date value inside each item object.
- `price_path` (string, conditionally required)
  - Path to price value inside each item object.

Parallel-array mode fields:

- `date_values_path` (string, conditionally required)
  - Path to date array.
- `price_values_path` (string, conditionally required)
  - Path to price array.

Conditional validation behavior:

- If either `date_values_path` or `price_values_path` is present, parallel-array mode is active and both are required.
- If parallel-array mode is not active, `date_path` and `price_path` are required.

## Placeholder Rules

Placeholders are interpolated in:

- `endpoint_url`
- `headers_json`
- `query_json`
- `body_json`

Supported tokens:

- `{symbol}`: investment symbol, for example `MSFT`
- `{from}`: start date in `Y-m-d`
- `{to}`: current date in `Y-m-d`

## Example A: Object-Item Mode

### Sample upstream endpoint

`GET https://prices.sample-api.dev/v1/eod?ticker={symbol}`

### Sample upstream JSON response

```json
{
  "data": [
    { "d": "2026-05-28", "close": 410.12 },
    { "d": "2026-05-29", "close": 411.78 },
    { "d": "2026-05-30", "close": 409.55 }
  ]
}
```

### Save config request

```http
PATCH /api/v1/investment-provider-configs/generic_api
Content-Type: application/json
Authorization: Bearer <token>

{
  "credentials": {
    "endpoint_url": "https://prices.sample-api.dev/v1/eod",
    "http_method": "GET",
    "query_json": "{\"ticker\":\"{symbol}\"}",
    "items_path": "data",
    "date_path": "d",
    "price_path": "close",
    "date_format": "Y-m-d"
  }
}
```

### Save config response (example)

```json
{
  "id": 12,
  "provider_key": "generic_api",
  "options": null,
  "last_error": null,
  "rate_limit_overrides": null,
  "has_credentials": true,
  "created_at": "2026-06-07T12:10:12.000000Z",
  "updated_at": "2026-06-07T12:10:12.000000Z"
}
```

## Example B: Parallel-Array Mode

### Sample upstream endpoint

`GET https://market-data.sample-api.dev/history/{symbol}`

### Sample upstream JSON response

```json
{
  "history": {
    "dates": [1717718400, 1717804800, 1717891200],
    "last": [99.5, 101.2, 100.75]
  }
}
```

### Save config request

```http
PATCH /api/v1/investment-provider-configs/generic_api
Content-Type: application/json
Authorization: Bearer <token>

{
  "credentials": {
    "endpoint_url": "https://market-data.sample-api.dev/history/{symbol}",
    "http_method": "GET",
    "date_values_path": "history.dates",
    "price_values_path": "history.last",
    "date_format": "timestamp_seconds"
  }
}
```

Notes:

- In parallel mode, `date_path` and `price_path` are not required.
- The parser zips arrays by index and skips rows where date or price is missing/invalid.

## Testing Configuration

### Validate credentials/config fields

```http
POST /api/v1/investment-provider-configs/generic_api/test
Content-Type: application/json
Authorization: Bearer <token>

{
  "credentials": {
    "endpoint_url": "https://market-data.sample-api.dev/history/{symbol}",
    "http_method": "GET",
    "date_values_path": "history.dates",
    "price_values_path": "history.last",
    "date_format": "timestamp_seconds"
  }
}
```

Success response:

```json
{
  "message": "Provider configuration is valid."
}
```

Validation error example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "credentials.price_values_path": [
      "This field is required when using parallel array mode."
    ]
  }
}
```

### Runtime fetch test

```http
POST /api/v1/investment-price-providers/generic_api/test-fetch
Content-Type: application/json
Authorization: Bearer <token>

{
  "symbol": "MSFT",
  "provider_settings": {}
}
```

Success response (example):

```json
{
  "message": "Test fetch successful.",
  "provider_key": "generic_api",
  "symbol": "MSFT",
  "price": 101.2,
  "date": "2024-06-08"
}
```

## Operational Notes

- Credentials are encrypted at rest and never returned by API responses.
- Empty string values are treated as missing for required checks.
- In `auto` date mode, numeric dates are treated as unix timestamps; very large values are interpreted as milliseconds.
- Only positive numeric prices are accepted.
