# cinema.moresenz.nl — image alt/title/description inventory (NL)

Read-only. No content changes. No Gemini.

## Scope
- **Site:** https://cinema.moresenz.nl — single site (not multisite), WP 7.1
- **Language:** Dutch via Polylang (`nl` / `nl_NL`). English (`/en/`) skipped
- **Builder:** **NectarBlocks** + synced patterns (`wp_block`), Popup Maker, featured images, ACF `acf/block-project-marquee-slider`, logo marquees (`nectar-blocks/scrolling-marquee`)
- **Post types scanned (published):** page, post (0), `novi-project` (24), popup (2), `wp_block` (22), `nectar_sections`, `nectar_templates`
- **Drafts/revisions skipped**

## Coming Soon toggle (2026-09-10) — DONE

Green light (user): *"Feel free to temporarily disable coming soon. As long as you re-enable it afterwards."*

| Step | Result |
|------|--------|
| Backup | `/home/moresenz/backups/seedprod_settings.bak-20260910-171151.json` (outside docroot, mode 600) |
| Disable | JSON-string-safe `wp eval` → `enable_coming_soon_mode=false` + `cache flush` |
| Verify off | Homepage ~588 KB, **no** `sp-seedprod`, real NectarBlocks markup |
| Crawl / inventory | siteone-crawler + sitemap HTML alt scan (25 pages) + expanded WP inventory |
| Re-enable | Same JSON-safe pattern → `enable_coming_soon_mode=true` + flush |
| Verify on | Homepage ~5.8 KB, `sp-seedprod` present, option type=string starting with `{` |

Skill followed: `~/.cursor/skills/seedprod-coming-soon/SKILL.md` (never `wp option patch`; always `wp_json_encode` string). Pattern mirrored from 2bhonest staging Coming Soon toggles.

**Coming Soon is ON again for anonymous visitors.**

## Verdict (actionable)

