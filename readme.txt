=== GrabWP Tenancy ===
Contributors: taicv
Tags: multi-tenant, tenancy, isolation, routing, domains
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Plugin URI: https://grabwp.com
Donate link: https://grabwp.com

Multi-tenant WordPress with shared MySQL, domain routing, and isolated uploads.

== Description ==

GrabWP Tenancy provides the foundation for multi-tenant WordPress with essential isolation features. This base plugin focuses on cost-effective multi-tenancy with shared resources and basic tenant separation.

**📖 Complete Documentation**: [grabwp.com](https://grabwp.com)  
**💻 Source Code**: [GitHub Repository](https://github.com/taicv/grabwp-tenancy)  
**🐛 Report Issues**: [GitHub Issues](https://github.com/taicv/grabwp-tenancy/issues)

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

= Where can I get help? =

* **Documentation**: Visit [grabwp.com](https://grabwp.com) for complete guides and tutorials
* **Support Forum**: Use the [WordPress.org support forum](https://wordpress.org/support/plugin/grabwp-tenancy/) for community help
* **Technical Issues**: Report bugs on [GitHub Issues](https://github.com/taicv/grabwp-tenancy/issues)


== Screenshots ==

1. Adding one domain per tenant
2. Support multiple domains per tenant
3. Simple listing all tenants


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/grabwp-tenancy` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add the following line to your wp-config.php file:
   `require_once __DIR__ . '/wp-content/plugins/grabwp-tenancy/load.php';`

**📖 Need detailed setup instructions?** Visit our [complete documentation](https://grabwp.com) for step-by-step guides and troubleshooting.

== Changelog ==

= 1.0.3 =
* **Major Enhancement**: Added comprehensive early loading system with load-helper.php
* **Security Improvements**: Enhanced input sanitization and validation functions for early loading
* **Path Management**: Introduced centralized Path Manager with backward compatibility support
* **WordPress Compliance**: Improved path structure with fallback to WordPress-compliant uploads directory
* **CLI Support**: Added command-line interface support for tenant operations
* **Performance**: Optimized tenant detection with caching and reduced file system calls
* **Backward Compatibility**: Maintained support for existing wp-content/grabwp structure

= 1.0.2 =
* Improved tenant management interface
* Direct login button to tenant from main site admin (If plugin also activated on tenant)
* No longer access to plugin admin page and menu from tenant's admin dashboard

= 1.0.1 =
* Refactored core plugin for improved tenant management and protocol handling
* Added admin notice registration for better user feedback in the admin area
* Defined GRABWP_TENANCY_LOADED constant for reliable plugin load detection
* Added translation support by loading the plugin text domain on initialization
* Added Vietnamese language support

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