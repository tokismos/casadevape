<?php
namespace LWS\WOOREWARDS\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage set of Event/Unlockable with PointStack. */
class Ajax
{
	function __construct()
	{
		add_action('wp_ajax_lws_woorewards_user_points_history', array($this, 'userPointsHistory'));
	}

	function userPointsHistory()
	{
		$user = isset($_GET['user']) ? intval($_GET['user']) : false;
		$stack = isset($_GET['stack']) ? \sanitize_key($_GET['stack']) : false;
		if( empty($user) || empty($stack) )
			\wp_die(__("Point system or user not found.", LWS_WOOREWARDS_DOMAIN), 404);

		$stack = \LWS\WOOREWARDS\Collections\PointStacks::instanciate()->create($stack, $user, 'ajax');
		$points = $stack->getHistory();

		$date_format = \get_option('date_format');
		foreach($points as &$point)
			$point['op_date'] = \mysql2date($date_format, $point['op_date']);
		\wp_send_json($points);
	}
}

?>