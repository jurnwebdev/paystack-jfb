<?php
namespace PaystackJFB;

if ( ! defined('ABSPATH') ) exit;

class Shortcodes {

  public static function init(){
    add_shortcode('paystack_init', [__CLASS__, 'init_tx']);
    add_shortcode('paystack_callback', [__CLASS__, 'callback']);
  }

  public static function init_tx(){
    $email     = isset($_GET['email'])    ? sanitize_email(wp_unslash($_GET['email'])) : '';
    $amount_ng = isset($_GET['amount'])   ? sanitize_text_field(wp_unslash($_GET['amount'])) : '';
    $reference = isset($_GET['reference'])? sanitize_text_field(wp_unslash($_GET['reference'])) : '';
    $callback  = isset($_GET['callback']) ? esc_url_raw(wp_unslash($_GET['callback'])) : '';

    if ( empty($email) || empty($amount_ng) ) {
      return '<p style="color:red;">Missing required parameters: email and amount.</p>';
    }

    $amount_clean = preg_replace('/[^\d\.]/', '', $amount_ng);
    if ( $amount_clean === '' || ! is_numeric($amount_clean) ) {
      return '<p style="color:red;">Invalid amount.</p>';
    }

    $amount_kobo = (int) round(floatval($amount_clean) * 100);
    if ( empty($reference) ) {
      $reference = 'jfb_' . wp_generate_password(12, false, false) . '_' . time();
    }

    $s  = Helpers::get_settings();
    $md = [];

    foreach ($_GET as $k => $v) {
      if (strpos($k, 'meta_') === 0) {
        $md[sanitize_key(substr($k, 5))] = sanitize_text_field(wp_unslash($v));
      }
    }

    if (!empty($s['meta_cct_id_key']) && isset($_GET[$s['meta_cct_id_key']])) {
      $md[$s['meta_cct_id_key']] = sanitize_text_field(wp_unslash($_GET[$s['meta_cct_id_key']]));
    }
    if (!empty($s['meta_first_name_key']) && isset($_GET[$s['meta_first_name_key']])) {
      $md[$s['meta_first_name_key']] = sanitize_text_field(wp_unslash($_GET[$s['meta_first_name_key']]));
    }
    if (empty($callback)) {
      $cb = Helpers::callback_url_from_settings();
      if ($cb) $callback = $cb;
    }

    $body = [
      'email'    => $email,
      'amount'   => $amount_kobo,
      'reference'=> $reference,
      'metadata' => $md,
    ];
    if (!empty($callback)) $body['callback_url'] = $callback;

    $resp = API::json_post('https://api.paystack.co/transaction/initialize', $body);

    if (is_wp_error($resp)) {
      return '<p style="color:red;">Paystack initialize failed: ' . esc_html($resp->get_error_message()) . '</p>';
    }
    if (empty($resp['status']) || empty($resp['data']['authorization_url'])) {
      return '<p style="color:red;">Unexpected response from Paystack initialize.</p>';
    }

    $auth = esc_url_raw($resp['data']['authorization_url']);
    \PaystackJFB\Logs::add_event('initialize', $reference, 'auth_url', $resp, 'info');

    // Try server-side redirect first
    nocache_headers();
    if (!headers_sent()) {
      wp_safe_redirect($auth, 302);
      exit;
    }

    // Fallbacks: JS + meta-refresh + a link
    $auth_html = esc_url($auth);
    $auth_js   = esc_js($auth);

    return '
      <p>Redirecting… <a href="'.$auth_html.'">Continue</a></p>
      <script>setTimeout(function(){ window.location.replace("'.$auth_js.'"); }, 50);</script>
      <noscript><meta http-equiv="refresh" content="0;url='.$auth_html.'"></noscript>
    ';
  }

