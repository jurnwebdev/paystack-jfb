<?php
namespace PaystackJFB;
if ( ! defined('ABSPATH') ) exit;

class Email {

  /**
   * Sends a "payment success" email using settings and placeholders.
   * Returns true on success, false otherwise.
   *
   * Expected settings (Helpers::get_settings()):
   *  - email_enable, email_to, email_from_name, email_from_email, email_subject, email_body, debug_enable
   */
  public static function send_success(array $v): bool {
    $s = Helpers::get_settings();

    // If emails globally disabled in settings, don't send.
    if ( empty($s['email_enable']) ) {
      return false;
    }

    // Build recipient(s). Default stored value can be '{customer_email}'.
    $to_raw = trim($s['email_to'] ?? '');
    if ($to_raw === '{customer_email}') {
      $to_raw = $v['email'] ?? '';
    }
    if (! $to_raw) {
      return false;
    }

    // Replace placeholders in subject and body using Helpers::replace_placeholders()
    $subject = Helpers::replace_placeholders($s['email_subject'] ?? 'Payment successful: {reference}', $v);
    $body    = Helpers::replace_placeholders($s['email_body'] ?? '<p>Hi {first_name},</p><p>Your payment of {amount_ngn} NGN was successful.</p>', $v);

    // Headers: HTML + From/Reply-To if available
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $from_name  = Helpers::safe_header_name($s['email_from_name'] ?? get_bloginfo('name'));
    $from_email = sanitize_email($s['email_from_email'] ?? get_option('admin_email'));
    if ($from_email) {
      $headers[] = 'From: ' . sprintf('%s <%s>', $from_name, $from_email);
      $headers[] = 'Reply-To: ' . sprintf('%s <%s>', $from_name, $from_email);
    }

    // Many recipients may be comma-separated; normalize to array of valid emails
    $rcp = array_filter(array_map('trim', explode(',', $to_raw)), function($e){ return is_email($e); });

    if (empty($rcp)) return false;

    // wp_mail accepts string or array of recipients
    $ok = wp_mail($rcp, $subject, $body, $headers);

    // Only write an error log when mail fails AND debug is enabled
    if (!$ok && !empty($s['debug_enable'])) {
      \PaystackJFB\Logs::add_event('error', $v['reference'] ?? '', 'wp_mail_failed', [
        'to'      => $rcp,
        'subject' => $subject,
      ], 'error');
    }

    return (bool) $ok;
  }
}
