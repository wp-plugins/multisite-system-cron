=== MultiSite System Cron ===
Contributors: pauiglesias, blogestudio
Tags: multisite, cron, crontab, linux, system, schedule, scheduling, timer, timing, wp-cron
Requires at least: 3.3.2
Tested up to: 4.2.2
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

For WordPress MultiSite installs, allows accurate and private system crontab requests instead of classic WP-Cron.

== Description ==

The WordPress Cron implementation needs web visits to be triggered regularly.

If you don't want to depend on the possible visits, or need a more accurate cron requests, it's easy to setup the Linux crontab to replace the WP-Cron system.

Simply disable the WP-Cron adding the constant DISABLE_WP_CRON to the wp-config.php file, and create a new crontab line with <code>wget</code> or <code>curl</code> commands fetching the wp-cron.php URL of your blog.

But for WordPress MultiSite installs you will need one crontab line for each blog and, if you have many blogs, it is not simple to maintain and configure all the cron calls.

Also, there is a risk to overlap requests, possibly affecting server performance or WordPress behaviour.

The aim of this plugin is to provide a method to implement Linux cron requests for WordPress MultiSite with only one line in the Linux crontab.

This only one request points to the main blog cron URL, but with special arguments, ensuring a controlled and private cron requests.

Then, from the main blog is performed a propagation process, calling one by one all the network blogs (also with privacy URL arguments).

In the network settings of this plugin you can setup the frecuency of this process, the time between each blog cron calls, estimate all the process duration to avoid overlapping, consulting amount of time of all cron processes, etc.

Obviously, this configuration depends of your number of blogs, the desired frecuency of cron calls and/or the performance capabilities of your server, so you will need some testing to achieve the proper parameters.

== Installation ==

1. Unzip and upload multisite-system-cron folder to the `/wp-content/plugins/` directory
1. From the network administration area, activate the plugin through the 'Plugins' menu in WordPress
1. Go to the network menu Settings > MultiSite System Cron to configure this plugin

== Frequently Asked Questions ==

= It works with no-multisite installs? =

No, it's not necessary. You can get the same result with a single line of crontab.

== Screenshots ==

1. Settings screen

== Changelog ==

= 1.0 =
Release Date: July 14th, 2015

* First and tested released until WordPress 4.2.2
* Tested code from WordPress 3.3.2 version.

== Upgrade Notice ==

= 1.0 =
Initial Release.