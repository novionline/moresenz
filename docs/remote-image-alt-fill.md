# Remote image alt fill — cinema.moresenz.nl

**Status: WAIT FOR GREEN LIGHT.** Do not run this on production until the user explicitly approves (e.g. “green light for remote alt fill”). Local MoreSenz (`https://moresenz.test`) already has NL alts filled; remote content has **not** been updated.

Related local notes: `tmp/alt-inventory/REPORT.md`, scripts under `tmp/alt-inventory/`.

---

## Goal

Fill empty Dutch (`nl_NL`) alt texts on NectarBlocks page-builder images on **cinema.moresenz.nl**, writing:

1. **Primary:** `nectar-blocks/image` attrs `image.alt` + `image.title` (same text)
2. **Secondary (optional):** `_wp_attachment_image_alt` only when empty

Skip decorative SVGs (`alt=""` OK). Child-theme `AccessibilityComponent` hides decorative NB icon/button SVGs from AT (`aria-hidden` + `role="presentation"`).

English (`/en/`) Polylang translation is a **follow-up** after NL is verified — not part of the first remote run unless explicitly requested.

---

## Prerequisites (before green light work)

1. **Explicit green light** in chat for remote alt fill on cinema.moresenz.nl.
2. **DB backup** outside the document root (e.g. `/home/moresenz/backups/` or project `.tmp/db-backups/`), never under `public/`.
3. **Coming Soon (SeedProd Lite):** Lite has **no reliable IP whitelist**. Public crawls / HTML verification need a **temporary disable**, then re-enable. Prefer the SeedProd skill (`seedprod-coming-soon`) / WP-CLI option flow — do not corrupt `seedprod_settings`.
4. **SSH:** `ssh moresenz` → `sudo su - moresenz` (WP root typically `/home/moresenz/public`).
5. **API key:** Gemini key via env (`GEMINI_API_KEY`), never commit `.env` or print keys.
6. **Code already on remote:** theme-child `AccessibilityComponent` (decorative SVG handling) + any tooling you deploy. Alt fill itself is a **content/DB** operation.

---

## Prompt + model

- Prompt source of truth: `wordpress-ai-toolbelt-plugin` → `partials/prompts/generate-alt-tag-by-image.php` (locale `nl_NL`).
- Model used locally: `gemini-3.1-flash-lite` (toolbelt’s older Flash id may be retired for the key — confirm before remote run).
- Output: one concise NL sentence, &lt;125 chars; set **title = alt**.

---

## NB-safe write path (mandatory)

Never hand-write fragile NectarBlocks `save()` markup.

```
parse_blocks(post_content)
  → set attrs.image.alt + attrs.image.title
  → sync first <img> alt/title in innerHTML + innerContent string pieces
  → serialize_blocks(...)
  → wp_slash(...)          // mandatory or Gutenberg JSON breaks
  → wp_update_post(...)
```

Reference implementation (local only): `tmp/alt-inventory/gemini-alt-fill-bulk.php` (+ `retry-large-alts.php` for oversized uploads).

Patterns from novi-nb-content-agent / `docs/nb-block-text-update.md` and BlockContentTranslator `replace_img_attr` sync.

After bulk content mutation:

- Spot-check Gutenberg editor (`isValid === false` / Attempt Recovery) on sample projects + patterns.
- Front-end smoke (HTTP 200, theme dynamic CSS + fonts still enqueued).
- Alt-only edits should **not** require `_nectar_blocks_css` regen; if block structure/blockIds changed, use editor CSS regen (`regen-nb-css-via-editor`).

---

## Large images

Gemini may reject very large files (`too_large`). Local fix: create a **temp** JPEG ≤ ~1600px long edge under `/tmp`, call Gemini, **delete** the temp file. Never overwrite originals in uploads.

Local examples: attachments `2233` (Verde), `2391` (Nexus) via `tmp/alt-inventory/retry-large-alts.php`.

---

## Scope checklist

| Include | Skip / later |
|--------|----------------|
| NL `nectar-blocks/image` empty raster alts | Decorative SVGs |
| Patterns / projects / pages / popups with empty CMS alts | Logo slider already filled (`Client logo grid` / marquee) |
| Optional empty `_wp_attachment_image_alt` | Overwriting non-empty alts |
| | EN Polylang until NL verified |
| | Remote Coming Soon left off |

---

## Verification

1. Inventory empty block alts (WP-CLI / inventory scripts) → expect **0** remaining raster empties in scope.
2. HTML scan of sitemap NL URLs (curl) — empty raster `alt` should be 0; SVG empty alts expected.
3. `siteone-crawler` with Coming Soon temporarily off; treat “all alts OK” cautiously (empty `alt=""` still counts as present). Prefer sitemap HTML scan as authority.
4. Re-enable Coming Soon.
5. Gutenberg spot-check a few updated posts/patterns.

---

## Suggested remote run order (after green light)

1. Quote green-light phrase; confirm cinema.moresenz.nl only.
2. Backup DB outside docroot.
3. Temporarily disable SeedProd Coming Soon.
4. Dry-run discovery counts → sample one project → approve → bulk apply.
5. Resize-retry any `too_large` failures.
6. Verify (inventory + HTML scan + editor spot-check).
7. Re-enable Coming Soon.
8. Plan EN translation separately.

---

## Explicit gate

**Do not fill alts on cinema.moresenz.nl until green light.** Local fill and this documentation do not unlock production content writes.
