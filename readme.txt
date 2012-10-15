=== The WP Remote WordPress Plugin ===
Contributors: humanmade, joehoyle, mattheu, tcrsavage, willmot
Tags: wpremote, remote administration, multiple wordpress
Requires at least: 2.9
Tested up to: 3.5
Stable tag: 2.3.1

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