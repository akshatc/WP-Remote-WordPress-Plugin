=== The WP Remote WordPress Plugin ===
Contributors: humanmade, willmot, joehoyle, danielbachhuber, mattheu, pauldewouters, cuvelier, tcrsavage
Tags: wpremote, remote administration, multiple wordpress
Requires at least: 3.0
Tested up to: 3.6
Stable tag: 2.6.3

WP Remote is a free web app that enables you to easily manage all of your WordPress powered sites from one place.

== Description ==

The WP Remote WordPress Plugin works with [WP Remote](https://wpremote.com/) to enable you to remotely manage all your WordPress sites.

= Features =

* Track all your WordPress sites from one place.
* Track the WordPress version each site is running and easily update.
* Track all your plugins and themes and 1 click update them.
* Free to monitor and update an unlimited number of sites.
* Back up your database and files.

= Support =

You can email us at support@wpremote.com for support.

== Installation ==

1. Install The WP Remote WordPress Plugin either via the WordPress.org plugin directory, or by uploading the files to your server.
2. Activate the plugin.
3. Sign up for an account at wpremote.com and add your site.

== Changelog ==

#### 2.6.3 (10 September 2013)

* Bug fix: Disabled updating BackupBuddy through WP Remote for BackupBuddy v4.1.1 and greater. BackupBuddy changed its custom update mechanism (as it's a premium plugin), which caused the WP Remote plugin not to function properly.

#### 2.6.2 (2 September 2013)

* Bug fix: Reactivating plugin after plugin upgrade.

#### 2.6.1 (26 August 2013)

* Add multiple API keys to your WP Remote plugin with a `wpr_api_keys` filter if you'd like to use more than WP Remote account with the site.
* Plugin now supports localization. Please feel free to [submit your translation](http://translate.hmn.md/projects).
* Update `HM Backup` to v2.3
* Bug fix: Properly handle timestamp values in database backups.
* Bug fix: Use super randomized backup directories.

#### 2.6

* Change to using better hmac style authentication
* Fix error for sites running =< WordPress 3.1

#### 2.5

* Remove BackUpWordPress, backups are now handled by the `HM Backup` class.
* BackUpWordPress can now be used alongside WP Remote without issues.
* Exclude `.git` and `.svn` folders from backups automatically.

#### 2.4.12 & 2.4.13

* Upgrade bundled BackUpWordPress to 2.1.3.
* Fix an issue with Download Site on Apache servers.
* Set the correct location for the BackUpWordPress language files.

#### 2.4.10 + 2.4.11

* Plugin release shenaningans.

#### 2.4.9

* Pull in latest BackUpWordPress which fixes a possible Fatal error caused by `url_shorten` being called outside the admin.

#### 2.4.8

* Pull in latest BackUpWordPress which fixes a possible Fatal error caused by misc.php being included to early.

#### 2.4.7

* Update to BackUpWordPress 2.1
* Fix an issue that could cause backups to be run when they shouldn't have.
* Only hide the backups menu item if the site doesn't have any non wpremote schedules.
* Hide all BackUpWordPress admin notices.
* Fix the button styles for the save API Key button in WordPress 3.5
* Fix a possible warning in the WP_Filesystem integration, props @tillkruess (github).
* Support for updating the Pagelines premium theme, props @tillkruess (github)

#### 2.4.6

* Support for updating the BackupBuddy premium plugin, props @tillkruess (github)

#### 2.4.1 - 2.4.5

* Minor bug fixes

#### 2.4

* Backups are now powered by BackUpWordPress.
* The BackUpWordPress Plugin can no longer be run alongside WP Remote.
* Show a message if a security plugin is active which could affect WP Remote.
* Emphasise that you can deactivate the plugin to clear your API key.

#### 2.3.1

* PHP 5.2.4 compat.

#### 2.3

* WP_Filesystem support for servers which don't allow PHP direct filesystem access.
* Support for monitoring and updating Gravity Forms.

#### 2.2.5

* Implemented API call for Core updates

#### 2.2.4

* Fixed excludes for backups directories
* Started on remote core upgrades
* Fix memory limit in WP 3.1

#### 2.2.3

* Use WPR_HM_Backup instead of HM_Backup (fixes compatibilty with backupwordpress)

#### 2.2

* Start keeping a changelog of plugin changes
* Pass home_url, site_url and admin_url to WP Remote instead of guessing at them, fixes issues with the urls being wrong for non-standard WordPress installs
* Better error message when you have the wrong API key entered.