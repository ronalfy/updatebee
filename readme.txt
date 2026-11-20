=== UpdateBee ===
Contributors: ronalfy
Tags: updates, automatic updates, plugins, themes, cron
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 8.2
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Control automatic updates for Core, plugins, themes, and translations. Schedule them and get email, log, and Slack reports.

== Description ==

UpdateBee is a single place to control automatic updates for WordPress Core, plugins, themes, and translations. Set a policy for each item, make exceptions where you need them, and choose when those updates are allowed to run.

= Control what gets updated =

Give individual plugins, themes, translations, and WordPress Core their own automatic update setting. Allow some to update on their own and leave others for a manual update.

For example, you can enable Core version updates, but disable it for the major point releases that don't traditionally include security updates.

= Schedule updates =

Set when update checks run instead of relying on the default WordPress interval. The scheduler shows what is queued and lets you change it for Core, plugins, and themes.

Advanced Crontab's are shown and supported should you choose (or your host choose) to disable the default Cron that ships with WordPress.

= Delay updates =

Hold new releases for a period you choose, so there is time for follow-up fixes if an initial version needs frequent refreshes via patches. If an upgrade notice contains a keyword you set, that update can skip the delay (for example, a security fix in the upgrade notice of a plugin).

= Skip a version =

Skip a plugin or theme version that you know is a problem, without turning automatic updates off for that item or having to block it permanently. For example, a 7.0 version of a plugin is known to have compatbilitiy problems with your checkout screen, and you'd like to hold off until the next point release without blocking the plugin from updates outright.

= Roll back an update =

Return a plugin or theme hosted on WordPress.org to an earlier version that is still available there. Rollback does not replace backups. Keep regular backups, especially before major updates. A rollback cannot undo every compatibility problem or database change.

= Logs =

Each update attempt is recorded with the previous version, the new version, and whether the update succeeded or was blocked, skipped, or delayed.

Logs can be cleared and exported at any time by an administrator (or network admin on Multisite).

= Email and Slack reports =

Email reports can summarize completed updates, successes, and failures. You can also receive a periodic email report of updates that are still pending. When you save a Slack webhook, the same kind of report can be delivered via Slack.

= Import, export, and branding =

Export your settings and import them on another site. Branding fields let you set the name, description, author, and URL shown for the plugin, allowing for agency-friendly settings.

= Where to find UpdateBee =

On a single site, UpdateBee is under the Tools menu labeled Automatic Updates. On Multisite, it is under the Settings menu in the network admin, and a shortcut is available in the admin menu.

= Privacy =

Logs and settings stay on your site. Email is sent through WordPress mail. Slack receives report text only after an administrator saves a webhook URL and explicitly enables Slack reporting for logs and some email-first reports such as available updates.

= Open Source =

Source code can be <a href="https://github.com/ronalfy/updatebee/">found on GitHub</a>.

= Credits =

UpdateBee is a fork of <a href="https://wordpress.org/plugins/stops-core-theme-and-plugin-updates/">Easy Updates Manager</a> and <a href="https://wordpress.org/plugins/companion-auto-update/">Companion Auto Update</a>, and has helpful importers for both.

== Installation ==

1. Install UpdateBee from the Plugins screen, or upload the `updatebee` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin. On Multisite, network-activation is required.
3. On a single site, open UpdateBee under Tools. On Multisite, open it under the Settings menu in the network admin, or use the shortcut in the admin menu bar. Then choose what may update automatically.

An optional admin bar menu can be enabled under UpdateBee's Advanced settings tab, which can be shown on the frontend and backend of your site.

Helpful commands (CMD+K on a Mac, CTRL+K on Windows) can help you force and refresh updates.

== Frequently Asked Questions ==

= Can I choose which plugins and themes update automatically? =

Yes. You can allow some plugins and themes to update automatically and leave others for a manual update. The same per-item control covers WordPress Core and translations.

= Can I control when automatic updates run? =

Yes. UpdateBee schedules when WordPress runs update checks, so updates can follow your schedule instead of the default WordPress interval. The scheduler shows what is queued and lets you change it.

For convenience, there is a list of available Cron processes next to the Cron schedules so you can see at a glance what's queued.

= Can I delay updates and still let a security release through? =

Yes. You can delay automatic updates so there is time for follow-up fixes. UpdateBee can also scan the upgrade notice for keywords you choose. When a notice matches, that update skips the delay.

= Can I skip one plugin or theme version? =

Yes. If a particular version has a bug or a compatibility problem, you can skip that version without turning automatic updates off for that plugin or theme or by blocking the update permanently.

= Can I roll a plugin or theme back? =

Yes, for plugins and themes hosted on WordPress.org. UpdateBee can return a supported package to a previous version that is still available. Keep regular backups, especially before major updates. A rollback cannot recover from every compatibility problem or database change.

= Does UpdateBee keep a record of updates? =

Yes. UpdateBee logs what was updated and when, including the previous version, the new version, and whether the update succeeded or was blocked. Logs can be cleared at any time, but leaving them populated makes the reports more accurate as that's where the data comes from for Slack and email reports.

= Can I get email or Slack reports? =

Yes. Email reports can tell you about completed updates and updates that are still available. Successs and failure emails are built separately, so you only receive what you need. Slack reports use a webhook URL that you save in the settings, and they are sent only when that webhook is set.

For logs, you must have a configured Slack webhook and also enable it via the log's settings. Other reports such as fatal notifications and available updates also can be sent via Slack (after opt-in).

= Does this plugin replace the WordPress update system? =

No. UpdateBee uses the WordPress update system and changes when automatic updates are allowed to run and via what degree.

= Where are the settings stored? =

UpdateBee stores its settings as network options and writes update history to its own database tables. UpdateBee doesn't delete its settings automatically, and must be deactivated with data deletion under the Advanced tab in UpdateBee's settings.

In the event you just need to wipe the slate clean, a Reset option is also available under Advanced.

= What are recommended Backup plugins for use with UpdateBee =

<a href="https://wordpress.org/plugins/updraftplus/">UpdraftPlus</a> is a reliable backup solution that works across a variety of environments. 

Also of note is <a href="https://wordpress.org/plugins/wp-health/">WP Umbrella</a>, which can act as a backup, security, and even an update solution of its own.

Be sure to check with your host as well, as most managed hosts perform their own periodic backups.

Be sure to download and test your backups regularly.

= Is UpdateBee affiliated with Easy Updates Manager, UpdraftPlus, or other update plugins? =

No. UpdateBee is an independently developed fork by Ronald Huereca of Easy Updates Manager and Companion Auto Update. 

Ronald Huereca is a former Easy Updates Manager developer, but no longer has any affiliation with the plugin or UpdraftPlus (the owners of Easy Updates Manager).

Ronald also helped contribute and test automatic upgrades in WordPress 5.5, which introduced plugin and theme updates to WordPress Core without the need of third-party plugins.

If you need a plugin lighter than EUM or UpdateBee, <a href="https://wordpress.org/plugins/companion-auto-update/">Companion Auto Update</a> is a solid alternative.

== Changelog ==

= 1.0.0 =
* Initial release.
