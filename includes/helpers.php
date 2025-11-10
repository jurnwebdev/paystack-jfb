<?php
namespace PaystackJFB; if ( ! defined('ABSPATH') ) exit;
class Helpers {
  const OPT_KEY = 'paystack_jfb_settings';
  public static function defaults(): array { return [
    'mode'=>'test','secret_test'=>'','secret_live'=>'','callback_page_id'=>0,
    'email_enable'=>1,'email_to'=>'{customer_email}','email_from_name'=>'Rave Visa','email_from_email'=>'support@ravevisa.com',
    'email_subject'=>'Payment Successful — Thank You for Your Order!',
    'email_body'=>'<p>Hi {first_name},</p><p>Thanks for your purchase with <strong>Rave Visa</strong>. Your payment was successful.</p><p><strong>Reference:</strong> {reference}<br><strong>Amount:</strong> NGN {amount_ngn}</p><p><strong>Next step:</strong> Kindly visit your account and <a href="https://ravevisa.com/account/">view your ticket</a> details. Your ticket includes a QR code that will be scanned for entry at the venue.</p><p>— Rave Visa</p>',
    'enable_db_update'=>1,'meta_cct_id_key'=>'inserted_cct_ticket_order','meta_first_name_key'=>'customer_first_name',
    'enable_webhook'=>1,'enable_reconcile'=>1,'reconcile_frequency'=>'every_3_hours','log_events'=>1,'debug_enable'=>0
  ];}
  public static function get_settings(): array { $o=get_option(self::OPT_KEY,[]); return wp_parse_args(is_array($o)?$o:[], self::defaults()); }
  public static function active_secret(): string { $s=self::get_settings(); return ($s['mode']==='live')?trim($s['secret_live']):trim($s['secret_test']); }
  public static function callback_url_from_settings(): string { $s=self::get_settings(); return !empty($s['callback_page_id'])? get_permalink((int)$s['callback_page_id']) : ''; }
  public static function replace_placeholders(string $t, array $v): string {
    return strtr($t,[ '{first_name}'=>$v['first_name']??'','{reference}'=>$v['reference']??'','{amount_kobo}'=>isset($v['amount_kobo'])?(string)$v['amount_kobo']:'','{amount_ngn}'=>$v['amount_ngn']??'','{email}'=>$v['email']??'','{paid_at_iso}'=>$v['paid_at_iso']??'','{paid_at_mysql}'=>$v['paid_at_mysql']??'' ]);
  }
  public static function mask_secret(string $k): string { if($k==='')return''; $l=strlen($k); return str_repeat('•', max(0,$l-4)).substr($k,-4); }
  public static function safe_header_name(string $s): string { $s=trim(preg_replace('/[\r\n]+/',' ',$s)); return $s?:get_bloginfo('name'); }
}