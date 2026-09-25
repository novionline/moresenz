## Novi Content Translator tests

These are fast PHPUnit tests that validate Novi’s Gutenberg block translation logic without bootstrapping WordPress.

### Run

From `public/wp-content/mu-plugins/novionline/novi-content-translator/`:

```bash
composer install
./vendor/bin/phpunit
```

### Notes

- Tests use WordPress core’s block parser (`wp-includes/class-wp-block-parser.php` + `wp-includes/blocks.php`) but do not load the full WP environment.\n+- If your local WP core path differs, adjust `ABSPATH` logic in `tests/bootstrap.php`.\n+
### Updating fixtures after NectarBlocks updates

1. In WP Admin, open a post that contains the target block.
2. Switch to the editor “Code editor” view.
3. Copy the serialized block markup (`<!-- wp:... --> ... <!-- /wp:... -->`) into a new or existing file under `tests/fixtures/`.
4. Add/update assertions in `tests/BlockContentTranslatorTest.php`.

### Translation behavior in tests

Tests do not call DeepL. `tests/bootstrap.php` injects a deterministic translator:

- Known strings map via a small dictionary (e.g. `Certified` → `Gecertificeerd`)
- Everything else falls back to prefixing with `[NL]`

