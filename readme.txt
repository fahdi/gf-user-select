=== Gravity Forms User Select ===
Contributors: fahdi
Tags: gravity-forms, user-select, dropdown, forms, wordpress-users
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a "User Select" field type to Gravity Forms, allowing you to create dropdowns populated with WordPress users.

== Description ==

Gravity Forms User Select is a powerful WordPress plugin that extends Gravity Forms with a custom "User Select" field type. This field allows you to create dropdowns populated with WordPress users, making it easy to build forms that require user selection.

= Key Features =

* **Custom Field Type**: Adds a "User Select" field to Gravity Forms
* **Role Filtering**: Filter users by WordPress roles (Administrator, Editor, Author, etc.)
* **Multiple Display Formats**: Choose how user names appear (Display Name, First+Last, Username, Custom Template)
* **Auto-Selection**: Option to automatically select the current logged-in user
* **Searchable Dropdowns**: AJAX-powered search for sites with many users
* **Admin Settings**: Global configuration page for default settings
* **Cache Management**: Built-in caching for improved performance
* **Security**: Nonces, capability checks, and input sanitization
* **Performance**: Optimized queries and efficient data handling

= Use Cases =

* **Contact Forms**: Allow users to select a contact person
* **Assignment Forms**: Assign tasks to specific users
* **Approval Workflows**: Select approvers from user lists
* **Team Selection**: Choose team members for projects
* **User Management**: Create user selection interfaces

= Display Formats =

* **Display Name**: User's WordPress display name
* **First + Last**: First name + Last name combination
* **Username**: WordPress username
* **Custom Template**: Use placeholders like {first_name} {last_name} ({user_email})

= Security Features =

* **Nonce Verification**: CSRF protection for all AJAX requests
* **Capability Checks**: Proper permission validation
* **Input Sanitization**: All user inputs are sanitized
* **Output Escaping**: All outputs are properly escaped
* **SQL Injection Protection**: Uses WordPress APIs instead of raw SQL

= Performance Features =

* **Caching**: User lists are cached for improved performance
* **AJAX Search**: Lazy loading for large user lists
* **Optimized Queries**: Efficient database queries
* **Pagination**: Handles large user bases gracefully

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/gf-user-select` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Make sure Gravity Forms is installed and active
4. Go to Settings > User Select to configure global settings
5. Edit a Gravity Form and add a "User Select" field

== Frequently Asked Questions ==

= Does this plugin require Gravity Forms? =

Yes, this plugin requires Gravity Forms to be installed and active. It extends Gravity Forms with a new field type.

= What WordPress version is required? =

This plugin requires WordPress 5.0 or higher and PHP 7.4 or higher.

= Can I filter users by role? =

Yes, you can filter users by any WordPress role (Administrator, Editor, Author, Contributor, Subscriber) or create custom role combinations.

= How does the search functionality work? =

For sites with many users (100+ by default), the plugin automatically enables AJAX search. Users can search by name, email, or username.

= Is this plugin secure? =

Yes, the plugin follows WordPress security best practices including nonce verification, capability checks, input sanitization, and output escaping.

= Can I customize how user names appear? =

Yes, you can choose from several display formats or create custom templates using placeholders.

= Does the plugin work with multisite? =

Yes, the plugin is fully compatible with WordPress multisite installations.

== Screenshots ==

1. User Select field in Gravity Forms editor
2. Field settings configuration
3. Global plugin settings page
4. Searchable dropdown in action
5. Form with User Select field

== Changelog ==

= 1.0.0 =
* Initial release
* Custom User Select field type for Gravity Forms
* Role-based user filtering
* Multiple display formats (Display Name, First+Last, Username, Custom Template)
* Auto-selection for current user
* Searchable dropdowns with AJAX
* Admin settings page
* Cache management
* Security features (nonces, capability checks, sanitization)
* Performance optimizations
* Comprehensive documentation

== Upgrade Notice ==

= 1.0.0 =
Initial release of Gravity Forms User Select plugin.

== Privacy Policy ==

This plugin does not collect, store, or transmit any personal data. It only uses existing WordPress user data that is already available to the site administrator.

The plugin may cache user information temporarily for performance purposes, but this data is stored locally on your server and is not transmitted to any external services.

== Support ==

For support, feature requests, or bug reports, please visit the [plugin's GitHub repository](https://github.com/fahdi/gf-user-select) or create an issue on the WordPress.org support forums.

== Development ==

The plugin is open source and contributions are welcome. Visit the [GitHub repository](https://github.com/fahdi/gf-user-select) to contribute code, report issues, or suggest features.

