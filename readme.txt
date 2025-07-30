=== GrabWP Tenancy ===
Contributors: taicv
Tags: multi-tenant, tenancy, isolation, routing, domains
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multi-tenant WordPress with shared MySQL, domain routing, and isolated uploads.

== Description ==

GrabWP Tenancy provides the foundation for multi-tenant WordPress with essential isolation features. This base plugin focuses on cost-effective multi-tenancy with shared resources and basic tenant separation.

= Core Features =

* **Shared MySQL Database**: Cost-effective tenant isolation using unique table prefixes
* **Separated Upload Directories**: Each tenant gets isolated upload directories
* **Shared Themes & Plugins**: All tenants share the same themes and plugins for simplicity
* **Domain-based Routing**: Automatic tenant identification based on domain names
* **Basic Admin Interface**: Simple tenant management through WordPress admin
* **Early Initialization**: Plugin loads before WordPress core hooks


== Frequently Asked Questions ==

= Does this plugin work with WordPress multisite? =

No, this plugin provides multi-tenancy functionality without requiring WordPress multisite. It creates tenant isolation through domain-based routing and database prefixes.

= Can I use custom domains for tenants? =

Yes, you can configure custom domains for each tenant through the admin interface or by editing the tenant mapping file.

= Is this plugin compatible with other plugins? =

The plugin is designed to be compatible with most WordPress plugins. However, plugins that directly access database tables may need to be configured to work with tenant prefixes.


== Screenshots ==

1. Adding one domain per tenant
2. Support multiple domains per tenant
3. Simple listing all tenants


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/grabwp-tenancy` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add the following line to your wp-config.php file:
   `require_once __DIR__ . '/wp-content/plugins/grabwp-tenancy/load.php';`

== Changelog ==

= 1.0.0 =
* Initial release
* Basic multi-tenant functionality
* Domain-based routing
* Admin interface for tenant management
* Shared MySQL with tenant prefixes
* Separated upload directories

== Upgrade Notice ==

= 1.0.0 =
Initial release of GrabWP Tenancy.