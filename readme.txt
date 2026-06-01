=== Paid Memberships Pro - Plugin Licensing ===
Contributors: salzano
Tags: paid memberships pro, pmpro, plugin sales
Requires at least: 4.0
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 
An add-on for Paid Memberships Pro. Sell WordPress plugins and provide automatic updates to customers.


== Description ==
 
Paid Memberships Pro - Plugin Licensing adds WordPress plugin file management to Membership Levels. This powerful add-on that transforms your WordPress site into a plugin marketplace. Attach downloadable plugin files to membership levels, automatically generate license keys for each user, and deliver seamless updates directly to your customers' WordPress dashboards.

When customers purchase a membership level that includes a plugin download, they receive both the plugin file and a unique license key. This license key enables automatic updates through WordPress's built-in update system, providing seamless updates.

= Features =

* Attach plugin .zip files to Paid Memberships Pro membership levels
* Automatically generate unique license keys for each user
* One license key per customer validates all their plugin purchases
* Deliver automatic updates through WordPress's native update system
* Site activation management - customers can add and remove sites from their licenses
* Configurable activation limits per plugin
* Secure file downloads protected by membership access
* Automatic creation of membership-account/sites/ page for site management with [pmpro_pl_sites]
* Integrates with Paid Memberships Pro's membership account pages

= Requirements =

* Paid Memberships Pro
* WordPress 4.0 or higher
* PHP 7.2 or higher

== Installation ==
 
1. Install and activate Paid Memberships Pro
2. Upload the plugin files to the `/wp-content/plugins/pmpro-plugin-licensing` directory, or install the plugin through the WordPress plugins screen directly
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to Memberships > Settings > Plugin Licensing to configure the plugin
5. Edit a membership level and attach plugin files using the "Add File" button
6. Set activation limits and configure other settings for each attached plugin
7. Use the include update and activate classes to connect plugins to the store
8. Customers who purchase memberships will receive download links and license keys


== Frequently Asked Questions ==
 
= What is Paid Memberships Pro? =

Paid Memberships Pro is a WordPress membership plugin that allows you to create membership levels, restrict content, and accept payments. This add-on extends PMPro to sell and update WordPress plugins.

= How do license keys work? =

Each customer receives one license key when they make their first purchase. This single key validates all plugins they have purchased, regardless of how many different plugins they buy.

= How do automatic updates work? =

Customers enter their license key in their WordPress dashboard. The plugin connects to your store via REST API to check for updates. When updates are available, they appear in the WordPress Updates screen just like other plugins.

= Can I limit how many sites a customer can activate? =

Yes. When managing files in a Membership Level, you can enter an activation limit. Customers can manage their active sites from the membership-account/sites/ page.

= What happens if a customer cancels their membership? =

Their license key becomes inactive, and they will no longer receive updates. However, they can continue using the plugin files they have already downloaded unless your plugin is customized to perform license checks and gate features.

= Can customers purchase multiple plugins? =

Yes. Make sure the membership levels are part of a Level Group that allows multiple selections. This allows customers to purchase additional plugins without losing access to previous purchases.

= How do I add a new version of a plugin? =

Edit the Membership Level, expand Other Settings, find the file in the table, and press the Replace button. Choose or upload the new version of the plugin. Save the Membership Level.

== Changelog ==

= 1.1.0 =
* [Added] Adds plugin screenshots and expands the documentation with setup.
* [Changed] First version available to the public.

= 1.0.78 =
* [Added] Adds an updater so this plugin can get updates from breakfastco.xyz.

= 1.0.77 =
* [Added] First that works.


== Upgrade Notice ==

= 1.1.0 =
This release adds screenshots and more complete documentation for setup, operations, and the client plugin REST API.

= 1.0.77 =
Sell WordPress plugins through Paid Memberships Pro with automatic license key generation and update delivery.
