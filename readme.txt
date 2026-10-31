=== UpdateBee ===
Contributors: ronalfy
Tags: updates, automatic updates, plugins, themes, cron
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 8.2
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Choose which WordPress core, plugin, theme, and translation updates run automatically, and decide when those updates are allowed to run.

== Description ==

UpdateBee gives a network or site administrator a single place to control automatic updates. You can allow, delay, or block updates for WordPress core, plugins, themes, and translations, and you can schedule the update check instead of relying on the default WordPress cron interval.

The plugin records each update attempt, including the previous version, the new version, and whether the update succeeded or was blocked. Optional email and Slack reports can summarize completed updates and updates that are still available.

UpdateBee is a multisite-capable plugin. Settings are managed from the network admin or under the Tools menu on single-site.

Source code can be <a href="https://github.com/ronalfy/updatebee">found on GitHub</a>.

Credits: UpdateBee is a fork of <a href="https://wordpress.org/plugins/stops-core-theme-and-plugin-updates/">Easy Updates Manager</a>.

== Installation ==

1. Upload the `updatebee` folder to the `/wp-content/plugins/` directory.
2. Network-activate the plugin through the Network Admin Plugins screen.
3. Open UpdateBee in the network admin and choose which updates should run automatically.

== Frequently Asked Questions ==

= Does this plugin replace the WordPress update system? =

No. UpdateBee uses the WordPress update system and changes when automatic updates are allowed to run.

= Where are the settings stored? =

UpdateBee stores its settings as network options and writes update history to its own database tables.

== Changelog ==

= 1.0.0 =
* Initial release.
