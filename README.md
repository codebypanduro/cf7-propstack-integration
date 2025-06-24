# Contact Form 7 - Propstack Integration

A WordPress plugin that integrates Contact Form 7 with Propstack CRM to automatically create and update contacts from form submissions.

## Description

This plugin extends Contact Form 7 functionality by automatically sending form submissions to Propstack CRM. It provides a user-friendly interface to map Contact Form 7 fields to Propstack contact fields, ensuring seamless data flow between your website forms and CRM system.

## Features

- **Easy Field Mapping**: Visual interface to map CF7 form fields to Propstack contact fields
- **Automatic Contact Creation**: Creates new contacts in Propstack when forms are submitted
- **Contact Updates**: Updates existing contacts if they already exist (based on email)
- **Form-Specific Integration**: Enable/disable integration per form
- **Debug Logging**: Comprehensive logging for troubleshooting
- **Data Validation**: Validates data before sending to Propstack
- **Data Sanitization**: Ensures data security and compliance
- **Responsive Admin Interface**: Modern, mobile-friendly settings page

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

### 3. Enable Integration

You can enable the integration in two ways:

**Option A: Per Form Setting**

- Edit a Contact Form 7 form
- Check the "Enable Propstack integration" checkbox
- Save the form

**Option B: Form Tag**

- Add `[propstack_enable]` to your form content
- Save the form

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

### Basic Contact Form

```html
[text* first-name "First Name"] [text* last-name "Last Name"] [email* email
"Email Address"] [text phone "Phone Number"] [textarea message "Message"]
[propstack_enable] [submit "Send Message"]
```

### Advanced Form with Company Information

```html
[select* salutation "Salutation" "Mr" "Ms" ] [text* first-name "First Name"]
[text* last-name "Last Name"] [email* email "Email Address"] [text company
"Company"] [text position "Position"] [text phone "Phone Number"] [checkbox
newsletter "Subscribe to newsletter"] [propstack_enable] [submit "Submit"]
```

## Troubleshooting

### Debug Mode

Enable debug mode in the plugin settings to log API requests and responses. Check your WordPress debug log for entries starting with `[CF7 Propstack]`.

### Common Issues

1. **"API key not configured"**: Ensure you've entered your Propstack API key in the settings
2. **"No field mappings found"**: Create field mappings for your form in the admin interface
3. **"Validation errors"**: Check that required fields (email, first_name or last_name) are mapped
4. **"API Error"**: Verify your API key and check Propstack API status

### Testing

1. Create a test form with the `[propstack_enable]` tag
2. Map at least the email field
3. Submit the form
4. Check your Propstack CRM for the new contact
5. Review debug logs if issues occur

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

## Support

For support and feature requests, please:

1. Check the debug logs for error messages
2. Verify your Propstack API key and permissions
3. Ensure Contact Form 7 is properly configured
4. Test with a simple form first

## Changelog

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
