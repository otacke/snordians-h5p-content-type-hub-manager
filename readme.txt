=== Sustainum H5P Content Type Hub Manager ===
Contributors: otacke
Tags: h5p, catharsis
Requires at least: 4.0
Tested up to: 6.7
Stable tag: 1.0.0
License: MIT
License URI: https://github.com/otacke/sustainum-h5p-content-type-hub-manager/blob/master/LICENSE

Allows to use alternative H5P Content Type Hub Servers.

== Description ==
The "Sustainum H5P Content Type Hub Manager" plugin for WordPress allows to set an alternative H5P Content Type Hub Server to get H5P contents from. It also offers additional functionality related to the server that is used.

== Install ==

=== Upload ZIP file ===
1. Go to https://github.com/otacke/sustainum-h5p-content-type-hub-manager/releases.
2. Pick the latest release (or the one that you want to use) and download the
   `sustainum-h5p-content-type-hub-manager.zip` file.
3. Log in to your WordPress site as an admin and go to _Plugins > Add New Plugin_.
4. Click on the _Upload Plugin_ button.
5. Upload the ZIP file with the plugin code.
6. Activate the plugin.

== Configure ==


```
define( ‘ALTERNATE_WP_CRON’, true );
```
