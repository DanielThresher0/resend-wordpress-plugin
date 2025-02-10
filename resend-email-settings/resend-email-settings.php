<?php
/**
 * Plugin Name: Resend Email Settings
 * Description: Adds a setting page for integrating resend via API for sending emails more efficiently.
 * Version: 0.9.1
 * Author: Daniel Thresher
 * Text Domain: resend-email-settings
 */

if (!defined('ABSPATH')) exit;

define('RESEND_EMAIL_SETTINGS_PATH', plugin_dir_path(__FILE__));
define('RESEND_EMAIL_SETTINGS_URL', plugin_dir_url(__FILE__));

/* 1. Plugin sidebar, scripts and styles set up */

function resend_email_settings_add_admin_menu() {
    add_menu_page(
        __('Resend Email Settings', 'resend-email-settings'),
        __('Resend Email', 'resend-email-settings'),
        'manage_options',
        'resend-email-settings',
        'resend_email_settings_page',
        'dashicons-email-alt',
        90
    );
}
add_action('admin_menu', 'resend_email_settings_add_admin_menu');

function resend_email_settings_enqueue_admin_assets($hook) {
    if ($hook !== 'toplevel_page_resend-email-settings') return;
    
    wp_enqueue_style(
        'resend-admin-style',
        RESEND_EMAIL_SETTINGS_URL . 'assets/css/admin-style.css',
        array(),
        '1.0'
    );
    
    wp_enqueue_script(
        'resend-admin-script',
        RESEND_EMAIL_SETTINGS_URL . 'assets/js/admin-script.js',
        array('jquery'),
        '1.0',
        true
    );
}
add_action('admin_enqueue_scripts', 'resend_email_settings_enqueue_admin_assets');

/* 2. Show Toast Notification Equivalent */
function resend_email_settings_page() {
    $api_key = esc_attr(get_option('resend_api_key'));
    $from_email = esc_attr(get_option('resend_from_email'));
    $sender_name = esc_attr(get_option('resend_sender_name')); ?>
    
    <div class="resend-settings-wrap">
        <div id="resend-toast-container" class="resend-toast-container"></div>
        <h2>Resend Email Settings</h2>
        <div class="resend-card">
            <form id="resend-settings-form" onsubmit="return false;">
                <div class="resend-input-group">
                    <label for="resend_api_key">API Key</label>
                    <div class="input-wrapper">
                        <div class="icon"><span class="dashicons dashicons-admin-network"></span></div>
                        <input type="text" id="resend_api_key" value="<?php echo $api_key; ?>" />
                    </div>
                </div>
                <div class="resend-input-group">
                    <label for="resend_from_email">From Email</label>
                    <div class="input-wrapper">
                        <div class="icon"><span class="dashicons dashicons-email"></span></div>
                        <input type="email" id="resend_from_email" value="<?php echo $from_email; ?>" />
                    </div>
                </div>
                <div class="resend-input-group">
                    <label for="resend_sender_name">Sender Name</label>
                    <div class="input-wrapper">
                        <div class="icon"><span class="dashicons dashicons-admin-users"></span></div>
                        <input type="text" id="resend_sender_name" value="<?php echo $sender_name; ?>" />
                    </div>
                </div>
                <input type="hidden" id="resend_save_settings_nonce" value="<?php echo wp_create_nonce('resend_save_settings_nonce'); ?>" />
                <button id="save-settings-button" class="resend-button">Save Settings</button>
            </form>
            <hr class="resend-divider" />
            <h2>Send Test Email</h2>
            <p class="resend-text-sm">Enter an email address to send a test email using your current settings.</p>
            <div class="resend-input-group">
                <div class="input-wrapper">
                    <div class="icon"><span class="dashicons dashicons-email"></span></div>
                    <input type="email" id="test_email" placeholder="Enter test email address" />
                </div>
            </div>
            <input type="hidden" id="resend_test_email_nonce" value="<?php echo wp_create_nonce('resend_test_email_nonce'); ?>" />
            <button id="send-test-email-button" class="resend-button">Send Test Email</button>
        </div>
    </div>
    <?php
}

/* 3. Save Settings Button Click Handler */
function resend_save_settings_callback() {
    if (!current_user_can('manage_options') || 
        !isset($_POST['security']) || 
        !wp_verify_nonce($_POST['security'], 'resend_save_settings_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'resend-email-settings')]);
    }

    update_option('resend_api_key', sanitize_text_field($_POST['resend_api_key']));
    update_option('resend_from_email', sanitize_email($_POST['resend_from_email']));
    update_option('resend_sender_name', sanitize_text_field($_POST['resend_sender_name']));
    
    wp_send_json_success(['message' => __('Settings saved successfully!', 'resend-email-settings')]);
}
add_action('wp_ajax_resend_save_settings', 'resend_save_settings_callback');

/* 4. Send Test Email Button Click Handler */
function resend_send_test_email_callback() {
    if (!current_user_can('manage_options') || 
        !isset($_POST['security']) || 
        !wp_verify_nonce($_POST['security'], 'resend_test_email_nonce')) {
        wp_send_json_error(['message' => __('Security check failed.', 'resend-email-settings')]);
    }

    $test_email = sanitize_email($_POST['test_email']);
    if (empty($test_email) || !is_email($test_email)) {
        wp_send_json_error(['message' => __('Invalid email address.', 'resend-email-settings')]);
    }

    $api_key = get_option('resend_api_key');
    $from_email = get_option('resend_from_email');
    $sender_name = get_option('resend_sender_name');
    
    if (empty($api_key) || empty($from_email) || empty($sender_name)) {
        wp_send_json_error(['message' => __('Please configure Resend settings first.', 'resend-email-settings')]);
    }

    $response = wp_remote_post('https://api.resend.com/emails', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode([
            'from'    => "$sender_name <$from_email>",
            'to'      => $test_email,
            'subject' => __('Test Email from Resend Email Settings', 'resend-email-settings'),
            'html'    => '<p>' . __('This is a test email sent via the Resend API.', 'resend-email-settings') . '</p>'
        ]),
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    ($status >= 200 && $status < 300) 
        ? wp_send_json_success(['message' => __('Test email sent successfully!', 'resend-email-settings')])
        : wp_send_json_error(['message' => __('Failed to send: ', 'resend-email-settings') . ($body['error'] ?? __('Unknown error', 'resend-email-settings'))]);
}
add_action('wp_ajax_resend_send_test_email', 'resend_send_test_email_callback');

/* 5. WP Mail replacer if the settings are inputted else fall back to WP Mail */
if ( ! function_exists( 'wp_mail' ) ) {
    function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
        $api_key     = get_option('resend_api_key');
        $from_email  = get_option('resend_from_email');
        $sender_name = get_option('resend_sender_name');

        if ( empty($api_key) || empty($from_email) || empty($sender_name) ) {
            return false;
        }

        $body = [
            'from'    => "$sender_name <$from_email>",
            'to'      => is_array( $to ) ? implode(',', $to ) : $to,
            'subject' => $subject,
            'html'    => $message,
        ];

        $response = wp_remote_post('https://api.resend.com/emails', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($body),
            'timeout' => 15,
        ]);

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );
        return ( $status >= 200 && $status < 300 );
    }
}
