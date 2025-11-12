<?php
namespace PaystackJFB;
if ( ! defined('ABSPATH') ) exit;

class Webhook {
  public static function init(){ add_action('rest_api_init',[__CLASS__,'register']); }
  public static function register(){ 
    register_rest_route('paystackjfb/v1','/webhook', [
      'methods'=>'POST',
      'callback'=>[__CLASS__,'handle'],
      'permission_callback'=>'__return_true'
    ]);
  }

  private static function verify_signature($raw){
    $secret = Helpers::active_secret();
    // Paystack signature header: HTTP_X_PAYSTACK_SIGNATURE or 'x-paystack-signature' depending on server.
    $sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? $_SERVER['HTTP_x_paystack_signature'] ?? ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
    if (!$sig) return false;
    return hash_equals($sig, hash_hmac('sha512',$raw,$secret));
  }

  public static function handle(\WP_REST_Request $req){
    $s = Helpers::get_settings();
    if (empty($s['enable_webhook'])) {
      return new \WP_REST_Response(['ok'=>false,'msg'=>'Webhook disabled'],403);
    }

    $raw = $req->get_body();
    if (!self::verify_signature($raw)) {
      \PaystackJFB\Logs::add_event('webhook_verify', '', 'bad_signature', ['raw' => substr($raw,0,200)], 'error');
      return new \WP_REST_Response(['ok'=>false,'msg'=>'Bad signature'],403);
    }

    $json = json_decode($raw,true);
    if (!is_array($json)) {
      \PaystackJFB\Logs::add_event('webhook', '', 'bad_json', null, 'error');
      return new \WP_REST_Response(['ok'=>false,'msg'=>'Bad JSON'],400);
    }

    $event = $json['event'] ?? '';
    $data  = $json['data'] ?? [];
    $ref   = $data['reference'] ?? ($data['trxref'] ?? '');
    $status = strtolower($data['status'] ?? '');
    $amount = intval($data['amount'] ?? 0);
    $email = isset($data['customer']['email']) ? sanitize_email($data['customer']['email']) : '';
    $meta  = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
    $paidIso = $data['paid_at'] ?? '';

    if ($event !== 'charge.success') {
      \PaystackJFB\Logs::add_event('webhook', $ref, 'ignored_event', $json, 'info');
      return new \WP_REST_Response(['ok'=>true],200);
    }

    \PaystackJFB\Logs::add_event('webhook_verify', $ref, $status, $data, $status === 'success' ? 'info' : 'error');

    // Determine cct id and first name from metadata
    $s = Helpers::get_settings();
    $cct_key = $s['meta_cct_id_key'] ?? '';
    $fname_key = $s['meta_first_name_key'] ?? '';
    $cct_id = '';
    if ($cct_key && isset($meta[$cct_key])) {
      $cct_id = sanitize_text_field($meta[$cct_key]);
    }
    $first = '';
    if ($fname_key && isset($meta[$fname_key])) {
      $first = sanitize_text_field($meta[$fname_key]);
    }

    // Extract vendor-related parameters
    $vendor_revenue = isset($meta['new_vendor_revenue']) ? intval($meta['new_vendor_revenue']) : 0;
    $vendor_tickets = isset($meta['new_vendor_tickets_sold']) ? intval($meta['new_vendor_tickets_sold']) : 0;
    $authorid = isset($meta['authorid']) ? intval($meta['authorid']) : 0;

    // Attempt DB update and set db_updated flag
    $db_updated = false;
    if ($status === 'success' && !empty($s['enable_db_update']) && $cct_id) {
      global $wpdb;
      $t = $wpdb->prefix . 'jet_cct_ticket_order';
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
          'payment_reference'  => $ref,
          'amount_paid'        => round($amount / 100, 2),
          'ticket_status'      => 'active',
        ];
        $fmt = ['%s','%s','%s','%f','%s'];
        if ($paidMysql) { $upd['paid_at'] = $paidMysql; $fmt[] = '%s'; }

        $rows = $wpdb->update($t, $upd, ['_ID' => $cct_id], $fmt, ['%s']);
        if ($rows !== false) {
          $db_updated = true;
          \PaystackJFB\Logs::add_event('db_update', $ref, 'success', ['cct_id' => $cct_id], 'info');
        }
      }
    }

    // --- Attempt usermeta update for vendor data ---
    if ($status === 'success' && ($vendor_revenue > 0 || $vendor_tickets > 0) && $authorid > 0) {
      // Update total_sales
      if ($vendor_revenue > 0) {
        $result = update_user_meta($authorid, 'total_sales', $vendor_revenue);
        if ($result === false && !metadata_exists('user', $authorid, 'total_sales')) {
          \PaystackJFB\Logs::add_event('error', $ref, 'usermeta_update_failed', ['user_id' => $authorid, 'meta_key' => 'total_sales', 'value' => $vendor_revenue], 'error');
          return new \WP_REST_Response(['ok'=>false,'msg'=>'Failed to update vendor revenue'],500);
        }
      }
      
      // Update total_tickets_sold
      if ($vendor_tickets > 0) {
        $result = update_user_meta($authorid, 'total_tickets_sold', $vendor_tickets);
        if ($result === false && !metadata_exists('user', $authorid, 'total_tickets_sold')) {
          \PaystackJFB\Logs::add_event('error', $ref, 'usermeta_update_failed', ['user_id' => $authorid, 'meta_key' => 'total_tickets_sold', 'value' => $vendor_tickets], 'error');
          return new \WP_REST_Response(['ok'=>false,'msg'=>'Failed to update vendor tickets'],500);
        }
      }
      
      \PaystackJFB\Logs::add_event('usermeta_update', $ref, 'success', ['user_id' => $authorid, 'total_sales' => $vendor_revenue, 'total_tickets_sold' => $vendor_tickets], 'info');
    }

    // Email sending decision
    $dedupe_key = 'paystackjfb_email_sent_' . md5($ref);
    $should_send_email = false;

    if ($status === 'success' && !empty($s['email_enable'])) {
      if (!empty($s['enable_db_update'])) {
        $should_send_email = $db_updated === true;
      } else {
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
          'reference'      => $ref,
          'amount_kobo'    => $amount,
          'amount_ngn'     => number_format($amount / 100, 2),
          'email'          => $email,
          'paid_at_iso'    => $paidIso,
          'paid_at_mysql'  => $paidMysql,
          'source'         => 'webhook',
        ]);

        if ($sent) {
          set_transient($dedupe_key, 1, DAY_IN_SECONDS);
        } else {
          if (!empty($s['debug_enable'])) {
            \PaystackJFB\Logs::add_event('error', $ref, 'email_failed', ['to' => $email], 'error');
          }
        }
      }
    }

    return new \WP_REST_Response(['ok'=>true],200);
  }
}
