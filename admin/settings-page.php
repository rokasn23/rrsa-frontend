<?php
/*
Plugin Name: RRSA frontend for My Calendar (by Joe Dolson) plugin
Description: Adds a shortcode to add an event to My Calendar events from frontend
Version: 1.3.0
Author: RN
*/

if (!defined('ABSPATH')) exit;

class RRSA_Admin {

    private $config_path;

    public function __construct() {
        $this->config_path = plugin_dir_path(__FILE__) . 'config/settings.json';
        // $this->config_path = wp_upload_dir()['basedir'] . '/rrsa-frontend/settings.json';

        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_menu_page(
            'RRSA Frontend Settings',
            'RRSA Frontend Plugin',
            'manage_options',
            'rrsa-frontend-settings',
            [$this, 'render_page'],
            'dashicons-admin-generic',
            80
        );
    }

    public function render_page() {

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Save handler
        if (
            isset($_POST['rrsa_frontend_save']) &&
            check_admin_referer('rrsa_frontend_settings_nonce')
        ) {

            $data = [
                'api_url' => sanitize_text_field($_POST['api_url'] ?? ''),
                'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
                'enabled' => isset($_POST['enabled']),
            ];

            $json = json_encode($data, JSON_PRETTY_PRINT);

            file_put_contents($this->config_path, $json);

            echo '<div class="updated"><p>Settings saved.</p></div>';
        }

        // Load existing config
        $config = [];

        if (file_exists($this->config_path)) {
            $contents = file_get_contents($this->config_path);
            $config = json_decode($contents, true);
        }

        ?>
        <div class="wrap">
            <h1>My Plugin Settings</h1>

            <form method="post">

                <?php wp_nonce_field('my_plugin_settings_nonce'); ?>

                <table class="form-table">

                    <tr>
                        <th>API URL</th>
                        <td>
                            <input
                                type="text"
                                name="api_url"
                                class="regular-text"
                                value="<?php echo esc_attr($config['api_url'] ?? ''); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th>API Key</th>
                        <td>
                            <input
                                type="text"
                                name="api_key"
                                class="regular-text"
                                value="<?php echo esc_attr($config['api_key'] ?? ''); ?>"
                            >
                        </td>
                    </tr>

                    <tr>
                        <th>Enabled</th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="enabled"
                                    <?php checked($config['enabled'] ?? false); ?>
                                >
                                Enable feature
                            </label>
                        </td>
                    </tr>

                </table>

                <p>
                    <input
                        type="submit"
                        name="my_plugin_save"
                        class="button button-primary"
                        value="Save Settings"
                    >
                </p>

            </form>
        </div>
        <?php
    }
}

new RRSA_Admin();