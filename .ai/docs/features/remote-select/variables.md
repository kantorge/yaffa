# Variables: Remote Select

No new environment variable, config key or secret.

| Name | Used by | Scope | Source | Risk |
|---|---|---|---|---|
| `REQUEST_DELAY_MS` (150) | `tom-select/index.js` search debounce | client, constant | code | none: it's a UX timing constant, not configuration |

- Nothing secret is bundled client-side: requests use the session cookie and CSRF token through the
  app's existing axios instance.
- CI: `test-e2e.yml` symlinks `.env.ci` (committed, test-only values) and uses the job's MySQL
  service. It needs no repository secrets.
