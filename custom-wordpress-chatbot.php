<?php
/*
Plugin Name: Customer360 Web Chatbot
Description: Customer360 Web Chatbot is a lightweight, fully customizable floating chat plugin for WordPress that helps businesses interact with website visitors in real-time.
Version: 1.0
Author: Anzathu Pindani
Text Domain: custom-chatbot
*/

defined('ABSPATH') or die('No direct access allowed!');

// Enqueue assets
add_action('wp_enqueue_scripts', 'custom_chatbot_register_assets');
function custom_chatbot_register_assets()
{
    $css_path = plugin_dir_path(__FILE__) . 'assets/css/chatbot.css';
    $js_path = plugin_dir_path(__FILE__) . 'assets/js/chatbot.js';

    // Pusher JS SDK
    wp_enqueue_script(
        'pusher-js',
        'https://js.pusher.com/7.2/pusher.min.js',
        array(),
        null,
        true
    );

    wp_register_style(
        'custom-chatbot-style',
        plugins_url('assets/css/chatbot.css', __FILE__),
        array(),
        file_exists($css_path) ? filemtime($css_path) : '1.0'
    );

    wp_register_script(
        'custom-chatbot-script',
        plugins_url('assets/js/chatbot.js', __FILE__),
        array('jquery', 'pusher-js'),
        file_exists($js_path) ? filemtime($js_path) : '1.0',
        true
    );

    wp_localize_script('custom-chatbot-script', 'wpApiSettings', array(
        'user' => array('id' => get_current_user_id())
    ));

    wp_enqueue_style('custom-chatbot-style');
    wp_enqueue_script('custom-chatbot-script');
}

// Output chatbot HTML
add_action('wp_footer', 'custom_chatbot_display');
function custom_chatbot_display()
{
    if (is_admin()) return;

    $chat_title = get_option('custom_chatbot_title', 'Chat with Us');
    $input_placeholder = get_option('custom_chatbot_input_placeholder', 'Type your message...');
    $send_text = get_option('custom_chatbot_send_text', 'Send');
    $chat_image_icon = plugins_url('assets/img/live-chatwhite.png', __FILE__);
?>
<div id="custom-chatbot-wrapper">
    <div id="custom-chatbot-container">
        <div class="chatbot-header">
            <span>
                <img src="<?php echo esc_url($chat_image_icon); ?>" class="chat-icon-image" />
                <?php echo esc_html($chat_title); ?>
            </span>
            <span class="chatbot-close">×</span>
        </div>
        <div class="chatbot-messages"></div>
        <div class="chatbot-input-area">
            <input type="text" class="chatbot-user-input" placeholder="<?php echo esc_attr($input_placeholder); ?>">
            <button class="chatbot-send-btn"><?php echo esc_html($send_text); ?></button>
        </div>
        <div class="chatbot-footer">
            <?php echo wp_kses_post(get_option('custom_chatbot_footer_text', __('Powered by <a href="https://nicotechnologies.com" target="_blank">NICO Technologies</a>', 'custom-chatbot'))); ?>
        </div>
    </div>
    <div id="custom-chatbot-launcher">
        <div id="custom-chatbot-icon">
            <img src="<?php echo esc_url($chat_image_icon); ?>" class="chat-launcher-img" />
        </div>
    </div>
</div>
<?php
}

