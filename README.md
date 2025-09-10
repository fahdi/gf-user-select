# Gravity Forms User Select

A WordPress plugin that adds a "User Select" field type to Gravity Forms, allowing you to create dropdowns populated with WordPress users.

## Quick Start

1. **Install & Activate** the plugin
2. **Edit a Gravity Form** and add a "User Select" field
3. **Configure** the field settings (roles, display format, etc.)
4. **Save** and test your form

## Basic Usage

### Adding a User Select Field

1. Go to **Forms** → **Edit Form**
2. Click **+ Add Field** → **Advanced Fields** → **User Select**
3. Configure the field settings:
   - **Label**: Field label (e.g., "Select User")
   - **Roles**: Choose which user roles to include
   - **Display Format**: How names appear in the dropdown
   - **Required**: Make the field required

### Display Formats

Choose how user names appear in the dropdown:

- **Display Name**: User's WordPress display name
- **First + Last**: First name + Last name
- **Username**: WordPress username
- **Custom**: Use a template like `{first_name} {last_name} ({user_email})`

### Role Filtering

Filter which users appear in the dropdown:

- **All Users**: Include all WordPress users
- **Specific Roles**: Select specific roles (e.g., Administrator, Editor)
- **Custom Capability**: Filter by WordPress capability
- **Include/Exclude**: Add or remove specific users by ID

## Advanced Features

### Auto-Selection

Automatically select the current user:

1. **Enable Auto-Selection** in field settings
2. **Choose Behavior**:
   - Always auto-select current user
   - Only for specific roles
   - Only if user has specific capability

### Searchable Dropdowns

For sites with many users:

1. **Enable Search** in field settings
2. **Set Threshold**: Minimum users to enable search (default: 100)
3. **Users can search** by name, email, or username

### Global Settings

Configure default behavior:

1. Go to **Settings** → **User Select**
2. **Set Defaults**:
   - Default roles to include
   - Default display format
   - Performance settings
   - Excluded users

## Field Settings

### Basic Settings
- **Field Label**: What users see
- **Description**: Help text below the field
- **Required**: Make selection mandatory
- **Placeholder**: Text shown before selection

### User Filtering
- **Roles**: Which user roles to include
- **Capabilities**: Filter by WordPress capabilities
- **Include Users**: Specific user IDs to always include
- **Exclude Users**: Specific user IDs to always exclude

### Display Options
- **Display Format**: How names appear
- **Custom Template**: For custom format (use {first_name}, {last_name}, {user_email}, {display_name})
- **Show User Count**: Display number of available users

### Behavior
- **Auto-Select Current User**: Automatically select logged-in user
- **Allow Empty Selection**: Allow "None" option
- **Searchable**: Enable search for large user lists
- **Search Threshold**: Minimum users to enable search

## Merge Tags

Use these merge tags in notifications and confirmations:

- `{field_label}`: Selected user's display name
- `{field_label:id}`: Selected user's ID
- `{field_label:email}`: Selected user's email
- `{field_label:username}`: Selected user's username

## Performance Tips

### For Large Sites
- **Enable Search**: For sites with 100+ users
- **Use Role Filtering**: Limit to specific roles
- **Exclude Inactive Users**: Remove users who haven't logged in recently

### Caching
- User lists are cached for 1 hour by default
- Cache is cleared when users are added/updated
- Increase cache time in global settings if needed

## Troubleshooting

### Field Not Showing Users
1. **Check Role Settings**: Ensure selected roles have users
2. **Verify Capabilities**: Check if capability filtering is too restrictive
3. **Clear Cache**: Go to Settings → User Select → Clear Cache

### Performance Issues
1. **Enable Search**: For large user lists
2. **Filter by Role**: Reduce the number of users
3. **Check Server Resources**: Ensure adequate memory and processing power

### Display Issues
1. **Check Display Format**: Ensure format matches available user data
2. **Verify User Data**: Check if users have required fields (first name, last name)
3. **Clear Browser Cache**: Refresh the page

## Support

- **Documentation**: Check this README and inline help text
- **GitHub Issues**: Report bugs and request features
- **WordPress.org**: Leave a review and get community support

## Changelog

### Version 1.0.1
- Minor bug fixes and improvements
- Enhanced security features
- Performance optimizations

### Version 1.0.0
- Initial release
- Basic user selection functionality
- Role and capability filtering
- Multiple display formats
- Auto-selection options
- Search functionality for large user lists