| Question | Answer |
|----------|--------|
| Do **content photos** need descriptive alt? | **Yes** — ~**131** raster `nectar-blocks/image` blocks have empty `attrs.image.alt` (~**124** unique attachments) |
| Are photo **titles** empty? | **No** — almost always filled (e.g. `Project Apex 0.2`) |
| Do photos render with empty `<img alt>`? | **No** — NectarBlocks **falls back to `title` for `alt`** when alt is empty |
| What’s empty in public HTML? | **Decorative SVGs only** — **151** empty `alt=""` across **25** crawled NL pages; **0** raster with empty rendered alt; **0** logo-slider empty alts in HTML |
| Partner **logo slider** gaps? | **No CMS alt/title gaps** — 20 logos in Client logo grid already have alt+title (`logo-{brand}`) |
| **Description** fields? | Effectively unused — NB image attrs have **no** `description` key; media library description/caption almost all empty (**266**/**265** of 267) |
| Gemini still useful? | **Yes** for *descriptive* NL alts on project photos / patterns / card slider / featured / popups (replace filename-ish titles) |

## Where fields live (Gemini targets — NL page-builder preferred)

| Layer | Field | Notes |
|-------|-------|-------|
| **Primary** | `nectar-blocks/image` → `attrs.image.alt` / `attrs.image.title` in `post_content` | Editor values; Polylang-bound on NL posts/patterns |
| **Render fallback** | Empty alt → HTML `alt="{title}"` | Explains CMS empty vs HTML filled |
| **Logo slider** | `nectar-blocks/scrolling-marquee` → `attrs.repeaterContent[].image.image.alt` / `.title` | Pattern **Client logo grid** `#1215` — already filled |
| **Card / carousels** | Nested `nectar-blocks/image` inside `carousel-item` | Same image attrs |
| **Project marquee** | `acf/block-project-marquee-slider` | Pulls `novi-project` featured/highlight images — fix at project/featured layer |
| **Featured** | `_wp_attachment_image_alt` + attachment `post_title` | 13/25 missing alt |
| **Media library** | `_wp_attachment_image_alt`, `post_title`, `post_content` (description), `post_excerpt` (caption) | Backfill optional; descriptions unused today |
| **Popups** | Same NB image attrs | Brochure `#823`, Visit showroom `#703` — 1 photo each, empty CMS alt |
| **CSS heroes** | `bgImage.desktop` on row/flex-box | Not `<img>` — no alt attribute |

## Counts by type (CMS / NL)

### Unique content photos needing alt (block alt empty)
| Source | Unique photo gaps |
|--------|-------------------|
| `novi-project` galleries | **103** |
| Synced patterns (`wp_block`) | **21** |
| **Total unique** | **124** |

Pattern photo gaps (fill once, reused):
- Stacked content sections `#551` — 7
- Card slider `#1411` — **4** (`particulier`, `architecten`, `installateur`, `bedrijven`)
- Project information (bento grid) — 4
- Image with content variants / Awards / Person CTA — remaining

Also: **13** featured-image attachment alts empty; **2** popup content photos; media library **204**/267 missing `_wp_attachment_image_alt`.

### Titles
- Content photos: titles present (missing title ≈ **1** edge case)
- Logo marquee: **20/20** titles filled

### Descriptions
- NB image blocks: `description` key **not used** (0/223)
- Media library: description empty **266**/267; caption empty **265**/267
- For Gemini: prefer writing **alt** (and optionally better **title**); description only if product wants media-library SEO copy

### Logo / slider / carousel inventory
| Pattern / block | Post ID | Image source | Alt | Title | Action |
|-----------------|---------|--------------|-----|-------|--------|
| Client logo grid (`scrolling-marquee` ×2) | `#1215` | 20 SVG partner logos in `repeaterContent` | **20/20 filled** (`logo-*`) | **20/20** | Optional polish only |
| Card slider | `#1411` | 4 PNG hero cards via `nectar-blocks/image` | **0/4** empty | filled (slug) | **Fill alts** |
| Customer reviews carousel | `#448` | Decorative dash SVGs | empty OK | filled | Skip |
| Project marquee slider | `#1426` | ACF → project posts | via featured/project images | — | Fix projects/featured |
| Homepage `#moresenz-partners` | uses logo grid pattern | same as `#1215` | OK in HTML | OK | — |

## Public HTML crawl (Coming Soon temporarily off)

### Sitemap complementary crawl (authoritative for rendered alt)
- **25** NL URLs (homepage + Yoast page/project sitemaps; `/en/` skipped)
- **779** `<img>` tags
- **151** empty `alt=""` — **all SVG** (icons/dividers/checks)
- **0** raster empty alt
- **0** logo-ish empty alt
- Artifacts: `tmp/alt-inventory/crawler/html-alt-scan.json`

### siteone-crawler
- Pass with Coming Soon **off** on `/`: crawled real homepage (~574 KB) but **did not follow** in-page project links (tool limitation with this site / `--disable-all-assets`); still reported “All pages have image alt attributes” (empty SVG `alt=""` counts as attribute present)
- Sitemap XML start URL also stayed at 1 document (sitemap XML, not expanded locs)
- Artifacts: `cinema-nl-open.*`, `cinema-nl-projects.*`
- **Use sitemap HTML scan + WP inventory** as source of truth, not siteone’s “all alts OK” summary

## Sample URLs / post IDs

| What | Example |
|------|---------|
| Project photos | https://cinema.moresenz.nl/projecten/reserve/ — att `2418` title `Project Reserve 0.2`, CMS alt empty |
| Aspen | https://cinema.moresenz.nl/projecten/aspen/ — att `2408`–`2411` |
| Apex | https://cinema.moresenz.nl/projecten/apex/ — att `2240`–`2243` |
| Card slider pattern | `wp_block` `#1411` — edit `post.php?post=1411&action=edit` |
| Logo grid pattern | `wp_block` `#1215` — alts already set |
| Stacked sections | `wp_block` `#551` |
| Popups | `#823` Brochure, `#703` Visit showroom |
| Edit link | `https://cinema.moresenz.nl/wp-admin/post.php?post={ID}&action=edit` |

## Cross-check

| Signal | Says | Why |
|--------|------|-----|
| Block attrs / media meta | Many empty photo alts | True CMS gap for Gemini |
| Rendered HTML (25 pages) | Photos have alt; only SVG empty | NB title→alt fallback |
| Logo marquee CMS + HTML | Alts present | `logo-{brand}` already set |
| siteone-crawler summary | “OK” | Misleading (empty alt attr; incomplete URL discovery) |
| Descriptions | Almost never filled | Not a current a11y gap in HTML; optional media SEO |

## Recommended Gemini fill order
1. **Project photos** on `novi-project` — descriptive NL alt; keep/improve title
2. Synced patterns — especially **Stacked content sections**, **Card slider** (4 PNGs), bento/project info
3. Featured-image attachment alts
4. Popup content photos (`#823`, `#703`)
5. Optional: media-library orphans + descriptions/captions
6. Skip decorative SVGs (`alt=""` OK)
7. Logo slider: **skip or light polish** only (already has `logo-*` alts)
8. CSS backgrounds only with separate accessible text if required

## Artifacts
- `tmp/alt-inventory/REPORT.md` (this file)
- `tmp/alt-inventory/nb-expanded-image-inventory.php` + `.json`
- `tmp/alt-inventory/crawler/html-alt-scan.json`
- `tmp/alt-inventory/crawler/cinema-nl-open.*`
- Prior: `nb-image-alt-scan.php`, `nb-unique-missing-alt.json`, `rendered-*.json`

## Caveats
- Theme chrome beyond content scans may still include header logo (usually labeled)
- `wp_block` URLs may not be public pages but inject via `core/block`
- English skipped; drafts skipped
- SeedProd Lite has no working IP whitelist (Pass 2 finding) — temporary disable was required for public crawl
- UFW: home IP `87.212.251.8` allowed for SSH on `moresenz` (unrelated to SeedProd)

---

## Local Gemini alt-fill (2026-09-10) — DONE on https://moresenz.test

User approved Eclipse sample, then bulk-fill + crawl.

### Approach
- **Prompt:** wordpress-ai-toolbelt-plugin `partials/prompts/generate-alt-tag-by-image.php` (locale `nl_NL`)
- **Model:** `gemini-3.1-flash-lite` (toolbelt recommends Gemini 2.0 Flash; that id is retired for this key)
- **Primary write:** NectarBlocks `attrs.image.alt` + `attrs.image.title` (= same text)
- **NB-safe:** `parse_blocks` → mutate attrs → sync first `<img>` in `innerHTML`/`innerContent` → `serialize_blocks` → **`wp_slash`** → `wp_update_post`
- **Secondary:** `_wp_attachment_image_alt` when empty
- **Skipped:** decorative SVGs; logo slider `#1215` (20/20 already filled); non-empty alts; Eclipse sample already filled
- **Discovery:** live scan of image-like attr nodes with `url`+`alt` — only `nectar-blocks/image` had empty **raster** alts; marquee/logo paths already complete

### Sample (approved first)
| Field | Value |
|-------|--------|
| Post | Eclipse `#27` |
| URL | https://moresenz.test/projecten/eclipse/ |
| Block | `nectar-blocks/image` `block-jngokjt8be9q` |
| Attachment | `2003` / `about-this-project.png` |
| Alt = title | `Luxe thuisbioscoop met sterrenhemelplafond en een groot scherm waarop een scène uit de film Avatar wordt getoond.` |

### Bulk fill counts
| Metric | Count |
|--------|------:|
| Unique attachments targeted | 121 |
| Gemini generated | 75 |
| Cache hits (prior run) | 44 |
| Initial Gemini failures (`too_large`) | **2** → resolved via temp resize |
| Block alts filled (`nectar-blocks/image`) | **137** (135 + 2 retry) |
| Block alts skipped (already non-empty) | 6 |
| Posts updated | 38 (36 + 2) |
| Attachment meta updated (empty only) | 116 (114 + 2) |
| Remaining CMS raster empty alts after fill | **0** |
| Featured-image empty attachment alts | **0** |

### Resize-retry (temp derivative only — originals untouched)
| Attachment | File | Method | Bytes | Alt (NL) |
|------------|------|--------|------:|----------|
| `2233` | `PBF0820.jpg` (Verde) | `temp_resize_1600` | 253524 | Fluweelzachte bank met kussen voor een donkere, geribbelde wand met wandlamp in Project Verde 0.5. |
| `2391` | `PBF3934-HDR-Pano-DN.jpg` (Nexus) | `temp_resize_1600` | 289295 | Thuisbioscoop met een groot scherm waarop Iron Man te zien is, een salontafel en blauwe sfeerverlichting in Project Nexus 0.3 |

Temp JPEGs written under `/tmp`, deleted after Gemini call.

### DB backups (outside docroot)
- `.tmp/db-backups/moresenz-local-pre-alt-fill-20260910-174229.sql`
- `.tmp/db-backups/moresenz-local-pre-bulk-alt-fill-20260910-175018.sql`

### Verification crawl (local)
**siteone-crawler** (`--disable-all-assets --no-cache`) on `/`:
- Visited **1** URL (homepage only — known link-discovery limit on this site)
- Reported: “All pages have image alt attributes”
- Artifacts: `tmp/alt-inventory/crawler/moresenz-local-after-alt.*`

**Authoritative HTML alt scan** (sitemap URL list via curl, 146 NL pages):
| Metric | Value |
|--------|------:|
| Pages | 146 |
| `<img>` tags | 779 |
| Empty **raster** alt | **0** |
| Empty **SVG** alt | 151 (decorative icons/dividers — expected) |
| Artifact | `tmp/alt-inventory/crawler/local-html-alt-scan.json` |

### Remaining gaps
- **None** for raster content photos in NB block attrs / featured meta on local NL content.
- Decorative SVG `alt=""` left intentionally.
- English (`/en/`) not in scope.
- Remote/production NL alts filled 2026-09-25 (see section below).

### Remote (cinema.moresenz.nl) — completed 2026-09-25

**Green light:** “We have green light to fill in the NL alt tags on cinema.moresenz.nl.”

| Item | Value |
|------|--------|
| Backup | `/home/moresenz/backups/cinema-pre-alt-fill-20260925-083614.sql` (67M, outside docroot) |
| Scripts | `/home/moresenz/scripts/alt-inventory/` (non-public) |
| Sample | Eclipse `#27` / att `2003` — NL alt+title written first |
| Generated | **143** unique attachment alts (`gemini-3.1-flash-lite`) |
| Block alts filled | **151** instances (`nectar-blocks/image`) |
| Posts updated | **38** |
| Attachment meta updated | **138** (empty only) |
| Gemini failures / too_large | **0** (no resize-retry needed) |
| Remaining CMS raster empty alts | **0** |
| API key on server | removed after run |

**Integrity (post-fill):**
- `audit-nb-after-alt`: `roundtrip_fail=0`, `corruption=0`, **no** `alt_mismatch` / `title_mismatch`
- Recently modified posts: **38**, lost unicode escapes: **0**
- `thin_css` on Homepage `#19` (0 inline images / pattern-driven) and Project template `#24` — expected, not alt damage
- `many_missing_css_ids` (34) — same false-positive class as local (blocks without custom CSS rules); no CSS regen required
- `wp nct` not installed on remote — used audit script + unicode check instead

**Verification (Coming Soon temporarily off, then re-enabled):**
- siteone-crawler: 28 URLs / ~8 MB — reported “26 page(s) without image alt attributes” (counts empty SVG `alt=""`; not authoritative for photos)
- HTML scan of 26 NL page/project URLs: **0 empty raster alts**; **183** empty SVG (`157` with `aria-hidden`/`role=presentation`)
- Artifacts: `crawler/cinema-remote-after-alt-20260925-084440.console.txt`, `crawler/cinema-remote-html-alt-scan.json`
- Coming Soon verified **on** again (`sp-seedprod`, ~5.8 KB shell); `seedprod_settings` remains JSON string

**Still pending:** Polylang English translation of alts (out of scope for this run).

### Scripts / cache
- `tmp/alt-inventory/gemini-alt-fill-bulk.php`
- `tmp/alt-inventory/retry-large-alts.php`
- `tmp/alt-inventory/fill-one-sample.php` (+ sync HTML helper)
- `tmp/alt-inventory/gemini-alt-cache.json` (local)
- Remote cache/log: `/home/moresenz/scripts/alt-inventory/gemini-alt-*.json`
- `tmp/alt-inventory/discover-image-attr-gaps.json`
