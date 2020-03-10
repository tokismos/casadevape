<?php
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();
@include_once dirname(__FILE__) . '/modules/woorewards-pro/uninstall.php';

wp_clear_scheduled_hook('lws_woorewards_daily_event');

delete_option('lws_woorewards_version');
delete_option('lws_woorewards_pointstack_timeout_delete');

global $wpdb;
foreach( array('lws-wre-pool', 'lws-wre-event', 'lws-wre-unlockable') as $post_type )
{
	foreach( $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='{$post_type}'") as $post_id )
		wp_delete_post($post_id, true);
}

$wpdb->query("DROP TABLE {$wpdb->prefix}lws_wr_historic");

// user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key='lws_wre_unlocked_id'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key='lws_wre_pending_achievement'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lws_wre_points_%'"); /// @see \LWS\WOOREWARDS\Abstracts\IPointStack::MetaPrefix

// post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'lws_woorewards_%'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('reward_origin','reward_origin_id')");

// mails
$prefix = 'lws_mail_'.'woorewards'.'_attribute_';
delete_option($prefix.'headerpic');
delete_option($prefix.'footer');
foreach( array('wr_new_reward') as $template )
{
	delete_option('lws_mail_subject_'.$template);
	delete_option('lws_mail_preheader_'.$template);
	delete_option('lws_mail_title_'.$template);
	delete_option('lws_mail_header_'.$template);
	delete_option('lws_mail_template_'.$template);
}

$cap = 'manage_rewards';
foreach( array('administrator', 'shop_manager') as $slug )
{
	$role = \get_role($slug);
	if( !empty($role) && $role->has_cap($cap) )
		$role->remove_cap($cap);
}

// clean options
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lws_woorewards_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'rflush_lws_woorewards_%'");
