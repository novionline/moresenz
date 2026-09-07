# Novi TTFB Profile

Runs only on **Comet cache misses** (WordPress boots). Two layers:

1. **Miss logger (always on for public HTML GETs)** — appends a lightweight JSONL line (no Server-Timing headers).
2. **Gated profiler** — `Server-Timing` + stage breakdown when token/env enabled.

## Miss log (production-safe)

Path: `wp-content/uploads/novi-ttfb/misses.jsonl` (directory gets `.htaccess` Deny).

Logged for public front-end **GET** HTML requests (skips POST, wp-admin, cron, CLI, AJAX, REST, feeds, static extensions, scanner `.php`/`.git` probes, fake `/login`/`/graphql` paths, and WP 404s).

Fields: `ts`, `host`, `method`, `path` (query **keys only**, values redacted), `total_ms`, `object_cache`, `db_query_count`, `profiled`.

Token-gated profile runs also write a richer `metrics` object and set `profiled: true`.

## Enable heavy profiling (pick one)

### A. Token (recommended)

In `wp-config.php`:

```php
define('NOVI_TTFB_PROFILE_TOKEN', 'replace-with-long-random-secret');
```

Then request:

```text
https://datalyzer.com/?novi_ttfb_debug=replace-with-long-random-secret
```

`novi_ttfb_debug` is **not** in Comet’s ignore list, so this forces a miss and emits Server-Timing.

### B. Env flag (all front-end requests)

```bash
export NOVI_TTFB_PROFILE=1
```

Use only for short approved windows.

## Response headers (profiled miss only)

- `Server-Timing: bootstrap, plugins, init, wp, template, total, db…`
- `X-Novi-TTFB-Profile: 1`
- `X-Novi-TTFB-DB-Queries: N`
- `X-Novi-TTFB-Object-Cache: none|redis|external`

Unprofiled misses do **not** add Server-Timing noise for visitors.

## Optional: accurate DB time

```php
define('SAVEQUERIES', true);
```

Turn off after the session. `db_query_count` uses `$wpdb->num_queries` without SAVEQUERIES.

## Comet HIT vs MISS

| Case | What you see |
|---|---|
| Comet **HIT** | Plugin never runs; no log line |
| Comet **MISS** (any public GET) | JSONL miss line |
| Comet **MISS** + token | JSONL + Server-Timing headers |

## Deploy

Wired from [`../load.php`](../load.php). Sync `novi-ttfb-profile/` to staging/prod under `wp-content/mu-plugins/novionline/`.
