<?php
namespace PaystackJFB; if ( ! defined('ABSPATH') ) exit;
class Settings {
  public static function init()
  { 
    add_action('admin_menu',[__CLASS__,'menu']); 
    add_action('admin_init',[__CLASS__,'reg']);
    add_action('admin_post_paystack_jfb_create_pages', [__CLASS__, 'handle_create_pages']);
    add_action('admin_notices', [__CLASS__, 'maybe_show_missing_pages_notice']);
 }
  public static function menu(){ add_options_page(__('Paystack for JetFormBuilder','paystack-jfb'),__('Paystack for JFB','paystack-jfb'),'manage_options','paystack-jfb',[__CLASS__,'render']); }
  public static function reg(){
    register_setting('paystack_jfb_group', Helpers::OPT_KEY, ['type'=>'array','sanitize_callback'=>[__CLASS__,'sanitize'],'default'=>Helpers::defaults()]);
    add_settings_section('main', __('Configuration','paystack-jfb'), function(){ echo '<p>'.esc_html__('Configure keys, mode, callback page, webhook, and reconciliation.','paystack-jfb').'</p>'; }, 'paystack-jfb');
    foreach(['mode','secret_test','secret_live','callback_page_id','enable_db_update','meta_cct_id_key','meta_first_name_key','email_enable','email_to','email_from_name','email_from_email','email_subject','email_body','enable_webhook','enable_reconcile','reconcile_frequency','log_events','debug_enable'] as $f){
      add_settings_field($f, ucwords(str_replace('_',' ',$f)), [__CLASS__,'field'], 'paystack-jfb','main',['id'=>$f]);
    }
  }
  public static function sanitize($in){ if(!current_user_can('manage_options')) return Helpers::get_settings(); $d=Helpers::defaults(); $o=[];
    $o['mode']=in_array($in['mode']??'test',['test','live'],true)?$in['mode']:'test';
    foreach(['secret_test','secret_live'] as $k){ $o[$k]=trim($in[$k]??''); }
    $prev=Helpers::get_settings(); if(($in['secret_test']??'')==='')$o['secret_test']=$prev['secret_test']; if(($in['secret_live']??'')==='')$o['secret_live']=$prev['secret_live'];
    $o['callback_page_id']=absint($in['callback_page_id']??0);
    $o['enable_db_update']=!empty($in['enable_db_update'])?1:0;
    $o['meta_cct_id_key']=sanitize_key($in['meta_cct_id_key']??$d['meta_cct_id_key']);
    $o['meta_first_name_key']=sanitize_key($in['meta_first_name_key']??$d['meta_first_name_key']);
    $o['email_enable']=!empty($in['email_enable'])?1:0;
    $o['email_to']=sanitize_text_field($in['email_to']??$d['email_to']);
    $o['email_from_name']=sanitize_text_field($in['email_from_name']??$d['email_from_name']);
    $o['email_from_email']=sanitize_email($in['email_from_email']??$d['email_from_email']);
    $o['email_subject']=sanitize_text_field($in['email_subject']??$d['email_subject']);
    $allowed=['a'=>['href'=>[],'target'=>[],'rel'=>[]],'b'=>[], 'strong'=>[], 'em'=>[], 'i'=>[], 'u'=>[], 'p'=>[], 'br'=>[], 'ul'=>[], 'ol'=>[], 'li'=>[], 'span'=>['style'=>[]], 'div'=>['style'=>[]] ];
    $o['email_body']=wp_kses($in['email_body']??$d['email_body'],$allowed);
    $o['enable_webhook']=!empty($in['enable_webhook'])?1:0;
    $o['enable_reconcile']=!empty($in['enable_reconcile'])?1:0;
    $o['reconcile_frequency']=in_array($in['reconcile_frequency']??'every_3_hours',['hourly','every_3_hours','twicedaily','daily'],true)?$in['reconcile_frequency']:'every_3_hours';
    $o['log_events']=!empty($in['log_events'])?1:0;
    if(($prev['enable_reconcile']??0)!=$o['enable_reconcile'] || ($prev['reconcile_frequency']??'')!==$o['reconcile_frequency']){ if($o['enable_reconcile']) Cron::maybe_schedule($o['reconcile_frequency']); else Cron::clear_schedule(); }
    $o['debug_enable']=!empty($in['debug_enable'])?1:0;
    return $o;
  }
  public static function field($args){ $id=$args['id']; $s=Helpers::get_settings(); $name=Helpers::OPT_KEY.'['.$id.']';
    switch($id){
      case'mode':?><select name="<?php echo esc_attr($name); ?>"><option value="test" <?php selected($s[$id],'test');?>>Test</option><option value="live" <?php selected($s[$id],'live');?>>Live</option></select><?php break;
      case'secret_test':
      case'secret_live':?><input type="password" name="<?php echo esc_attr($name); ?>" value="" placeholder="<?php echo esc_attr(Helpers::mask_secret($s[$id]));?>" class="regular-text" autocomplete="new-password"/><?php break;
      case'callback_page_id': wp_dropdown_pages(['name'=>$name,'selected'=>$s[$id],'show_option_none'=>'— Use Paystack Dashboard —','option_none_value'=>0]); break;
      case'enable_db_update':
      case'email_enable':
      case'enable_webhook':
      case'enable_reconcile':
      case'log_events':
      case'debug_enable':?><label><input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($s[$id],1);?>/> Enable</label><?php break;
      case'reconcile_frequency':?><select name="<?php echo esc_attr($name); ?>"><option value="hourly" <?php selected($s[$id],'hourly');?>>Hourly</option><option value="every_3_hours" <?php selected($s[$id],'every_3_hours');?>>Every 3 Hours</option><option value="twicedaily" <?php selected($s[$id],'twicedaily');?>>Twice Daily</option><option value="daily" <?php selected($s[$id],'daily');?>>Daily</option></select><?php break;
      case'meta_cct_id_key':
      case'meta_first_name_key':
      case'email_to':
      case'email_from_name':
      case'email_from_email':
      case'email_subject':?><input type="text" class="regular-text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($s[$id]);?>"/><?php break;
      case'email_body':?><textarea class="large-text code" rows="7" name="<?php echo esc_attr($name); ?>"><?php echo esc_textarea($s[$id]);?></textarea><?php break;
      default: echo '<input type="text" class="regular-text" name="'.esc_attr($name).'" value="'.esc_attr($s[$id]).'"/>';
    }
  }
  public static function render(){ 
    ?>
  <div class="wrap"><h1><?php esc_html_e('Paystack for JetFormBuilder','paystack-jfb'); ?></h1>

    <form method="post" action="options.php">
      <?php settings_fields('paystack_jfb_group'); do_settings_sections('paystack-jfb'); submit_button(); ?>
    </form>

    <hr/>
    <h2><?php esc_html_e('Usage','paystack-jfb'); ?></h2>
    <ol style="line-height:1.7">
      <li>Create a <strong>Pay</strong> page and insert: <code>[paystack_init]</code></li>
      <li>Create a <strong>Payment Result</strong> page and insert: <code>[paystack_callback]</code></li>
      <li>In JetFormBuilder, set the post-submit redirect to your Pay page URL and append your form fields, e.g.:</li>
    </ol>

    <pre><?php echo esc_html( home_url('/pay/?email={email}&amount={amount}&inserted_cct_ticket_order={inserted_id}&customer_first_name={first_name}') ); ?></pre>

    <p>
      <?php 
        $url = wp_nonce_url( admin_url('admin-post.php?action=paystack_jfb_create_pages'), 'paystack_jfb_create_pages' );
      ?>
      <a class="button button-primary" href="<?php echo esc_url($url); ?>">
        <?php esc_html_e('Create Pay & Callback Pages Automatically','paystack-jfb'); ?>
      </a>
    </p>

    <?php \PaystackJFB\Logs::render_admin_table(); ?>
  </div><?php
  }