// Process messages via AJAX
add_action('wp_ajax_custom_chatbot_process_message', 'custom_chatbot_process_message');
add_action('wp_ajax_nopriv_custom_chatbot_process_message', 'custom_chatbot_process_message');
// Add this to your existing AJAX handler function
function custom_chatbot_process_message()
{
    check_ajax_referer('custom_chatbot_nonce', 'nonce');

    $name = sanitize_text_field($_POST['name'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $issue = sanitize_text_field($_POST['issue'] ?? '');

    // Handle file upload
    $attachment_url = '';
    if (!empty($_FILES['attachment'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $upload_overrides = array('test_form' => false);
        $uploaded_file = wp_handle_upload($_FILES['attachment'], $upload_overrides);

        if ($uploaded_file && !isset($uploaded_file['error'])) {
            $attachment_url = $uploaded_file['url'];
        }
    }

    // Rest of your existing code...
    $args = array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => json_encode(array(
            'name'  => $name,
            'phone' => $phone,
            'issue' => $issue,
            'attachment' => $attachment_url
        )),
        'timeout' => 15
    );

    // ... rest of your function
}

// Add this new AJAX endpoint for file uploads
add_action('wp_ajax_custom_chatbot_upload_attachment', 'custom_chatbot_upload_attachment');
add_action('wp_ajax_nopriv_custom_chatbot_upload_attachment', 'custom_chatbot_upload_attachment');
function custom_chatbot_upload_attachment()
{
    check_ajax_referer('custom_chatbot_nonce', 'nonce');

    if (empty($_FILES['attachment'])) {
        wp_send_json_error('No file uploaded');
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');

    $upload_overrides = array('test_form' => false);
    $uploaded_file = wp_handle_upload($_FILES['attachment'], $upload_overrides);

    if ($uploaded_file && !isset($uploaded_file['error'])) {
        wp_send_json_success(array(
            'url' => $uploaded_file['url'],
            'name' => basename($uploaded_file['file'])
        ));
    } else {
        wp_send_json_error($uploaded_file['error']);
    }
}
// API connection function
function custom_chatbot_call_api($message)
{
    $api_url = get_option('custom_chatbot_api_url', '');
    $api_key = get_option('custom_chatbot_api_key', '');

    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => json_encode(array(
            'message' => $message,
            'user_id' => get_current_user_id(),
            'context' => array()
        )),
        'timeout' => 15
    );

    $response = wp_remote_post($api_url, $args);

    if (is_wp_error($response)) {
        return array('reply' => __('Connection error. Please try again.', 'custom-chatbot'));
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

// Admin settings
add_action('admin_menu', 'custom_chatbot_admin_menu');
function custom_chatbot_admin_menu()
{
    add_options_page(
        __('Chatbot Settings', 'custom-chatbot'),
        __('Chatbot Settings', 'custom-chatbot'),
        'manage_options',
        'custom-chatbot-settings',
        'custom_chatbot_settings_page'
    );

    add_action('admin_init', 'custom_chatbot_register_settings');
}

function custom_chatbot_register_settings()
{
    register_setting('custom_chatbot_settings', 'custom_chatbot_allowed_file_types');
    register_setting('custom_chatbot_settings', 'custom_chatbot_max_file_size');
    register_setting('custom_chatbot_settings', 'custom_chatbot_api_url');
    register_setting('custom_chatbot_settings', 'custom_chatbot_api_key');
    register_setting('custom_chatbot_settings', 'custom_chatbot_icon');
    register_setting('custom_chatbot_settings', 'custom_chatbot_footer_text');
    register_setting('custom_chatbot_settings', 'custom_chatbot_image_icon');
}




function custom_chatbot_settings_page()
{
?>
<div class="wrap">
    <h1><?php esc_html_e('Chatbot Settings', 'custom-chatbot'); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields('custom_chatbot_settings'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php esc_html_e('API URL', 'custom-chatbot'); ?></th>
                <td>
                    <input type="url" name="custom_chatbot_api_url"
                        value="<?php echo esc_attr(get_option('custom_chatbot_api_url')); ?>" class="regular-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('API Key', 'custom-chatbot'); ?></th>
                <td>
                    <input type="password" name="custom_chatbot_api_key"
                        value="<?php echo esc_attr(get_option('custom_chatbot_api_key')); ?>" class="regular-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Chat Icon', 'custom-chatbot'); ?></th>
                <td>
                    <input type="text" name="custom_chatbot_icon"
                        value="<?php echo esc_attr(get_option('custom_chatbot_icon', '💬')); ?>" class="regular-text">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Chat Header Image Icon URL', 'custom-chatbot'); ?></th>
                <td>
                    <input type="url" name="custom_chatbot_image_icon"
                        value="<?php echo esc_attr(get_option('custom_chatbot_image_icon')); ?>" class="regular-text">
                    <p class="description">
                        <?php esc_html_e('Optional: Display image before chat title', 'custom-chatbot'); ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php esc_html_e('Footer Credit', 'custom-chatbot'); ?></th>
                <td>
                    <input type="text" name="custom_chatbot_footer_text"
                        value="<?php echo esc_attr(get_option('custom_chatbot_footer_text', 'Developed by <a href="https://nicotechnologies.com" target="_blank">NICO Technologies</a>')); ?>"
                        class="regular-text">
                    <p class="description"><?php esc_html_e('HTML allowed for links', 'custom-chatbot'); ?></p>
                </td>
            </tr>
            tr valign="top">
            <th scope="row"><?php esc_html_e('Allowed File Types', 'custom-chatbot'); ?></th>
            <td>
                <input type="text" name="custom_chatbot_allowed_file_types"
                    value="<?php echo esc_attr(get_option('custom_chatbot_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx')); ?>"
                    class="regular-text">
                <p class="description">
                    <?php esc_html_e('Comma-separated list of allowed file extensions', 'custom-chatbot'); ?></p>
            </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php esc_html_e('Max File Size (MB)', 'custom-chatbot'); ?></th>
                <td>
                    <input type="number" name="custom_chatbot_max_file_size"
                        value="<?php echo esc_attr(get_option('custom_chatbot_max_file_size', 5)); ?>"
                        class="small-text">
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
<?php
}