<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Each clickable element is available only once per user.
 *	Trigger a hook the first time a logged in user click on it.
 *
 * Content hidden if:
 * * visitor is log off.
 * * the Events\EasterEgg cannot be found or is not active or in an active pool.
 * * No visited image and customer already got it.
 *
 * 2 images for 2 states: For seek and visited. */
class EasterEgg
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('lws_easteregg', array($me, 'shortcode'));
		\add_action('wp_ajax_lws_woorewards_ee_has', array($me, 'listener')); /// volontary obfuscated name to avoid a too easy research in source of page by customer

		\add_action('init', function(){
			\wp_register_script('woorewards-easteregg',LWS_WOOREWARDS_PRO_JS.'/easteregg.js',array('jquery', 'lws-tools'),LWS_WOOREWARDS_PRO_VERSION);
		});
	}

	function listener()
	{
		if( !(isset($_GET['ee_has']) &&  isset($_GET['p'])) ) /// args volontary obfuscated name to avoid a too easy research in source of page by customer
			return;
		if( empty($userId = \get_current_user_id()) )
			return;
		if( !\wp_verify_nonce($_GET['ee_has'], 'lws_woorewards_easteregg') )
			return;

		$p = base64_decode(str_rot13($_GET['p']));
		$startWith = 'easteregg.';
		if( substr($p, 0, strlen($startWith)) != $startWith )
			return;
		if( empty(intval($eggId = substr($p, strlen($startWith)))) )
			return;

		\do_action('lws_woorewards_easteregg', $userId, $eggId);

		global $wpdb;
		$imgId = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='wre_event_visited_egg' AND post_id=%d", $eggId));
		if( !empty($imgId) && !empty($url = \esc_attr(\wp_get_attachment_url($imgId))) )
			\wp_die("<img src='{$url}' style='display:inline;'>");
		else
			\wp_die("<span class='lws-wr-easteregg-found'></span>");
	}

	/** @brief shortcode [lws_easteregg]
	 *	Display a clickable easter egg image. */
	public function shortcode($atts=array(), $content='')
	{
		if( !is_array($atts) || !isset($atts['p']) || empty($p = intval($atts['p'])) || $p < 0 )
			return $content;
		if( empty($userId = \get_current_user_id()) )
			return $content;
		if( !$this->isActive($p) )
			return $content;

		$visited = $this->isVisited($userId, $p);
		$metaKey = $visited ? 'wre_event_visited_egg' : 'wre_event_egg';
		global $wpdb;
		$imgId = $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='{$metaKey}' AND post_id={$p}");

		if( !empty($imgId) && !empty($url = \esc_attr(\wp_get_attachment_url($imgId))) )
		{
			if( !$visited )
			{
				$this->enqueueScrpits();
				$nonce = \esc_attr(\wp_create_nonce('lws_woorewards_easteregg'));
				$e = \esc_attr(str_rot13(base64_encode('easteregg.'.$p)));
				$content = "<img src='{$url}' data-p='{$e}' data-n='{$nonce}' class='lws_wre_ee_has' style='display:inline;'>";  /// class volontary obfuscated name to avoid a too easy research in source of page by customer
			}
			else
				$content = "<img src='{$url}' style='display:inline;'>";
		}
		return $content;
	}

	protected function isVisited($userId, $eggId)
	{
		$done = \get_user_meta($userId, 'lws_woorewards_easteregg', false);
		return in_array($eggId, $done);
	}

	/** Is event/pool active */
	protected function isActive($eggId)
	{
		foreach( \LWS_WooRewards_Pro::getActivePools()->asArray() as $pool )
		{
			if( $pool->findEvent(intval($eggId)) )
				return true;
		}
		foreach( \LWS_WooRewards_Pro::getLoadedAchievements()->asArray() as $pool )
		{
			if( $pool->findEvent(intval($eggId)) )
				return true;
		}
		return false;
	}

	protected function enqueueScrpits()
	{
		\wp_enqueue_script('jquery');
		\wp_enqueue_script('lws-tools');
		\wp_enqueue_script('woorewards-easteregg');
	}

}

?>