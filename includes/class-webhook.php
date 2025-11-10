<?php
namespace PaystackJFB; if ( ! defined('ABSPATH') ) exit;
class Webhook {
  public static function init(){ add_action('rest_api_init',[__CLASS__,'register']); }
  public static function register(){ register_rest_route('paystack-jfb/v1','/webhook',[ 'methods'=>'POST','callback'=>[__CLASS__,'handle'], 'permission_callback'=>'__return_true' ]); }
  private static function verify_signature($raw){ $secret=Helpers::active_secret(); if(!$secret) return false; $sig=$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? ''; if(!$sig) return false; return hash_equals($sig, hash_hmac('sha512',$raw,$secret)); }
  public static function handle(\WP_REST_Request $req){
    $s=Helpers::get_settings(); if(empty($s['enable_webhook'])) return new \WP_REST_Response(['ok'=>false,'msg'=>'Webhook disabled'],403);
    $raw=$req->get_body(); if(!self::verify_signature($raw)){ Logs::add('webhook','','signature_failed',['raw'=>$raw]); return new \WP_REST_Response(['ok'=>false,'msg'=>'Bad signature'],403); }
    $json=json_decode($raw,true); if(!is_array($json)){ Logs::add('webhook','','bad_json',['raw'=>$raw]); return new \WP_REST_Response(['ok'=>false,'msg'=>'Bad JSON'],400); }
    $event=$json['event']??''; $data=$json['data']??[]; $ref=$data['reference']??''; $status=strtolower($data['status']??''); $amount=intval($data['amount']??0);
    $email=isset($data['customer']['email'])?sanitize_email($data['customer']['email']):''; $meta=$data['metadata']??[]; $paidIso=$data['paid_at']??'';
    if($event!=='charge.success'){ Logs::add('webhook',$ref,'ignored_event',$json); return new \WP_REST_Response(['ok'=>true],200); }
    $note=''; if(!empty($s['enable_db_update'])){
      $cct_key=$s['meta_cct_id_key']; $cct_id=''; if($cct_key && is_array($meta) && isset($meta[$cct_key])) $cct_id=sanitize_text_field($meta[$cct_key]);
      if($status==='success' && $cct_id){ global $wpdb; $t=$wpdb->prefix.'jet_cct_ticket_order';
        $cur=$wpdb->get_row($wpdb->prepare("SELECT `transaction_status` FROM `$t` WHERE `_ID`=%s LIMIT 1",$cct_id),ARRAY_A);
        if(!$cur){ $note='CCT not found'; } else if(strtolower((string)$cur['transaction_status'])!=='success'){
          $paidMysql=null; if($paidIso){ $ts=strtotime($paidIso); if($ts) $paidMysql=gmdate('Y-m-d H:i:s',$ts); }
          $upd=['cct_status'=>'publish','transaction_status'=>'success','payment_reference'=>$ref,'amount_paid'=>round($amount/100,2),'ticket_status'=>'active']; $fmt=['%s','%s','%s','%f','%s'];
          if($paidMysql){ $upd['paid_at']=$paidMysql; $fmt[]='%s'; }
          $res=$wpdb->update($t,$upd,['_ID'=>$cct_id],$fmt,['%s']); $note=$res===false?'db_failed':'db_updated';
        } else { $note='already_success'; }
      } else { $note='missing_cct_or_not_success'; }
    }
    if($status==='success' && !empty($s['email_enable'])){
      $first=''; $fk=$s['meta_first_name_key']; if($fk && is_array($meta) && isset($meta[$fk])) $first=sanitize_text_field($meta[$fk]);
      $paidMysql=null; if($paidIso){ $ts=strtotime($paidIso); if($ts) $paidMysql=gmdate('Y-m-d H:i:s',$ts); }
      Email::send_success(['first_name'=>$first,'reference'=>$ref,'amount_kobo'=>$amount,'amount_ngn'=>number_format($amount/100,2),'email'=>$email,'paid_at_iso'=>$paidIso,'paid_at_mysql'=>$paidMysql]);
    }
    Logs::add('webhook',$ref,$note?:$status,$json);
    return new \WP_REST_Response(['ok'=>true],200);
  }
}