<?php
namespace LWS\WOOREWARDS\PRO\Mails;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Setup mail about newly generated rewards.
 * Override 'wr_new_reward' mail from free version to be able to present any kind of reward.
 * $data should be an array as:
 *	*	'user' => a WP_User instance
 *	*	'type' => the reward type (origin Unlockable type)
 *	* 'unlockable' => a Unlockable instance
 *	*	'reward' => depends on Unlockable type: WC_Coupon, string, array... */
class NewReward
{
	protected $template = 'wr_new_reward';

	public function __construct()
	{
		\add_filter('lws_mail_settings_' . $this->template, array($this, 'settings'), 11); // priority after the free version to change
		\add_filter('lws_mail_body_' . $this->template, array($this, 'body'), 9, 3); // priority before the free version to bypass
	}

	public function settings( $settings )
	{
		$settings['css_file_url']  = LWS_WOOREWARDS_PRO_CSS . '/mails/newreward.css';
		$settings['fields']['enabled'] = array(
			'id' => 'lws_woorewards_enabled_mail_' . $this->template,
			'title' => __("Enabled", LWS_WOOREWARDS_PRO_DOMAIN),
			'type' => 'box',
			'extra' => array(
				'default' => 'on'
			)
		);
		return $settings;
	}


	public function body( $html, $data, $settings )
	{
		if( !empty($html) )
			return $html;
		if( $demo = \is_wp_error($data) )
			$data = $this->placeholders();

		$html = \apply_filters('lws_woorewards_new_reward_custom_type_mail_content', false, $data, $settings, $demo);
		return !empty($html) ? $html : $this->getDefault($data, $settings, $demo);
	}

	protected function getDefault($data, $settings, $demo=false)
	{
		$values = array(
			'title'  => $data['unlockable']->getTitle(),
			'detail' => $data['unlockable']->getCustomDescription()
		);

		if( empty($img = $data['unlockable']->getThumbnailImage()) && $demo )
			$img = "<div class='lws-reward-thumbnail lws-icon-image'></div>";

		$expire = '';
		if( \is_object($data['reward']) && \method_exists($data['reward'], 'get_date_expires') && $data['reward']->get_date_expires('edit') )
			$expire = $data['reward']->get_date_expires('edit')->date('Y-m-d');
		if( \is_array($data['reward']) && isset($data['reward']['meta_input']['expiry_date']) && !empty($data['reward']['meta_input']['expiry_date']) )
			$expire = $data['reward']['meta_input']['expiry_date'];

		if( !empty($expire) )
		{
			$expire = \mysql2date(\get_option('date_format'), $expire);
			$expire = sprintf(__("Expires on %s",LWS_WOOREWARDS_PRO_DOMAIN), $expire);
			$expire = "<div class='lwss_selectable lws-reward-expiry' data-type='Reward Expiration'>$expire</div>";
		}

		if( \is_object($data['reward']) && \method_exists($data['reward'], 'get_code') && ($code = $data['reward']->get_code()) )
			$values['title'] = __("Coupon code",LWS_WOOREWARDS_PRO_DOMAIN) . ' : ' . $code . ' - ' . $values['title'];

		return <<<EOT
<tr><td class='lws-middle-cell'>
	<table class='lwss_selectable lws-rewards-table' data-type='Rewards Table'>
		<tr>
			<td><div class='lwss_selectable lws-reward-img' data-type='Reward Image'>{$img}</div></td>
			<td>
				<div class='lwss_selectable lws-reward-title' data-type='Reward Title'>{$values['title']}</div>
				<div class='lwss_selectable lws-reward-detail' data-type='Reward Description'>{$values['detail']}</div>
				$expire
			</td>
		</tr>
	</table>
</td></tr>
EOT;
	}

	protected function placeholders()
	{
		$unlockable = false;
		if( !\LWS_WooRewards::isWC() )
			$unlockable = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create('lws_woorewards_pro_unlockables_usertitle')->last();
		else
			$unlockable = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create('lws_woorewards_unlockables_coupon')->last();

		if( !$unlockable )
			return array('user' => \wp_get_current_user(), 'type' => '', 'unlockable' => null, 'reward' => false);
		else if( \method_exists($unlockable, 'setTestValues') )
			$unlockable->setTestValues();

		$user = \wp_get_current_user();
		return array(
			'user' => $user,
			'type' => $unlockable->getType(),
			'unlockable' => $unlockable,
			'reward' => $unlockable->createReward($user, true)
		);
	}

}
?>
