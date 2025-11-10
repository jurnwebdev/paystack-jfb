<?php
namespace PaystackJFB; if ( ! defined('ABSPATH') ) exit;
class API {
  public static function json_post(string $url, array $body){
    $secret=Helpers::active_secret(); if(!$secret) return new \WP_Error('no_secret','Paystack Secret Key not configured.');
    $res=wp_remote_post($url,['headers'=>['Authorization'=>'Bearer '.$secret,'Content-Type'=>'application/json'],'timeout'=>45,'body'=>wp_json_encode($body)]);
    if(is_wp_error($res))return $res; $code=wp_remote_retrieve_response_code($res); $body=wp_remote_retrieve_body($res); $json=json_decode($body,true);
    if($code<200||$code>=300) return new \WP_Error('paystack_http','HTTP error: '.$code,['body'=>$body]); if(!is_array($json)) return new \WP_Error('bad_json','Invalid JSON');
    return $json;
  }
  public static function json_get(string $url){
    $secret=Helpers::active_secret(); if(!$secret) return new \WP_Error('no_secret','Paystack Secret Key not configured.');
    $args=['headers'=>['Authorization'=>'Bearer '.$secret,'Content-Type'=>'application/json'],'timeout'=>45];
    $delays=[400,900]; for($i=0;$i<3;$i++){ $res=wp_remote_get($url,$args); if(!is_wp_error($res)){ $c=wp_remote_retrieve_response_code($res); $b=wp_remote_retrieve_body($res);
      if($c>=200&&$c<300){ $j=json_decode($b,true); if(is_array($j)) return $j; return new \WP_Error('bad_json','Invalid JSON'); }
      if(!in_array($c,[429,500,502,503,504],true)) return new \WP_Error('paystack_http','HTTP error: '.$c,['body'=>$b]); }
      if($i<2) usleep(( [400,900][min($i,1)] )*1000);
    } return new \WP_Error('paystack_http','Network/HTTP error');
  }
}