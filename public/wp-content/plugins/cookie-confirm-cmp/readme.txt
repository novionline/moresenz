=== Cookie Confirm CMP ===
Contributors: cookieconfirm
Tags: gdpr, cmp, cookie, consent, google consent mode
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 1.2
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

With the Cookie Confirm CMP plugin for WordPress, you can easily make your website compliant with GDPR regulations.
The plugin helps you manage cookies and gives visitors clear control over their preferences, keeping you compliant and reducing legal risk.

Cookie Confirm CMP makes privacy compliance simple, transparent, and hassle free.

**Features:**

* Clean and user-friendly cookie banner
* Full control over a variety of cookie categories
* Google Consent Mode v2 support with configurable default states
* Ads data redaction and URL passthrough settings
* Block enqueued scripts, inline scripts, and iframes until consent is granted (YouTube, Vimeo and many more platforms)
* Flexible script matching (exact, contains, regex, starts with, ends with)
* Less then 5 minutes for an easy setup: no technical knowledge required
* Seamless integration with your WordPress website

**Third-Party Service**

This plugin relies on the [Cookie Confirm](https://cookieconfirm.com/) external service to provide the cookie consent banner on your website. By default, the consent script is loaded from `https://assets.cookieconfirm.com/js/consent.js`.

When the consent script is loaded, a request is made to the Cookie Confirm servers to fetch and display the consent banner to your visitors. No personal data from your WordPress site is sent to Cookie Confirm servers by this plugin.

* [Cookie Confirm Website](https://cookieconfirm.com/)
* [Cookie Confirm Terms and Conditions](https://cookieconfirm.com/terms-and-conditions/)

== Installation ==

1. Upload the `cookie-confirm-cmp` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to the 'Cookie Confirm' menu item in your admin dashboard to configure the plugin.
4. Optionally configure Google Consent Mode defaults, script blocking rules, and iframe blocking rules.

== Frequently Asked Questions ==

= Do I need a Cookie Confirm account? =

The plugin works with the default Cookie Confirm consent script. Visit [cookieconfirm.com](https://cookieconfirm.com/) to create an account and customize your consent banner in less then 5 minutes.

= Does this plugin support Google Consent Mode? =

Yes. The plugin injects Google Consent Mode v2 default consent states before any other scripts load. You can configure each consent type (ad_storage, analytics_storage, etc.) as granted or denied by default.

= Can I block specific scripts until consent is given? =

Yes. You can add script URLs or inline script IDs in the settings and assign them to a consent category. The plugin will prevent them from executing until the visitor grants consent for that category.

= Does this plugin use any external services? =

Yes. The plugin loads a consent banner script from Cookie Confirm's servers (`assets.cookieconfirm.com`). See the Description section for full details and links to the terms and conditions.

== Screenshots ==
1. Cookie banner on the frontend.
2. Cookie banner customization page.
3. Admin settings page overview.
4. Google Consent Mode settings.
5. Script blocking configuration.

== Changelog ==

= 1.2 =
* Tested up to WordPress 7.0.

= 1.1 =
* Skip outputting Google Consent Mode defaults when the site already handles Consent Mode through Google Tag Manager, preventing duplicate gtag consent initialization.

= 1.0 =
* Initial release.
* Cookie consent banner integration.
* Google Consent Mode v2 support.
* Script, inline script, and iframe blocking.
* Admin settings page built with Vue 3.

== Upgrade Notice ==

= 1.2 =
Compatibility update for WordPress 7.0.

= 1.1 =
Avoids duplicate Consent Mode initialization on sites that route Consent Mode through Google Tag Manager.

= 1.0 =
Initial release.
