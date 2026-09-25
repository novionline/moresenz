# WordPress core plugin

Utility plugin for Novi Online WordPress plugins and themes.

This plugin was built using:

- PHP 8.4
- Node 24.10.0

Please use these versions to prevent incompatibility issues.

## Install node modules

- ```$ nvm use 24.10.0```
- ```$ npm install```

## JS / SCSS development

- ```$ nvm use 24.10.0```
- ```$ npm run dev```

## Build JS / SCSS for production

```$ npm run build```

## Customization hooks

The plugin exposes WordPress filters so the active (child) theme can customize the
**light admin bar** and the **admin CMS color scheme** without touching the plugin.
Add the snippets below to your theme's `functions.php` (or a dedicated include).

### Admin CMS color scheme

The admin color scheme follows the NectarBlocks `accentPrimary` global color and
derives its shades from it (falling back to Novi purple `#945ef0` when unset).

| Filter | Arguments | Description |
| --- | --- | --- |
| `novi_admin_color_accent` | `string $accent` | Override the accent hex used for the whole admin scheme. |
| `novi_admin_color_palette` | `array $palette`, `string $base` | Fine-tune the derived shades. Keys: `base`, `lighter`, `darker10`, `darker20`, `baseRgb`, `darker10Rgb`, `darker20Rgb`. |
| `novi_admin_footer_text` | `string $text` | Override the branded `wp-admin` footer text (HTML allowed). |
| `novi_admin_bar_favicon_url` | `string $url` | Override the favicon shown in place of the WP logo in the admin bar. Return an empty string to keep the default WP logo. |
| `novi_admin_chart_accent` | `string $accent` | Override the accent applied to third-party admin charts (e.g. the Redis Object Cache dashboard widget). Defaults to the scheme accent. |

```php
//force a specific admin scheme accent regardless of NectarBlocks
add_filter('novi_admin_color_accent', static function (string $accent): string {
    return '#1c9260';
});

//tweak only the darkest shade used for the admin scheme
add_filter('novi_admin_color_palette', static function (array $palette, string $base): array {
    $palette['darker20'] = '#0c3c28';
    return $palette;
}, 10, 2);

//replace the admin footer branding
add_filter('novi_admin_footer_text', static function (string $text): string {
    return 'Built by <a href="https://example.com">Example</a>';
});

//use a custom image as the admin bar logo
add_filter('novi_admin_bar_favicon_url', static function (string $url): string {
    return get_stylesheet_directory_uri() . '/assets/img/admin-logo.png';
});

//use a different accent for admin charts than the rest of the scheme
add_filter('novi_admin_chart_accent', static function (string $accent): string {
    return '#00394f';
});
```

> Third-party admin charts that hardcode their line color (such as the Redis Object
> Cache dashboard widget, which uses ApexCharts) are recolored to the accent via a
> small inline script. The override is scoped to the screens where that plugin loads.

> The recolored scheme stylesheet is cached in `uploads/novi-admin-color/`. Both the
> accent and the palette are part of the cache key, so changing either via these
> filters automatically regenerates the cached CSS.

### Light admin bar

| Filter | Arguments | Description |
| --- | --- | --- |
| `novi_admin_bar_light_allowed_roles` | `array $roles` | Roles allowed to use the light admin bar (default `['administrator']`). |
| `novi_admin_bar_light_colors` | `array $colors` | Override/extend the resolved NectarBlocks color map (`slug => hex`, e.g. `dark`, `accentPrimary`, `light`). |
| `novi_admin_bar_light_css_variables` | `array $declarations`, `array $nbColors` | Override/extend the CSS custom properties printed on `body .admin-bar-light`. |
| `novi_admin_bar_light_primary_items` | `array $items` | Customize the primary (left) item list. |
| `novi_admin_bar_light_secondary_items` | `array $items` | Customize the secondary (dropdown) item list. |
| `novi_admin_bar_light_used_pattern_ids` | `int[] $ids` | Customize which synced pattern IDs are linked. |
| `novi_admin_bar_light_used_gravity_form_ids` | `int[] $ids` | Customize which Gravity Form IDs are linked. |

```php
//also let editors use the light admin bar
add_filter('novi_admin_bar_light_allowed_roles', static function (array $roles): array {
    $roles[] = 'editor';
    return $roles;
});

//override the source colors feeding the light admin bar
add_filter('novi_admin_bar_light_colors', static function (array $colors): array {
    $colors['accentPrimary'] = '#1c9260';
    return $colors;
});

//override the generated CSS custom properties directly
add_filter('novi_admin_bar_light_css_variables', static function (array $declarations, array $nbColors): array {
    $declarations['--novi-admin-bar-light-color-back'] = '#00394f';
    return $declarations;
}, 10, 2);
```