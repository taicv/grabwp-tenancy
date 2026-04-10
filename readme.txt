=== GrabWP Tenancy ===
Contributors: taicv
Tags: multi-tenant, multisite, multi site, multi domain, saas
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Plugin URI: https://grabwp.com
Donate link: https://grabwp.com

Multi-tenant WordPress with shared MySQL, domain and path routing, and isolated uploads.

== Description ==

https://www.youtube.com/watch?v=SAd_QeUZEDw

= WORDPRESS MULTISITE ALTERNATIVE - ENABLE MULTI-TENANT AT NO COST =

GrabWP Tenancy provides the foundation for multi-tenant WordPress with essential isolation features. Host your tenants effortlessly on a single domain using our new **Path-Based Routing** (e.g., example.com/site/abcdef), or map them to full custom domains. It is the perfect, lightweight, drop-in replacement for WordPress Multisite built for simplicity and scale.

**📖 Complete Documentation**: [grabwp.com](https://grabwp.com)  
**💻 Source Code**: [GitHub Repository](https://github.com/grabwp/grabwp-tenancy)  
**🐛 Report Issues**: [GitHub Issues](https://github.com/grabwp/grabwp-tenancy/issues)

= Core Features =

* **Shared MySQL Database**: Cost-effective tenant isolation using unique table prefixes
* **Separated Upload Directories**: Each tenant gets isolated upload directories
* **Shared Themes & Plugins**: All tenants share the same themes and plugins for simplicity
* **Flexible Routing Options**: Choose between domain-based routing or our new **path-based (subdirectory) routing** for an effortless setup without DNS changes.
* **Basic Admin Interface**: Simple tenant management through WordPress admin
* **Early Initialization**: Plugin loads before WordPress core hooks

= Need More? Upgrade to Pro =

Managing multiple client sites and need enterprise-grade isolation? **[GrabWP Tenancy Pro](https://grabwp.com/pro/)** adds:

* **Dedicated MySQL or SQLite** database per tenant — complete data isolation, zero cross-tenant risk
* **Full wp-content separation** — isolated themes, plugins & uploads per tenant
* **AJAX backup & restore** — 7-step backup, 8-step restore with real-time progress UI
* **Cross-database migration** — move tenants freely between shared MySQL, dedicated MySQL, and SQLite
* **Extension sync & management** — sync plugins/themes with filesystem, switch between symlink and copy installs
* **Broken symlink auto-repair** — one-click detection and repair
* **Custom tenant data location** — store content outside wp-content/uploads using settings or wp-config.php
* **Per-tenant config files** — each tenant gets its own config; new tenants inherit master defaults

**From $9.99/month** — all plans include every feature. Use code `EARLYBIRDPRO` for 20% off.

👉 **[Get Pro Now](https://grabwp.com/pro/)**

== Frequently Asked Questions ==

= Does this plugin work with WordPress multisite? =

No, this plugin provides multi-tenancy functionality without requiring WordPress multisite. It creates tenant isolation through domain-based or path-based routing and database prefixes.

= Do I need a custom domain for every tenant? =

No! With our new path-based routing feature, you can completely bypass complex domain and DNS configurations. You can host tenants dynamically on subdirectories underneath your main site (e.g., `yoursite.com/client-a`).

= Can I use custom domains for tenants? =

Yes, you can configure custom domains for each tenant through the admin interface or by editing the tenant mapping file.

= Is this plugin compatible with other plugins? =

The plugin is designed to be compatible with most WordPress plugins. However, plugins that directly access database tables may need to be configured to work with tenant prefixes.

= Where can I get help? =

* **Documentation**: Visit [grabwp.com](https://grabwp.com) for complete guides and tutorials
* **Support Forum**: Use the [WordPress.org support forum](https://wordpress.org/support/plugin/grabwp-tenancy/) for community help
* **Technical Issues**: Report bugs on [GitHub Issues](https://github.com/grabwp/grabwp-tenancy/issues)

= How is this different from WordPress Multisite? =

WordPress Multisite shares one database, one set of plugins, and one set of themes across all sites. GrabWP Tenancy gives each tenant true isolation — separate table prefixes, separate upload directories, and with Pro, completely dedicated databases and per-tenant plugins/themes. No network admin complexity, no shared-database risks.

= Can I manage 50+ client sites with this? =

Yes. GrabWP Tenancy is built for WordPress freelancers and agencies managing multiple client sites from a single installation. The admin interface handles tenant CRUD, and Pro adds backup/restore per tenant for easy management at scale.

== Screenshots ==

1. Adding one domain per tenant
2. Support multiple domains per tenant
3. Simple listing all tenants


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/grabwp-tenancy` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
**📖 Need detailed setup instructions?** Visit our [complete documentation](https://grabwp.com) for step-by-step guides and troubleshooting.

== Changelog ==

= 1.0.7 =
- Major: Introducing **Path-Based Routing**! You can now host tenants on subdirectories/paths (e.g., `example.com/site/abcdef`) without requiring separate domains. This completely eliminates the need for complex domain mappings and serves as a true, lightweight replacement for WordPress Multisite.
- Enhance: Added comprehensive `.htaccess` diagnostic admin notices specifically tailored for path-based routing support.
- Enhance: Centralized configuration and diagnostic tools into a transparent, read-only Status Page UI with manual fallback instructions for environment issues.
- Refactor: Streamlined the installation, uninstallation, and environment-fixing processes into a single `GrabWP_Tenancy_Installer` class.
- Security: Improved code compliance by integrating complete nonce verification on sensitive administrative handlers and securely transitioning to `wp_is_writable()`.
- Fix: Resolved PHP fatal errors (e.g., `Class Not Found`) relating to the class autoloader sequence during the initial activation process.
- Quality: Standardized codebase formatting for consistent line endings (CRLF to LF) and file encoding across all files.

= 1.0.6 =
- New: Dedicated **Status** admin page with system information, file structure, database config, content isolation, and domain routing details (moved out of Settings page)
- New: One-click **Auto Install MU-Plugin** button with AJAX handler — auto-creates the must-use plugin file, or shows copy-to-clipboard fallback when the directory is not writable
- New: One-click **Auto Install to wp-config.php** button with AJAX handler — injects the `load.php` require line before the stop-editing marker, or shows copy-to-clipboard fallback when `wp-config.php` is not writable
- New: Admin notices for missing MU-plugin and missing `wp-config.php` loader, shown only on plugin pages, with writability-aware UI (auto-install vs. manual copy)
- New: Pro Features section added to the plugin readme
- Improved: Default tenant capability settings are now **enabled** (Disallow File Mods, Disallow File Edit, Hide Plugin Management, Hide Theme Management) for stronger security out of the box
- Improved: Added fallback definition of `grabwp_tenancy_validate_tenant_id()` in Path Manager so validation works even when `load-helper.php` is not loaded
- Improved: Tenant edit page title now displays the tenant ID for clarity
- Improved: Moved AJAX nonces (`muPluginNonce`, `loaderNonce`) into localized admin script data
- Improved: Refactored JS — extracted `bindCopyButton()` helper, added `initMuPluginInstall()` and `initLoaderInstall()` handlers in `grabwp-admin.js`
- Improved: Streamlined `load-helper.php` base-dir resolution logic and added `GRABWP_TENANCY_DIRS_FROM_PLUGIN` constant
- Improved: Renamed local variables in tenant views to use `grabwp_tenancy_` prefix to avoid potential conflicts
- Improved: Tested up to WordPress 6.9

= 1.0.5 =
- New: Settings page for tenant capability controls (Disallow File Mods, Disallow File Edit, Hide Plugin Management, Hide Theme Management, Hide GrabWP Plugins)
- Fix: Tenant ID starting with a number causes database errors on table prefix

= 1.0.4 =
- Enhance tenant management with a new list table for admin
- Implement logging functionality, and improve tenant deletion process with confirmation prompts.
- Refactor path management and database handling for better organization and security.
- Update translations and enhance admin UI elements for improved user experience.
- Refactor tenant initialization process to streamline functionality for tenant and main site.
- Remove deprecated asset loading class and configuration management.
- Enhance tenant context handling and improve upload directory management.
- Introduce hooks for pro plugin extensibility and improve code organization for better maintainability.


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
