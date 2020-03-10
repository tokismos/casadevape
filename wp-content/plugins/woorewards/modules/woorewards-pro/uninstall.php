<?php
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

delete_site_option('lws-ap-activation-woorewards');
delete_site_option('lws-license-key-woorewards');
delete_site_option('lws-ap-release-woorewards');
delete_option('lws_woorewards_pro_version');

// delete badge posts
$badges = \get_posts(array(
	'numberposts' => -1,
	'post_type' => 'lws_badge',
	'post_status' => array('publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft', 'inherit'),
	'update_post_meta_cache' => false,
	'update_post_term_cache' => false,
	'cache_results'  => false
));
foreach( $badges as $badge )
{
	\wp_delete_post($badge->Id, true);
}

global $wpdb;

// achievememts
foreach( array('lws-wre-achievement') as $post_type )
{
	foreach( $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='{$post_type}'") as $post_id )
		wp_delete_post($post_id, true);
}

// user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lws_woorewards_%' OR meta_key LIKE 'lws_wre_event_%'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('lws-loyalty-done-steps','woorewards_special_title','woorewards_special_title_position')");

// post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('lws_woorewards_event_points_sponsorship','woorewards_freeproduct','woorewards_permanent','woorewards_reminder_done')");

// mails
foreach( array('wr_new_reward', 'wr_available_unlockables', 'wr_sponsored', 'couponreminder', 'pointsreminder') as $template )
{
	delete_option('lws_mail_subject_'.$template);
	delete_option('lws_mail_title_'.$template);
	delete_option('lws_mail_header_'.$template);
	delete_option('lws_mail_template_'.$template);
}

// clean options
foreach( array(
	'lws_wooreward_max_sponsorship_count',
	'lws_wre_product_points_preview',
	'lws_wre_cart_points_preview',
) as $opt )
{
	delete_option($opt);
}

// remove custom role (if still without capacity )
foreach( \wp_roles()->get_names() as $value => $label )
{
	if( 0 === strpos($value, 'lws_wr_') )
	{
		foreach( \get_users(array('role'=>$value)) as $user )
			$user->remove_role($value);

		if( ($role = \wp_roles()->get_role($value)) && empty($role->capabilities) )
			\remove_role($value);
	}
}

?>
