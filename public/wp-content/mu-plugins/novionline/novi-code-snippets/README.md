# Novi Code Snippets

Private CPT for CSS/JS code snippets; output in head (CSS) and footer (JS). Novi admins only.

## Requirements

- PHP 7.4+
- [Composer](https://getcomposer.org/) (for dependency installation)

## Setup

Install Composer dependencies (validation and minification):

```bash
cd wp-content/mu-plugins/novionline/novi-code-snippets
composer install
```

Uses [matthiasmullie/minify](https://github.com/matthiasmullie/minify) for CSS/JS validation and minification. Invalid code fails minification and is not output on the front-end.
