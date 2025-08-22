## 📦 Download Latest Release

**Version 1.3.0** - [Download ZIP](https://github.com/codebypanduro/cf7-propstack-integration/releases/download/v1.3.0/cf7-propstack-integration.zip)

[View all releases](https://github.com/codebypanduro/cf7-propstack-integration/releases/tag/v1.3.0) | [Installation Guide](#installation)

---







A WordPress plugin that integrates Contact Form 7 with Propstack CRM to automatically create and update contacts from form submissions.

## Description

This plugin extends Contact Form 7 functionality by automatically sending form submissions to Propstack CRM. It provides a user-friendly interface to map Contact Form 7 fields to Propstack contact fields, ensuring seamless data flow between your website forms and CRM system.

## Features

- **Native CF7 Panel Integration**: Seamless integration using Contact Form 7's built-in panel system
- **Inline Field Mapping Management**: Add and delete field mappings directly within the CF7 form editor
- **Real-time Field Mapping**: Dynamic field mapping without page reloads
- **Custom Fields Support**: Automatically fetches and displays custom fields from Propstack API
- **Automatic Contact Creation**: Creates new contacts in Propstack when forms are submitted
- **Contact Updates**: Updates existing contacts if they already exist (based on email)
- **Form-Specific Integration**: Enable/disable integration per form using CF7's native panel system
- **Debug Logging**: Comprehensive logging for troubleshooting
- **Data Validation**: Validates data before sending to Propstack
- **Data Sanitization**: Ensures data security and compliance
- **Custom Fields Caching**: Efficient caching system for custom fields to improve performance
- **Smart Field Disabling**: Automatically disables already mapped CF7 fields to prevent duplicates

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

### 2. Enable Integration Per Form

The integration is managed through Contact Form 7's native panel system:

1. **Edit a Contact Form 7 form**
2. **Look for the "Propstack Integration" tab** in the form editor (alongside Form, Mail, Messages, and Additional Settings)
3. **Check the "Enable Propstack integration for this form" checkbox**
4. **Configure field mappings directly in the panel** (see Field Mappings section below)
5. **Save the form**

### 3. Field Mappings

Field mappings can be managed directly within the CF7 form editor:

#### Adding Field Mappings

1. In the **Propstack Integration** panel, scroll to the "Field Mappings" section
2. Select a CF7 field from the dropdown (already mapped fields are disabled)
3. Select the corresponding Propstack field
4. Click "Add Mapping"
5. The mapping will be added instantly without page reload

#### Managing Existing Mappings

- Current mappings are displayed in a table below the add mapping form
- Click "Delete" next to any mapping to remove it
- Mappings are updated in real-time

#### Field Mapping Features

- **Smart Dropdowns**: CF7 fields that are already mapped are automatically disabled
- **Real-time Updates**: Add and delete mappings without page reloads
- **Visual Feedback**: Success messages confirm mapping operations
- **Form-specific**: Each form has its own independent field mappings

## Supported Propstack Fields

The plugin supports mapping to the following Propstack contact fields:

- **Basic Information**: `first_name`, `last_name`, `email`, `salutation`, `academic_title`
- **Company**: `company`, `position`
- **Contact**: `home_phone`, `home_cell`, `office_phone`, `office_cell`
- **Additional**: `description`, `language`, `newsletter`, `accept_contact`
- **System**: `client_source_id`, `client_status_id`

## Custom Fields Support

The plugin automatically fetches and displays custom fields from your Propstack account:

### Automatic Custom Field Detection

- Custom fields are automatically fetched from the Propstack API
- They appear in the dropdown with a "Custom: " prefix
- Custom fields are cached for 1 hour to improve performance
- If no custom fields are found, sample fields are provided for testing

### Custom Field Mapping

- Custom fields are mapped using the format `custom_{field_name}`
- Data is sent to Propstack using the `custom_fields` object
- Custom fields work alongside standard fields seamlessly

### Custom Fields in Form Submissions

When a form is submitted with custom field mappings:

- Custom field data is separated from standard contact data
- Data is sent to Propstack using the `custom_fields` structure
- Custom fields are included in both contact creation and updates
- The request format follows the official Propstack specification:

```json
{
  "client": {
    "first_name": "John",
    "email": "john@example.com",
    "custom_fields": {
      "my_custom_field": "value",
      "important_notes": "Important info"
    }
  }
}
```

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
[submit "Send Message"]
```

_Enable integration via the "Propstack Integration" panel in the form editor_

### Advanced Form with Company Information

```html
[select* salutation "Salutation" "Mr" "Ms"] [text* first-name "First Name"]
[text* last-name "Last Name"] [email* email "Email Address"] [text company
"Company"] [text position "Position"] [text phone "Phone Number"] [checkbox
newsletter "Subscribe to newsletter"] [submit "Submit"]
```

_Enable integration via the "Propstack Integration" panel in the form editor_

## Troubleshooting

### Debug Mode

Enable debug mode in the plugin settings to log API requests and responses. Check your WordPress debug log for entries starting with `[CF7 Propstack]`.

### Common Issues

1. **"API key not configured"**: Ensure you've entered your Propstack API key in the settings
2. **"No field mappings found"**: Create field mappings for your form in the Propstack Integration panel
3. **"Validation errors"**: Check that required fields (email, first_name or last_name) are mapped
4. **"API Error"**: Verify your API key and check Propstack API status
5. **"Propstack Integration panel not visible"**: Ensure you're editing a Contact Form 7 form and the plugin is activated
6. **"Field mapping buttons not working"**: Check browser console for JavaScript errors and ensure jQuery is loaded

### Testing

1. Create a test form
2. Enable integration via the "Propstack Integration" panel
3. Map at least the email field in the panel
4. Submit the form
5. Check your Propstack CRM for the new contact
6. Review debug logs if issues occur

## API Reference

The plugin uses the Propstack API v1 for contact management. For detailed API documentation, visit the [Propstack API Reference](https://docs.propstack.de/reference/kontakte).

### Supported Endpoints

- `POST /contacts` - Create new contact
- `PUT /contacts/{id}` - Update existing contact
- `GET /contacts?email={email}` - Find contact by email
- `GET /contact_sources` - Get contact sources
- `GET /custom_fields` - Get custom fields

## Changelog

### Version 1.0.0

- Initial release with basic Contact Form 7 integration
- Field mapping interface
- Contact creation and updates
- Custom fields support
- Debug logging

### Version 1.1.0

- Native CF7 panel integration
- Inline field mapping management
- Real-time field mapping updates
- Smart field disabling
- Improved user experience

## Support

For support and feature requests, please contact the plugin developer or create an issue in the plugin repository.

## License

This plugin is licensed under the GPL v2 or later.


**Note**: This plugin requires an active Propstack account and API key to function. Please ensure you have the necessary Propstack credentials before installation.
