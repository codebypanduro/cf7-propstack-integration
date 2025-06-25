<?php

/**
 * Plugin Updater Class for GitHub Releases
 * 
 * This class handles automatic updates for the CF7 Propstack Integration plugin
 * by checking GitHub releases and downloading updates when available.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CF7_Propstack_Plugin_Updater
{
    private $plugin_slug;
    private $plugin_file;
    private $github_repo;
    private $github_token;
    private $plugin_data;
    private $update_transient_key;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->plugin_slug = 'cf7-propstack-integration';
        $this->plugin_file = CF7_PROPSTACK_PLUGIN_BASENAME;
        $this->github_repo = 'codebypanduro/cf7-propstack-integration'; // Replace with your actual repo
        $this->github_token = get_option('cf7_propstack_github_token', ''); // Optional GitHub token for private repos
        $this->update_transient_key = 'cf7_propstack_update_info';

        // Get plugin data
        $this->plugin_data = get_plugin_data(CF7_PROPSTACK_PLUGIN_PATH . 'cf7-propstack-integration.php');

        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);

        // Add settings for GitHub token
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
    }

    /**
     * Register settings for GitHub token
     */
    public function register_settings()
    {
        register_setting('cf7_propstack_options', 'cf7_propstack_github_token');
    }

    /**
     * Add settings page
     */
    public function add_settings_page()
    {
        add_submenu_page(
            'options-general.php',
            'CF7 Propstack GitHub Updates',
            'GitHub Updates',
            'manage_options',
            'cf7-propstack-github-updates',
            array($this, 'settings_page')
        );
    }

    /**
     * Settings page content
     */
    public function settings_page()
    {
        if (isset($_POST['submit'])) {
            update_option('cf7_propstack_github_token', sanitize_text_field($_POST['github_token']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }

        $current_token = get_option('cf7_propstack_github_token', '');
?>
        <div class="wrap">
            <h1>CF7 Propstack GitHub Updates</h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">GitHub Token (Optional)</th>
                        <td>
                            <input type="text" name="github_token" value="<?php echo esc_attr($current_token); ?>" class="regular-text" />
                            <p class="description">Only needed for private repositories. Leave empty for public repos.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }

    /**
     * Check for updates from GitHub
     */
    public function check_for_updates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get latest release from GitHub
        $latest_release = $this->get_latest_release();

        if (!$latest_release) {
            return $transient;
        }

        $current_version = $this->plugin_data['Version'];
        $latest_version = $latest_release['tag_name'];

        // Remove 'v' prefix if present
        $latest_version = ltrim($latest_version, 'v');

        // Check if update is available
        if (version_compare($latest_version, $current_version, '>')) {
            $plugin_update = new stdClass();
            $plugin_update->id = $this->plugin_slug;
            $plugin_update->slug = $this->plugin_slug;
            $plugin_update->plugin = $this->plugin_file;
            $plugin_update->new_version = $latest_version;
            $plugin_update->url = $latest_release['html_url'];
            $plugin_update->package = $this->get_download_url($latest_release);
            $plugin_update->tested = $this->get_tested_version($latest_release);
            $plugin_update->requires = $this->get_requires_version($latest_release);
            $plugin_update->requires_php = $this->get_requires_php($latest_release);
            $plugin_update->last_updated = $latest_release['published_at'];
            $plugin_update->sections = array(
                'description' => $this->get_release_description($latest_release),
                'changelog' => $this->get_release_changelog($latest_release)
            );

            $transient->response[$this->plugin_file] = $plugin_update;
        }

        return $transient;
    }

    /**
     * Get latest release from GitHub
     */
    private function get_latest_release()
    {
        // Check cache first
        $cached = get_transient($this->update_transient_key);
        if ($cached !== false) {
            return $cached;
        }

        $api_url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";

        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            ),
            'timeout' => 15
        );

        // Add token if available
        if (!empty($this->github_token)) {
            $args['headers']['Authorization'] = 'token ' . $this->github_token;
        }

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $release = json_decode($body, true);

        if (empty($release) || !isset($release['tag_name'])) {
            return false;
        }

        // Cache for 1 hour
        set_transient($this->update_transient_key, $release, HOUR_IN_SECONDS);

        return $release;
    }

    /**
     * Get download URL for the release
     */
    private function get_download_url($release)
    {
        // Look for a zip file in the assets
        if (isset($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if ($asset['content_type'] === 'application/zip') {
                    return $asset['browser_download_url'];
                }
            }
        }

        // Fallback to source zip
        return $release['zipball_url'];
    }

    /**
     * Get tested WordPress version from release
     */
    private function get_tested_version($release)
    {
        // You can add a custom field in your release description
        // Format: "Tested with WordPress: 6.4"
        if (isset($release['body'])) {
            if (preg_match('/Tested with WordPress:\s*([0-9.]+)/i', $release['body'], $matches)) {
                return $matches[1];
            }
        }

        // Default to current WordPress version
        return get_bloginfo('version');
    }

    /**
     * Get required WordPress version from release
     */
    private function get_requires_version($release)
    {
        // You can add a custom field in your release description
        // Format: "Requires WordPress: 5.0"
        if (isset($release['body'])) {
            if (preg_match('/Requires WordPress:\s*([0-9.]+)/i', $release['body'], $matches)) {
                return $matches[1];
            }
        }

        // Default minimum
        return '5.0';
    }

    /**
     * Get required PHP version from release
     */
    private function get_requires_php($release)
    {
        // You can add a custom field in your release description
        // Format: "Requires PHP: 7.4"
        if (isset($release['body'])) {
            if (preg_match('/Requires PHP:\s*([0-9.]+)/i', $release['body'], $matches)) {
                return $matches[1];
            }
        }

        // Default minimum
        return '7.4';
    }

    /**
     * Get release description
     */
    private function get_release_description($release)
    {
        return isset($release['body']) ? $release['body'] : '';
    }

    /**
     * Get release changelog
     */
    private function get_release_changelog($release)
    {
        return isset($release['body']) ? $release['body'] : '';
    }

    /**
     * Plugin info for the update screen
     */
    public function plugin_info($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $latest_release = $this->get_latest_release();

        if (!$latest_release) {
            return $result;
        }

        $plugin_info = new stdClass();
        $plugin_info->name = $this->plugin_data['Name'];
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->version = ltrim($latest_release['tag_name'], 'v');
        $plugin_info->author = $this->plugin_data['Author'];
        $plugin_info->author_profile = $this->plugin_data['AuthorURI'];
        $plugin_info->last_updated = $latest_release['published_at'];
        $plugin_info->homepage = $latest_release['html_url'];
        $plugin_info->sections = array(
            'description' => $this->get_release_description($latest_release),
            'changelog' => $this->get_release_changelog($latest_release)
        );
        $plugin_info->download_link = $this->get_download_url($latest_release);
        $plugin_info->tested = $this->get_tested_version($latest_release);
        $plugin_info->requires = $this->get_requires_version($latest_release);
        $plugin_info->requires_php = $this->get_requires_php($latest_release);

        return $plugin_info;
    }

    /**
     * Post install actions
     */
    public function post_install($response, $hook_extra, $result)
    {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_file) {
            return $response;
        }

        // Clear update cache
        delete_transient($this->update_transient_key);

        // Clear WordPress plugin update cache
        delete_site_transient('update_plugins');

        return $response;
    }

    /**
     * Force check for updates
     */
    public function force_check_updates()
    {
        delete_transient($this->update_transient_key);
        delete_site_transient('update_plugins');
        wp_update_plugins();
    }
}
