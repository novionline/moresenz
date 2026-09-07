=== Notification Attachments for Gravity Forms ===
Contributors: kgmservizi
Donate link: https://kgmservizi.com
Tags: gravity forms notification attachments
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send attachment in Gravity Forms Notification

== Description ==

**REQUIREMENTS**
**LATEST VERSION IS COMPATIBLE ONLY WITH GRAVITY FORMS > 2.5+**

**Don't work? Open ticket, we answer in max 48h.**

**This plugin requires Gravity Forms.**

Send attachment in Gravity Forms Notification, you can add easily from Gravity Forms Notification setting.

**Filter**

For allow other plugins/themes to update the attachment_path as they need.

add_filter('gf_kgm_notification_attachment_path', $path, $attachment_id, $form, $lead)


== Installation ==

1. Upload the plugin files to the '/wp-content/plugins/' directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Add attachment in Gravity Forms Notification.


== Frequently Asked Questions ==

= Where I can add files? =

You can add one or multiple files as attachment in any notification settings of your Gravity Forms module.

= I can add more than one attachment? =

Yes.

= How plugin work? =

Plugin use default Gravity Forms send hook.


== Screenshots ==

1. Example.


== Changelog ==

= 0.6.3 =
* Update compatibility versions (PHP/WP).

= 0.6.2 =
* Bugfix.

= 0.6.1 =
* Improved attachment path handling with CDN support
* Better compatibility with other plugins
* Other bugfix

= 0.5.6 =
* Bugfix.

= 0.5.5 =
* 6.6 compatibility.

= 0.5.4 =
* Bugfix.

= 0.5.3 =
* Updated deprecated filter.

= 0.5.2 =
* Bugfix and compatibility.

= 0.5.1 =
* Bugfix and compatibility.

= 0.5 =
* Filter for upload dir thanks to @effakt

= 0.4 =
* Bugfix.

= 0.3.2 =
* FIX for non thumb image (icon)

= 0.3.1 =
* FIX for non thumb image

= 0.3 =
* FIX not updated attachment after save
* FIX error after add attachment

= 0.2 =
* DANGER! Compatibility Gravity Forms 2.5+ - DON'T UPDATE IF YOU USE PREVIOUS VERSION

= 0.1.1 =
* Compatibility WordPress 5.7

= 0.1 =
* Initial release


== Upgrade Notice ==

= 0.6.3 =
Update compatibility versions (PHP/WP).

= 0.6.2 =
Bugfix.

= 0.6.1 =
Improved attachment path handling with CDN support
Better compatibility with other plugins
Other bugfix

= 0.5.6 =
Bugfix.

= 0.5.5 =
6.6 compatibility.

= 0.5.4 =
Bugfix.

= 0.5.3 =
Updated deprecated filter.

= 0.5.2 =
Bugfix and compatibility.

= 0.5.1 =
Bugfix and compatibility.

= 0.5 =
Filter for upload dir thanks to @effakt

= 0.4 =
Bugfix.

= 0.3.2 =
FIX for non thumb image (icon)

= 0.3.1 =
FIX for non thumb image

= 0.3 =
FIX not updated attachment after save
FIX error after add attachment

= 0.2 =
DANGER! Compatibility Gravity Forms 2.5+ - DON'T UPDATE IF YOU USE PREVIOUS VERSION

= 0.1.1 =
Compatibility WordPress 5.7

= 0.1 =
Initial release.

`<?php code(); // goes in backticks ?>`