  public static function handle_create_pages() {
  if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
  check_admin_referer('paystack_jfb_create_pages');

  // Create "Pay" page with [paystack_init]
  $pay_id = wp_insert_post([
    'post_title'   => 'Pay',
    'post_status'  => 'publish',
    'post_type'    => 'page',
    'post_content' => '[paystack_init]',
  ]);

  // Create "Payment Result" page with [paystack_callback]
  $cb_id = wp_insert_post([
    'post_title'   => 'Payment Result',
    'post_status'  => 'publish',
    'post_type'    => 'page',
    'post_content' => '[paystack_callback]',
  ]);

  // Save callback page into settings
  $opts = Helpers::get_settings();
  if ( $cb_id && ! is_wp_error($cb_id) ) {
    $opts['callback_page_id'] = (int) $cb_id;
    update_option( Helpers::OPT_KEY, $opts );
  }

  wp_safe_redirect( admin_url('options-general.php?page=paystack-jfb&created=1') );
  exit;
}

public static function maybe_show_missing_pages_notice() {
  if ( ! current_user_can('manage_options') ) return;

  // Simple check: is there any page that contains [paystack_init]?
  $pay_exists = false;
  $pages = get_posts(['post_type' => 'page', 'numberposts' => 50, 'post_status' => 'any']);
  foreach ( (array) $pages as $p ) {
    if ( strpos( (string) $p->post_content, '[paystack_init]' ) !== false ) { $pay_exists = true; break; }
  }
  if ( $pay_exists ) return;

  $url = wp_nonce_url( admin_url('admin-post.php?action=paystack_jfb_create_pages'), 'paystack_jfb_create_pages' );
  echo '<div class="notice notice-warning is-dismissible">
    <p><strong>Paystack for JFB:</strong> No page with <code>[paystack_init]</code> was detected. 
    This page is required to initialize Paystack after JetFormBuilder redirect.</p>
    <p><a class="button button-primary" href="'.esc_url($url).'">Create Pay & Callback Pages</a></p>
  </div>';
}

  

}