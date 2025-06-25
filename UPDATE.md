# CF7 Propstack Integration - Automatic Updates

This plugin includes an automatic update system that allows it to update directly from GitHub releases without needing to be in the WordPress marketplace.

## How It Works

The plugin uses WordPress's built-in update system and hooks into it to check for updates from your GitHub repository. When a new release is created on GitHub, all sites with the plugin installed will automatically detect the update and can install it.

## Setup Instructions

### 1. Configure the Plugin Updater

In the file `wp-content/plugins/cf7-propstack-integration/includes/class-plugin-updater.php`, update the GitHub repository URL:

```php
$this->github_repo = 'codebypanduro/cf7-propstack-integration'; // Replace with your actual repo
```

Replace `codebypanduro/cf7-propstack-integration` with your actual GitHub repository path.

### 2. Create a Release (Simplified Workflow)

When you want to release a new version, simply:

1. **Create a release in GitHub**:

   - Go to your GitHub repository
   - Click on "Releases" in the right sidebar
   - Click "Create a new release"
   - Create a new tag (e.g., `v1.0.1`) or use an existing tag
   - Add a release title and description
   - Click "Publish release"

2. **GitHub Actions will automatically**:
   - Extract the version from the release tag
   - Update the plugin version in the main plugin file
   - Commit and push the version change
   - Create a plugin zip file
   - Attach it to your release
   - Make it available for automatic updates

**That's it!** No manual scripts or version management needed.

### 3. Optional: GitHub Token for Private Repositories

If your repository is private, you'll need to:

1. Create a GitHub Personal Access Token with `repo` permissions
2. Go to WordPress Admin → Settings → GitHub Updates
3. Enter your GitHub token

For public repositories, no token is needed.

## Release Notes Format

You can include specific information in your release descriptions that the plugin will automatically parse:

```
## Release Notes

Tested with WordPress: 6.4
Requires WordPress: 5.0
Requires PHP: 7.4

### What's New
- Feature A
- Feature B

### Bug Fixes
- Fixed issue with X
- Improved performance
```

## How Updates Work

1. **Automatic Check**: WordPress checks for updates every 12 hours by default
2. **Manual Check**: Users can manually check for updates in WordPress Admin → Plugins
3. **Update Detection**: The plugin compares the current version with the latest GitHub release
4. **Download & Install**: WordPress downloads the zip file and installs the update
5. **Automatic Updates**: If enabled, updates can happen automatically in the background

## Testing Updates

To test the update system:

1. Install the plugin on a test site
2. Create a new release on GitHub with a higher version number
3. Go to WordPress Admin → Plugins
4. You should see an update notification
5. Click "Update Now" to install the update

## Troubleshooting

### Updates Not Showing

- Check that your GitHub repository is public (or you have a valid token for private repos)
- Verify the repository path in the updater class
- Check that the release has a zip file attached
- Clear WordPress cache: `delete_site_transient('update_plugins')`

### Update Fails

- Check that the zip file structure is correct (should contain the plugin folder)
- Verify file permissions on the server
- Check WordPress debug log for errors

### GitHub API Rate Limits

- For public repos: 60 requests per hour per IP
- For private repos with token: 5,000 requests per hour
- The plugin caches release info for 1 hour to minimize API calls

## Security Considerations

- The plugin validates all downloaded files
- Updates are downloaded through WordPress's secure update system
- GitHub releases are signed and verified
- Only zip files are accepted as update packages

## Customization

You can customize the update behavior by modifying the `CF7_Propstack_Plugin_Updater` class:

- Change cache duration
- Add custom validation
- Modify update frequency
- Add custom update notifications

## Support

For issues with the update system, check:

1. WordPress debug log
2. GitHub repository settings
3. Network connectivity
4. File permissions

The update system is designed to be robust and handle edge cases gracefully, but always test updates on a staging site first.
