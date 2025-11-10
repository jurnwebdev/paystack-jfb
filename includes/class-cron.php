<?php
namespace PaystackJFB; if ( ! defined('ABSPATH') ) exit;
class Cron {
  const HOOK='paystack_jfb_reconcile';
  public static function init(){ add_filter('cron_schedules',[__CLASS__,'sched']); add_action(self::HOOK,[__CLASS__,'run']); }
  public static function sched($s){ if(!isset($s['every_3_hours'])) $s['every_3_hours']=['interval'=>10800,'display'=>__('Every 3 Hours','paystack-jfb')]; return $s; }
  public static function maybe_schedule($freq=''){ $s=Helpers::get_settings(); if(empty($s['enable_reconcile'])) return; $freq=$freq?:($s['reconcile_frequency']?:'every_3_hours'); if(!wp_next_scheduled(self::HOOK)) wp_schedule_event(time()+300,$freq,self::HOOK); }
  public static function clear_schedule(){ $t=wp_next_scheduled(self::HOOK); if($t) wp_unschedule_event($t,self::HOOK); }
  public static function run(){ $s=Helpers::get_settings(); if(empty($s['enable_reconcile'])) return; global $wpdb; $t=$wpdb->prefix.'jet_cct_ticket_order';
    $rows=$wpdb->get_results("SELECT `_ID`,`payment_reference`,`transaction_status` FROM `$t` WHERE (`transaction_status` IS NULL OR `transaction_status`='' OR `transaction_status` NOT IN ('success')) AND `payment_reference` IS NOT NULL AND `payment_reference`!='' ORDER BY `_ID` DESC LIMIT 25",ARRAY_A);
    foreach((array)$rows as $r){ $ref=$r['payment_reference']; $verify=API::json_get('https://api.paystack.co/transaction/verify/'.rawurlencode($ref));
      if(is_wp_error($verify) || empty($verify['data'])){ Logs::add('reconcile',$ref,'verify_failed', is_wp_error($verify)?$verify->get_error_data():$verify); continue; }
      $d=$verify['data']; if(strtolower($d['status']??'')!=='success'){ Logs::add('reconcile',$ref,'not_success',$verify); continue; }
      $amount=intval($d['amount']??0); $paidIso=$d['paid_at']??''; $paidMysql=null; if($paidIso){ $ts=strtotime($paidIso); if($ts) $paidMysql=gmdate('Y-m-d H:i:s',$ts); }
      $upd=['cct_status'=>'publish','transaction_status'=>'success','amount_paid'=>round($amount/100,2),'ticket_status'=>'active']; $fmt=['%s','%s','%f','%s']; if($paidMysql){ $upd['paid_at']=$paidMysql; $fmt[]='%s'; }
      $res=$wpdb->update($t,$upd,['_ID'=>$r['_ID']],$fmt,['%s']); Logs::add('reconcile',$ref,$res===false?'db_failed':'db_updated',$verify);
    }
  }
}