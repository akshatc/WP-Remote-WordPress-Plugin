=== The WP Remote WordPress Plugin ===
Contributors: jeramynirodha, bmett, humanmade, willmot, joehoyle, danielbachhuber, mattheu, pauldewouters, cuvelier, tcrsavage
Tags: wpremote, remote administration, multiple wordpress
Requires at least: 3.0
Tested up to: 4.9
Stable tag: 2.8.4.1

WP Remote is a free web app that enables you to easily manage all of your WordPress powered sites from one place.

== Description ==

The WP Remote WordPress Plugin works with [WP Remote](https://wpremote.com/) to enable you to remotely manage and update all your WordPress sites.

= Features =

* Free to monitor and update an unlimited number of sites.
* Track and update all of your WordPress sites from one place.
* Track and update all of your WordPress plugins and themes from one place.
* Schedule automatic backups to AWS, SFTP, FTP.
* Perform manual backups of your WordPress database and files.
* Download previous backups from the one place.
* Install and activate plugins and themes from the one place.
* Early beta access to [maekit](https://maek.it/) web design business platform.

= Support =

You can email us at support@wpremote.com for support.

== Installation ==

1. Install The WP Remote WordPress Plugin either via the WordPress.org plugin directory, or by uploading the files to your server.
2. Activate the plugin.
3. Sign up for an account at wpremote.com and add your site.

== Frequently Asked Questions ==

** I've forgotten my password **
Use the “I’ve forgotten my password” link on the log-in screen to generate an email with a link to reset your password.

https://wpremote.com/login/lost-password/
 
** How do I fix the “Does not appear to be a valid URL” message? **

1. If the domain name has been typed incorrectly:
The easiest way to ensure you have the correct domain name is to open your site in a different browser window and then copy and paste the site address.

2. If you have made recent changes to your DNS/Nameservers records:
If this is the case then just give it a little more time and try again later.
 
**What if I want to back up my site to another destination?**
 
You can also store your backups on your own Amazon S3 or you can upload backups to your own server via FTP or SFTP.
 
**How do I restore my site from a backup?**
 
WP Remote does not provide an automated way to restore your site. We recommend downloading a copy of your backup, unzipping it and then uploading to your site's server via FTP/SSH. Database importing can be done via your PHPMyAdmin interface or a similar tool - Your database backup can be found in the root folder of your downloaded backup zip.
 
**Further Support & Feedback**

General support questions should be posted in the <a href="http://wordpress.org/support/plugin/wpremote">WordPress support forums.</a>

You can email us at support@wpremote.com for support.

== Screenshots ==

1. The WP Remote dashboard at wpremote.com
2. See all of the plugins and themes needing update across all Sites in one view.
3. Download nightly Automatic Backups.

== Changelog ==

#### 2.8.4.1 (3 December 2017)

* Correct handling of up_to_date error

#### 2.8.4 (3 December 2017)

* Modify error message response in certain situations

#### 2.8.3 (21 November 2017)

* Add endpoint to validate plugin update
* Improved error handling
* Fix 'Clear Api' redirect

#### 2.8.2 (25 October 2017)

* Change settings page function name for compatibility
* Allow the WP Remote API key to be updated from CLI

#### 2.8.1 (10 October 2017)

* Add link to clear API key from the plugin settings page.
* Prevent WP Remote from clearing the API key on deactivation
* Clear API key on uninstall

#### 2.8.0.1 (31 August 2017)

* Bug fix for PHP < 5.4

#### 2.8.0 (30 August 2017)

* Modify plugin activation and return plugin active status to WP Remote

#### 2.7.9.2 (25 August 2017)

* Bug fix for php 5.4 and lower

#### 2.7.9.1 (25 August 2017)

* Add Fallback method for when current user isn't found

#### 2.7.9 (22 August 2017)

* Query DB to find an admin user to run updates

#### 2.7.8 (20 July 2017)

* Replaced mysql class and functions with mysqli

#### 2.7.7 (20 April 2017)

* Fixed fatal error with backup location

#### 2.7.6 (18 Sept 2014)

* Fixed issue with plugins not being reactivated when updated on an MU WordPress install
* Fixed issue with child themes reporting an available update whenever the parent theme has an available update

#### 2.7.5 (10 Sept 2014)

* Fixed WordPress 4.0 issues with json_encode of a WP_Error object which would result in malformed responses from the WP_Remote WordPress plugin
* Added FAQ to readme
* Updated incompatible plugins list

#### 2.7.3 (12 May 2014)

* Added the ability to return basic content information for the site - post count, user count, plugin count etc.
* Updated contribution guidelines

#### 2.7.2 (22 January 2014)

* Misc improvements to the accuracy of the backup restart mechanism.
* Inline styles to insure the API key prompt always appears, even if a theme or plugin may hide admin notices.

#### 2.7.1 (23 December 2013)

* Bug fix: Restore plugin and theme installation mechanism.
* Bug fix: On some hosts where `getmypid()` wasn't permitted, the backup process would be prematurely reported as killed.

#### 2.7.0 (19 November 2013)

* Improved durability of backups where the backup process can take more than 90 seconds.
* New API support for posts, comments, and fixed support for users (oops).
* Reporting and update integration with premium plugins that support ManageWP's API implementation.
* Plugin, theme, and core updates now respect the `DISALLOW_FILE_MODS` constant.

#### 2.6.7 (27 October 2013)

* API improvement: specify database- and file-only backups
* Bug fix: Make the backup download URL accessible on Apache servers again. The protective .htaccess was being generated with the wrong key.

#### 2.6.6 (23 October 2013)

* Bug fix: Due to some files moving around, WP Remote wasn't able to properly update the current version of the plugin.

#### 2.6.5 (23 October 2013)

* Incorporated a more reliable plugin re-activation process after update.
* Bug fix: Properly delete backup folders for failed backups. Users may want to look inside of `/wp-content/` for any folders named as `*-backups`. If they were created by WP Remote, they can be safely deleted.
* Bug fix: Log the proper fields in history when a new user is created.

#### 2.6.4 (2 October 2013)

* Misc API improvements for Premium.
* Bug fix: Disable all premium plugin and theme updates. Causing fatals too often.
* Bug fix: Restore FTP-based core, theme, and plugin updates by properly accessing the passed credentials.

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

## Contribution guidelines ##

see https://github.com/MyWorkAus/WP-Remote-WordPress-Plugin/blob/master/CONTRIBUTING.md
