<?php
namespace PaystackJFB;

if ( ! defined('ABSPATH') ) exit;

class Email {

  /**
   * Sends a "payment success" email.
   * MUST return a real boolean so logs reflect reality.
   *
   * @param array $args {
   *   @type string email
   *   @type string reference
   *   @type string amount_ngn
   *   @type string first_name
   *   @type string paid_at_mysql
   *   @type string source
   * }
   * @return bool
   */
  public static function send_success(array $args){
    $to = isset($args['email']) ? sanitize_email($args['email']) : '';
    if (empty($to)) return false;

    $subject = 'Payment successful: ' . esc_html($args['reference'] ?? '');
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $amount = isset($args['amount_ngn'])    ? esc_html($args['amount_ngn'])    : '';
    $first  = isset($args['first_name'])    ? esc_html($args['first_name'])    : '';
    $ref    = isset($args['reference'])     ? esc_html($args['reference'])     : '';
    $paid   = isset($args['paid_at_mysql']) ? esc_html($args['paid_at_mysql']) : '';
    $src    = isset($args['source'])        ? esc_html($args['source'])        : 'callback';

    $body = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6">'
          . '<p>Hi ' . ($first ?: 'there') . ',</p>'
          . '<p>Your payment was successful.</p>'
          . '<p><strong>Amount:</strong> ' . $amount . ' NGN<br>'
          . '<strong>Reference:</strong> ' . $ref . '<br>'
          . '<strong>Paid at:</strong> ' . $paid . '</p>'
          . '<p style="color:#888;">Sent via: ' . $src . '</p>'
          . '</div>';

    $ok = wp_mail($to, $subject, $body, $headers);

    if (!$ok) {
      // Let wp_mail_failed hook add deeper diagnostics; we also log a simple marker.
      \PaystackJFB\Logs::add('wp_mail_failed', $ref, 'wp_mail_returned_false', ['to' => $to]);
    }

    return (bool) $ok;
  }
}
