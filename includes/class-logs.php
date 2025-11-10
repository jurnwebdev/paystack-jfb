<?php
namespace PaystackJFB; if ( ! defined('ABSPATH') ) exit;
class Logs {
  public static function table(){ global $wpdb; return $wpdb->prefix.'paystack_jfb_logs'; }
  public static function maybe_install(){ global $wpdb; $t=self::table(); $cc=$wpdb->get_charset_collate();
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE IF NOT EXISTS `$t` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_type VARCHAR(64) NOT NULL DEFAULT '', reference VARCHAR(128) NOT NULL DEFAULT '', status VARCHAR(64) NOT NULL DEFAULT '', payload LONGTEXT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id), KEY ref(reference)) $cc;");
  }
  public static function add($event,$ref,$status,$payload=null){
    $s=Helpers::get_settings(); if(empty($s['log_events'])) return; global $wpdb; $t=self::table();
    $wpdb->insert($t,['event_type'=>$event,'reference'=>$ref,'status'=>$status,'payload'=>is_null($payload)?null:wp_json_encode($payload),'created_at'=>current_time('mysql',true)],['%s','%s','%s','%s','%s']);
    $count=(int)$wpdb->get_var("SELECT COUNT(*) FROM `$t`"); if($count>200){ $cut=$count-200; $wpdb->query($wpdb->prepare("DELETE FROM `$t` ORDER BY id ASC LIMIT %d",$cut)); }
  }
  public static function render_admin_table(){ if(!current_user_can('manage_options')) return; global $wpdb; $t=self::table(); $rows=$wpdb->get_results("SELECT * FROM `$t` ORDER BY id DESC LIMIT 50",ARRAY_A);
    echo '<h2 style="margin-top:24px;">Recent Paystack Events</h2><table class="widefat striped"><thead><tr><th>ID</th><th>Event</th><th>Reference</th><th>Status</th><th>Created (UTC)</th><th>Payload</th></tr></thead><tbody>';
    if(empty($rows)) echo '<tr><td colspan="6"><em>No events logged yet.</em></td></tr>';
    foreach((array)$rows as $r){ $payload=$r['payload']?json_decode($r['payload'],true):null; $pretty=$payload?wp_json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES):'';
      echo '<tr><td>'.esc_html($r['id']).'</td><td>'.esc_html($r['event_type']).'</td><td>'.esc_html($r['reference']).'</td><td>'.esc_html($r['status']).'</td><td>'.esc_html($r['created_at']).'</td><td><details><summary>View</summary><pre style="white-space:pre-wrap;max-height:240px;overflow:auto;">'.esc_html($pretty).'</pre></details></td></tr>';
    } echo '</tbody></table>';
  }
}