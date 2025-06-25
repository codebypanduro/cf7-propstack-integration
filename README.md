# Contact Form 7 - Propstack Integration

A WordPress plugin that integrates Contact Form 7 with Propstack CRM to automatically create and update contacts from form submissions.

## Description

This plugin extends Contact Form 7 functionality by automatically sending form submissions to Propstack CRM. It provides a user-friendly interface to map Contact Form 7 fields to Propstack contact fields, ensuring seamless data flow between your website forms and CRM system.

## Features

- **Easy Field Mapping**: Visual interface to map CF7 form fields to Propstack contact fields
- **Automatic Contact Creation**: Creates new contacts in Propstack when forms are submitted
- **Contact Updates**: Updates existing contacts if they already exist (based on email)
- **Form-Specific Integration**: Enable/disable integration per form using CF7's native panel system
- **Debug Logging**: Comprehensive logging for troubleshooting
- **Data Validation**: Validates data before sending to Propstack
- **Data Sanitization**: Ensures data security and compliance
- **Responsive Admin Interface**: Modern, mobile-friendly settings page
- **Native CF7 Integration**: Uses Contact Form 7's built-in panel system for seamless integration

## Requirements

- WordPress 5.0 or higher
- Contact Form 7 plugin
- PHP 7.4 or higher
- Propstack API key

## Installation

1. Upload the `cf7-propstack-integration` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Contact Form 7 > Propstack Integration to configure the plugin

## Configuration

### 1. API Settings

1. Navigate to **Contact Form 7 > Propstack Integration**
2. Enter your Propstack API key
3. Verify the API URL (default: `https://api.propstack.de/v1`)
4. Optionally enable debug mode for logging
5. Save settings

### 2. Field Mappings

1. In the Field Mappings section, select a Contact Form 7 form
2. Choose the CF7 field you want to map
3. Select the corresponding Propstack field
4. Click "Add Mapping"
5. Repeat for all fields you want to sync

### 3. Enable Integration Per Form

The integration can be enabled for individual forms using Contact Form 7's native panel system:

1. **Edit a Contact Form 7 form**
2. **Look for the "Propstack Integration" tab** in the form editor (alongside Form, Mail, Messages, and Additional Settings)
3. **Check the "Enable Propstack integration for this form" checkbox**
4. **Click "Manage Field Mappings"** to configure field mappings for this form
5. **Save the form**

The Propstack Integration panel provides:

- A checkbox to enable/disable integration for the specific form
- Information about field mappings
- A direct link to manage field mappings in the plugin settings

### Alternative: Form Tag Method

You can also enable integration by adding the `[propstack_enable]` tag to your form content:

```html
[text* first-name "First Name"] [email* email "Email Address"]
[propstack_enable] [submit "Send Message"]
```

## Supported Propstack Fields

The plugin supports mapping to the following Propstack contact fields:

- **Basic Information**: `first_name`, `last_name`, `email`, `salutation`, `academic_title`
- **Company**: `company`, `position`
- **Contact**: `home_phone`, `home_cell`, `office_phone`, `office_cell`
- **Additional**: `description`, `language`, `newsletter`, `accept_contact`
- **System**: `client_source_id`, `client_status_id`

## Field Transformations

The plugin automatically transforms certain field values:

- **Salutation**: Converts "Herr", "Frau", "Mr", "Ms" to Propstack format
- **Language**: Converts language names to codes (de, en, es)
- **Boolean Fields**: Converts checkbox/radio values to boolean
- **Numeric Fields**: Ensures proper numeric format for IDs

## Usage Examples

### Basic Contact Form with Panel Integration

```html
[text* first-name "First Name"] [text* last-name "Last Name"] [email* email
"Email Address"] [text phone "Phone Number"] [textarea message "Message"]
[submit "Send Message"]
```

_Note: Enable integration via the "Propstack Integration" panel in the form editor_

### Advanced Form with Company Information

```html
[select* salutation "Salutation" "Mr" "Ms"] [text* first-name "First Name"]
[text* last-name "Last Name"] [email* email "Email Address"] [text company
"Company"] [text position "Position"] [text phone "Phone Number"] [checkbox
newsletter "Subscribe to newsletter"] [submit "Submit"]
```

_Note: Enable integration via the "Propstack Integration" panel in the form editor_

### Form Tag Method (Legacy)

```html
[text* first-name "First Name"] [email* email "Email Address"]
[propstack_enable] [submit "Send Message"]
```

## Troubleshooting

### Debug Mode

Enable debug mode in the plugin settings to log API requests and responses. Check your WordPress debug log for entries starting with `[CF7 Propstack]`.

### Common Issues

1. **"API key not configured"**: Ensure you've entered your Propstack API key in the settings
2. **"No field mappings found"**: Create field mappings for your form in the admin interface
3. **"Validation errors"**: Check that required fields (email, first_name or last_name) are mapped
4. **"API Error"**: Verify your API key and check Propstack API status
5. **"Propstack Integration panel not visible"**: Ensure you're editing a Contact Form 7 form and the plugin is activated

### Testing

1. Create a test form
2. Enable integration via the "Propstack Integration" panel
3. Map at least the email field in the plugin settings
4. Submit the form
5. Check your Propstack CRM for the new contact
6. Review debug logs if issues occur

## API Reference

The plugin uses the Propstack API endpoints:

- `POST /contacts` - Create new contact
- `PUT /contacts/{id}` - Update existing contact
- `GET /contacts?email={email}` - Find contact by email
- `GET /contact_sources` - Get available contact sources

## Security

- All data is sanitized before sending to Propstack
- API keys are stored securely in WordPress options
- Nonce verification for all AJAX requests
- User capability checks for admin functions
- Integration settings stored as CF7 form properties

## Support

For support and feature requests, please:

1. Check the debug logs for error messages
2. Verify your Propstack API key and permissions
3. Ensure Contact Form 7 is properly configured
4. Test with a simple form first
5. Verify the "Propstack Integration" panel is visible in the form editor

## Changelog

### Version 1.1.0

- **NEW**: Native Contact Form 7 panel integration
- **IMPROVED**: Replaced meta box approach with CF7's built-in panel system
- **ENHANCED**: Better user experience with integrated form editor interface
- **UPDATED**: Form-specific settings now stored as CF7 properties
- **FIXED**: Compatibility issues with Contact Form 7's custom editor

### Version 1.0.0

- Initial release
- Basic field mapping functionality
- Contact creation and updates
- Admin interface for configuration
- Debug logging
- Form-specific integration options

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Built for Contact Form 7 integration
- Uses Propstack API for CRM functionality
- Follows WordPress coding standards
- Implements Contact Form 7's native panel system
