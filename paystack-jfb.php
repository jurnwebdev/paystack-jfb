<?php
/**
 * Plugin Name: Paystack for JetFormBuilder
 * Description: JetFormBuilder ↔ Paystack with server-side init, webhook verification, callback fallback, reconciliation cron, and logs.
 * Version:     1.3.0
 * Author:      Tobi John
 * Author URI:  https://tobijohn.com
 * Text Domain: paystack-jfb
 */

if ( ! defined('ABSPATH') ) exit;

define('PAYSTACK_JFB_VERSION', '1.3.0');
define('PAYSTACK_JFB_FILE', __FILE__);
define('PAYSTACK_JFB_DIR', plugin_dir_path(__FILE__));

require_once PAYSTACK_JFB_DIR . 'includes/helpers.php';
require_once PAYSTACK_JFB_DIR . 'includes/class-settings.php';
require_once PAYSTACK_JFB_DIR . 'includes/class-api.php';
require_once PAYSTACK_JFB_DIR . 'includes/class-email.php';
require_once PAYSTACK_JFB_DIR . 'includes/class-shortcodes.php';
require_once PAYSTACK_JFB_DIR . 'includes/class-logs.php';
require_once PAYSTACK_JFB_DIR . 'includes/class-webhook.php';
require_once PAYSTACK_JFB_DIR . 'includes/class-cron.php';

register_activation_hook( __FILE__, function() {
    \PaystackJFB\Logs::maybe_install();
    \PaystackJFB\Cron::maybe_schedule();
});
register_deactivation_hook( __FILE__, function() {
    \PaystackJFB\Cron::clear_schedule();
});

add_action('plugins_loaded', function () {
    \PaystackJFB\Settings::init();
    \PaystackJFB\Shortcodes::init();
    \PaystackJFB\Webhook::init();
    \PaystackJFB\Cron::init();
});

// Better diagnostics for email issues
add_action('wp_mail_failed', function($wp_error){
  $code = is_wp_error($wp_error) ? $wp_error->get_error_code() : null;
  $msg  = is_wp_error($wp_error) ? $wp_error->get_error_message() : null;
  $data = is_wp_error($wp_error) ? $wp_error->get_error_data() : null;

  // Try to extract a reference if you inject it into headers or data elsewhere
  $ref  = is_array($data) && isset($data['reference']) ? $data['reference'] : '';

  \PaystackJFB\Logs::add('wp_mail_failed', $ref, 'error', [
    'code' => $code,
    'msg'  => $msg,
    'data' => $data,
  ]);
}, 10, 1);

