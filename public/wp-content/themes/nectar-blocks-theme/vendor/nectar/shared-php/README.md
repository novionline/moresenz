# nectar/shared-php

Shared PHP code for Nectar Blocks **plugin**, **theme**, and **nectar-blocks-importer-exporter**. Consumed via Composer path repository.

## Setup

From the **plugin** or **theme** (or **nectar-blocks-importer-exporter**) directory:

```bash
composer update
# or
composer install
```

The package is declared as `"nectar/shared-php": "@dev"` and resolved from `../packages/nectar-php`.

## Contents

- **`Nectar\Shared\RemoteVersionCheck`** – Shared logic for the Nectar update API (token-based fetch, transient cache, purge). Used by plugin, theme, and IE updaters.
- **`Nectar\Shared\Helpers`** – Shared helpers (e.g. `option_isset()`). Used by theme and available to plugin.

## Adding shared code

1. Add classes under `src/` with namespace `Nectar\Shared\`.
2. Run `composer dump-autoload` in the package (or in any consumer) to refresh autoload.

## Theme note

The theme loads Composer’s autoload in `functions.php` so that `Nectar\Shared\*` is available to all theme code (including the updater and helpers).
