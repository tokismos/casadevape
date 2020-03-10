<?php
namespace LWS\WOOREWARDS\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage achievenent display. */
class Achievement
{
	function __construct()
	{
		\add_action('lws_woorewards_achievement_push', array(\get_class(), 'push'), 10, 1);

		\add_action('admin_footer', array($this, 'footer'));
		\add_action('wp_footer', array($this, 'footer'));
		\add_action('admin_enqueue_scripts', array($this , 'scripts'), 99999); // late to let anybody going first
		\add_action('wp_enqueue_scripts', array($this , 'scripts'), 99999); // late to let anybody going first
	}

	/**	Register an achievement for display on the page (backend or frontend).
	 *	@param options (array) with:
	 *	* 'title' (string) At least a title is required for custom achievement.
	 *	* 'message' (string) optional, display a custom achievement with that message.
	 *	* 'image' (url) Achievement icon, if no url given, a default image is picked.
	 *	*	'user' (int) recipient user id.
	 *	* 'origin' (mixed, optional) source of the achievement.
	 *	@param $user (int) recipient user id, if false, use $options, if still undefined, use \get_current_user_id().
	 *
	 *	If recipient user cannot be defined (not set, and no one logged in),
	 *	the message will be displayed to the first logged out guy that load a page, whoever he is. */
	static function push($options=array(), $user=false)
	{
		if( !empty($options) && (is_array($options) || is_numeric($options)) )
		{
			if( empty($user = self::getUserId($user, $options)) )
			{
				\update_site_option(
					'lws_wre_pending_achievement',
					array_merge(
						\get_site_option('lws_wre_pending_achievement', array()),
						array($options)
					)
				);
			}
			else
			{
				\add_user_meta(
					$user,
					'lws_wre_pending_achievement',
					$options,
					false
				);
			}
		}
	}

	static protected function getUserId($user=false, $options=array())
	{
		if( !empty($user = intval($user)) )
			return $user;
		if( is_array($options) && isset($options['user']) )
		{
			if( !empty($user = intval($options['user'])) )
				return $user;
		}
		return \get_current_user_id();
	}

	protected function get()
	{
		if( empty($user = self::getUserId()) )
			return \get_site_option('lws_wre_pending_achievement', array());
		else
			return \get_user_meta($user, 'lws_wre_pending_achievement', false);
	}

	protected function count()
	{
		if( empty($user = self::getUserId()) )
			return count(\get_site_option('lws_wre_pending_achievement', array()));
		else
			return count(\get_user_meta($user, 'lws_wre_pending_achievement', false));
	}

	protected function clear()
	{
		if( empty($user = self::getUserId()) )
			\update_site_option('lws_wre_pending_achievement', array());
		else
			\delete_user_meta($user, 'lws_wre_pending_achievement');
	}

	public function scripts()
	{
		foreach( ($deps = array('jquery', 'jquery-ui-core','jquery-ui-widget')) as $dep )
			\wp_enqueue_script($dep);
		\wp_enqueue_script('lws-wre-badge', LWS_WOOREWARDS_JS.'/badge.js', $deps, LWS_WOOREWARDS_VERSION, true);
		$key = 'lws_wre_badge_css';
		$url = \apply_filters($key, LWS_WOOREWARDS_CSS.'/badge.css');
		\wp_enqueue_style($key, $url, array(), LWS_WOOREWARDS_VERSION);
	}

	/** Display achievement DOM. */
	function footer()
	{
		$achievements = $this->get();
		while( !empty($achievements) )
		{
			$this->clear();

			foreach( $achievements as $options )
			{
				$options = \apply_filters('lws_woorewards_achievement_options', $options);

				if( is_array($options) && isset($options['title']) )
				{
					$title = esc_attr($options['title']);
					$image = (isset($options['image']) && !empty($options['image'])) ? esc_attr($options['image']) : esc_attr(LWS_WOOREWARDS_IMG.'/badge-reward.png');
					$background = (isset($options['background']) && !empty($options['background'])) ? esc_attr($options['background']) : esc_attr(LWS_WOOREWARDS_IMG.'/badge-star.png');
					$content = isset($options['message']) ? $options['message'] : '';
					echo "<div class='lws_wre_badge' data-title='$title' data-imageurl='$image' data-bgurl='$background'>$content</div>";
				}
			}

			// cause unlock an achievement can produce achievement
			$achievements = $this->get();
		}
	}

}

?>