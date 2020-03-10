<?php
namespace LWS\WOOREWARDS\PRO\Mails;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Setup mail about achievement unlocked.
 * $data should be an array as:
 *	*	'user' => a WP_User instance
 *	*	'type' => the reward type (origin Unlockable type)
 *	* 'unlockable' => a Unlockable instance
 *	*	'reward' => depends on Unlockable type: WC_Coupon, string, array... */
class Achieved
{
	protected $template = 'wr_achieved';

	public function __construct()
	{
		add_filter( 'lws_woorewards_mails', array($this, 'addTemplate'), 20 );
		add_filter( 'lws_mail_settings_' . $this->template, array( $this , 'settings' ) );
		add_filter( 'lws_mail_body_' . $this->template, array( $this , 'body' ), 10, 3 );
	}

	public function addTemplate($arr)
	{
		$arr[] = $this->template;
		return $arr;
	}

	public function settings( $settings )
	{
		$settings['domain']        = 'woorewards';
		$settings['settings_name'] = __("Achievement", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['about']         = __("Sent to customers when they unlock an achievement", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['subject']       = __("New achievement completed !", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['title']         = __("Achievement complete", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['header']        = __("With that achievement, you received the following badge", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['footer']        = __("Powered by WooRewards", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['css_file_url']  = LWS_WOOREWARDS_PRO_CSS . '/mails/achieved.css';
		$settings['fields']['enabled'] = array(
			'id' => 'lws_woorewards_enabled_mail_' . $this->template,
			'title' => __("Enabled", LWS_WOOREWARDS_PRO_DOMAIN),
			'type' => 'box'
		);
		return $settings;
	}


	public function body( $html, $data, $settings )
	{
		if( !empty($html) )
			return $html;
		if( empty(\get_option('lws_woorewards_manage_badge_enable', 'on')) )
			return __("Badges and achievements are deactivated. Check your WooRewards &gt; General Settings.", LWS_WOOREWARDS_PRO_DOMAIN);
		if( $demo = \is_wp_error($data) )
			$data = $this->placeholders();

		$html = \apply_filters('lws_woorewards_achieved_custom_type_mail_content', false, $data, $settings, $demo);
		return !empty($html) ? $html : $this->getDefault($data, $settings, $demo);
	}

	protected function getDefault($data, $settings, $demo=false)
	{
		$values = array(
			'title'  => $data['unlockable']->getTitle(),
			'detail' => $data['unlockable']->getCustomDescription()
		);

		if( empty($img = $data['unlockable']->getThumbnailImage()) && $demo )
			$img = "<div class='lws-achievement-thumbnail'><img src='".LWS_WOOREWARDS_PRO_IMG.'/cat.png'."'/></div>";

		return <<<EOT
<tr><td class='lws-middle-cell'>
	<table class='lwss_selectable lws-achievement-table' data-type='Badges Table'>
		<tr>
			<td><div class='lwss_selectable lws-achievement-img' data-type='Badge Image'>{$img}</div></td>
			<td>
				<div class='lwss_selectable lws-achievement-title' data-type='Badge Title'>{$values['title']}</div>
				<div class='lwss_selectable lws-achievement-detail' data-type='Badge Description'>{$values['detail']}</div>
			</td>
		</tr>
	</table>
</td></tr>
EOT;
	}

	protected function placeholders()
	{
		$unlockable = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create('lws_woorewards_pro_unlockables_badge')->last();
		//$unlockable->setTestValues();
		//$unlockable->setThumbnail(LWS_WOOREWARDS_PRO_IMG.'/cat.png');
		$unlockable->setTitle('The Cat');
		$unlockable->setDescription('Look at this beautiful cat');
		$user = \wp_get_current_user();

		return array(
			'user' => $user,
			'type' => $unlockable->getType(),
			'unlockable' => $unlockable,
			'reward' => array()
		);
	}

}
?>
