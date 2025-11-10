<?php
namespace PaystackJFB; if ( ! defined('ABSPATH') ) exit;
class Email {
  public static function send_success(array $v): void {
    $s=Helpers::get_settings(); if(empty($s['email_enable'])) return;
    $to=trim($s['email_to']); if($to==='{customer_email}') $to=$v['email']??''; if(!$to) return;
    $sub=Helpers::replace_placeholders($s['email_subject'],$v); $body=Helpers::replace_placeholders($s['email_body'],$v);
    $hdr=['Content-Type: text/html; charset=UTF-8','From: '.Helpers::safe_header_name($s['email_from_name']).' <'.sanitize_email($s['email_from_email']).'>','Reply-To: '.Helpers::safe_header_name($s['email_from_name']).' <'.sanitize_email($s['email_from_email']).'>'];
    $rcp=array_filter(array_map('trim',explode(',',$to)),'is_email'); if(empty($rcp)) return; wp_mail($rcp,$sub,$body,$hdr);
  }
}