  public static function callback(){
    $reference = isset($_GET['reference']) ? sanitize_text_field(wp_unslash($_GET['reference'])) : '';
    if (empty($reference)) {
      $reference = isset($_GET['trxref']) ? sanitize_text_field(wp_unslash($_GET['trxref'])) : '';
    }
    if (empty($reference)) {
      return '<p style="color:red;">Missing transaction reference.</p>';
    }

    $verify = API::json_get('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
    if (is_wp_error($verify) || empty($verify['data'])) {
      \PaystackJFB\Logs::add_event('error', $reference, 'verify_failed', null, 'error');
      return '<p style="color:red;">Verification failed.</p>';
    }

    $d      = $verify['data'];
    $status = strtolower($d['status'] ?? '');
    \PaystackJFB\Logs::add_event('callback_verify', $reference, $status, $d, $status === 'success' ? 'info' : 'error');

    $amount  = intval($d['amount'] ?? 0);
    $email   = isset($d['customer']['email']) ? sanitize_email($d['customer']['email']) : '';
    $meta    = is_array($d['metadata'] ?? null) ? $d['metadata'] : [];
    if (empty($email) && isset($meta['email'])) {
      $email = sanitize_email($meta['email']);
    }
    $paidIso = $d['paid_at'] ?? '';

    $s         = Helpers::get_settings();
    $cct_key   = $s['meta_cct_id_key']   ?? '';
    $fname_key = $s['meta_first_name_key'] ?? '';

    $cct_id = '';
    if ($cct_key && isset($meta[$cct_key])) {
      $cct_id = sanitize_text_field($meta[$cct_key]);
    } elseif ($cct_key && isset($_GET[$cct_key])) {
      $cct_id = sanitize_text_field(wp_unslash($_GET[$cct_key]));
    }

    $first = '';
    if ($fname_key && isset($meta[$fname_key])) {
      $first = sanitize_text_field($meta[$fname_key]);
    } elseif ($fname_key && isset($_GET[$fname_key])) {
      $first = sanitize_text_field(wp_unslash($_GET[$fname_key]));
    }

    // Normalize webhook-enabled flag
    $webhook_enabled = false;
    if (isset($s['enable_webhook'])) {
      $v = $s['enable_webhook'];
      $webhook_enabled = ($v === 1 || $v === '1' || $v === true || $v === 'true' || $v === 'on' || $v === 'yes');
    }

    // --- Attempt DB update and record whether we actually changed status to 'success' ---
    $db_updated = false;

    if ($status === 'success' && !empty($s['enable_db_update']) && $cct_id) {
      global $wpdb;
      $t   = $wpdb->prefix . 'jet_cct_ticket_order';
      $cur = $wpdb->get_row($wpdb->prepare("SELECT `transaction_status` FROM `$t` WHERE `_ID`=%s LIMIT 1", $cct_id), ARRAY_A);

      if ($cur && strtolower((string)$cur['transaction_status']) !== 'success') {
        $paidMysql = null;
        if ($paidIso) {
          $ts = strtotime($paidIso);
          if ($ts) $paidMysql = gmdate('Y-m-d H:i:s', $ts);
        }

        $upd = [
          'cct_status'         => 'publish',
          'transaction_status' => 'success',
          'payment_reference'  => $reference,
          'amount_paid'        => round($amount / 100, 2),
          'ticket_status'      => 'active',
        ];
        $fmt = ['%s','%s','%s','%f','%s'];
        if ($paidMysql) { $upd['paid_at'] = $paidMysql; $fmt[] = '%s'; }

        $rows = $wpdb->update($t, $upd, ['_ID' => $cct_id], $fmt, ['%s']);
        if ($rows !== false) {
          $db_updated = true;
          \PaystackJFB\Logs::add_event('db_update', $reference, 'success', ['cct_id' => $cct_id], 'info');
        }
      }
    }

    // EMAIL SENDING: decide who sends
    $dedupe_key = 'paystackjfb_email_sent_' . md5($reference);
    $should_send_email = false;

    if ($status === 'success' && !empty($s['email_enable'])) {
      if (!empty($s['enable_db_update'])) {
        // Only the process that updated the DB should send the email
        $should_send_email = $db_updated === true;
      } else {
        // No DB update: fallback to transient dedupe
        $should_send_email = ! get_transient($dedupe_key);
      }

      if ($should_send_email && !empty($email)) {
        $paidMysql = null;
        if ($paidIso) {
          $ts = strtotime($paidIso);
          if ($ts) $paidMysql = gmdate('Y-m-d H:i:s', $ts);
        }

        $sent = Email::send_success([
          'first_name'     => $first,
          'reference'      => $reference,
          'amount_kobo'    => $amount,
          'amount_ngn'     => number_format($amount / 100, 2),
          'email'          => $email,
          'paid_at_iso'    => $paidIso,
          'paid_at_mysql'  => $paidMysql,
          'source'         => $webhook_enabled ? 'callback_fallback' : 'callback',
        ]);

        if ($sent) {
          set_transient($dedupe_key, 1, DAY_IN_SECONDS);
        } else {
          if (!empty($s['debug_enable'])) {
            \PaystackJFB\Logs::add_event('error', $reference, 'email_failed', ['to' => $email], 'error');
          }
        }
      }
    }

    return '<div class="paystack-result"><h3>Payment Verification</h3>'
         . '<p><strong>Reference:</strong> ' . esc_html($reference) . '</p>'
         . '<p><strong>Status:</strong> ' . esc_html($status) . '</p>'
         . '<p><strong>Email:</strong> ' . esc_html($email) . '</p>'
         . '<p><strong>Amount:</strong> ' . number_format($amount / 100, 2) . ' NGN</p>'
         . '</div>';
  }
}
