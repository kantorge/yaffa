# Frontend Context – YAFFA (`resources/js/`)

See root [CLAUDE.md](../../CLAUDE.md) for project overview and commands.
See [.ai/agents/frontend.agent.md](../../.ai/agents/frontend.agent.md) for full frontend implementation rules.

## Key Architecture Facts

- This is **NOT an SPA** — Blade templates are the foundation; Vue adds interactivity
- Vue components are mounted as self-contained islands on server-rendered pages
- No global state management (no Vuex/Pinia) — components do not share state
- UI framework: Bootstrap 5.3 utilities + CoreUI component patterns

## Vue Conventions

- **Options API** — use `export default { data(), methods:, computed:, ... }` inside `<script>`
- Do **not** use `<script setup>` — the codebase uses classic `<script>` blocks
- Keep components small and focused on one responsibility
- Prefer `computed` over `watch`
- Always handle all three async states: **loading / error / empty**

## Bootstrap Conventions

- Bootstrap utility classes first, custom CSS only when utilities are insufficient
- Follow existing layout patterns (cards, modals, tables, forms) — check siblings
- No inline styles unless absolutely unavoidable
- Responsive behavior is expected on all components

## Build

After any change to JS/Vue/SCSS, rebuild before testing:

```bash
vendor/bin/sail npm run dev
```

## Scope Boundaries

- Do not modify PHP/Laravel backend code
- Do not introduce new JS frameworks, UI libraries, or CSS tools
- Do not redesign existing UI patterns without user approval
- Do not change global styles without user approval
