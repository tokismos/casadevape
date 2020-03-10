<?php
namespace LWS\WOOREWARDS\PRO\Mails;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Inform people that someone sponsors him.
 * $data should be an array as:
 *	*	'sponsor' => a WP_User instance, the sponsor.
 *	*	'type' => the reward type (origin Unlockable type)
 *	* 'unlockable' => a Unlockable instance
 *	*	'reward' => depends on Unlockable type: WC_Coupon, string, array... */
class Sponsored
{
	protected $template = 'wr_sponsored';

	public function __construct()
	{
		\add_filter('lws_woorewards_mails', array($this, 'addTemplate'), 51);// priority: mail order in settings
		\add_filter('lws_mail_arguments_' . $this->template, array($this, 'attributes'), 10, 2);
		\add_filter('lws_mail_settings_' . $this->template, array($this, 'settings'));
		\add_filter('lws_mail_body_' . $this->template, array($this, 'body'), 10, 3);
	}

	public function addTemplate($arr)
	{
		$arr[] = $this->template;
		return $arr;
	}

	public function attributes($settings, $data)
	{
		if( \is_wp_error($data) )
			$data = $this->placeholders();
		$user = isset($data['sponsor']) ? $data['sponsor'] : \wp_get_current_user();
		$name = empty($user->display_name) ? (empty($user->user_nicename) ? $user->user_email : $user->user_nicename) : $user->display_name;

		foreach( array('subject', 'title', 'header', 'footer') as $key )
			$settings[$key] = str_replace('[sponsor]', $name, $settings[$key]);
		return $settings;
	}

	public function settings( $settings )
	{
		$settings['domain']        = 'woorewards';
		$settings['settings_name'] = __("Sponsorship", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['about']         = sprintf(__("Inform a user about a sponsorship. Use the shortcode %s to insert the name of the sponsor.", LWS_WOOREWARDS_PRO_DOMAIN),'<b>[sponsor]</b>');
		$settings['subject']       = sprintf(__("An advise from %s", LWS_WOOREWARDS_PRO_DOMAIN),'[sponsor]');
		$settings['title']         = sprintf(__("%s wants you to know us", LWS_WOOREWARDS_PRO_DOMAIN),'[sponsor]');
		$settings['header']        = __("Register and enjoy the reward below with your next order.", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['footer']        = __("Powered by WooRewards", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['css_file_url']  = LWS_WOOREWARDS_PRO_CSS . '/mails/sponsored.css';
		$settings['subids']        = array('lws_woorewards_mail_site_url_text'=>"{$settings['domain']} mail - {$settings['settings_name']} - home page URL label");
		return $settings;
	}

	public function body( $html, $data, $settings )
	{
		if( !empty($html) )
			return $html;
		if( $demo = \is_wp_error($data) )
			$data = $this->placeholders();

		if( !isset($data['type']) || empty($data['type']) )
			return $this->getBacklink($settings);

		$html = \apply_filters('lws_woorewards_sponsored_custom_type_mail_content', false, $data, $settings, $demo);
		return !empty($html) ? $html : $this->getDefault($data, $settings, $demo);
	}

	protected function getDefault($data, $settings, $demo=false)
	{
		if( !isset($data['unlockable']) || empty($data['unlockable']) )
			return '';

		$values = array(
			'title'  => $data['unlockable']->getTitle(),
			'detail' => $data['unlockable']->getCustomDescription()
		);

		if( empty($img = $data['unlockable']->getThumbnailImage()) && $demo )
			$img = "<div class='lws-sponsor-thumbnail lws-icon-image'></div>";

		$expire = '';
		if( \is_object($data['reward']) && \method_exists($data['reward'], 'get_date_expires') && $data['reward']->get_date_expires('edit') )
			$expire = $data['reward']->get_date_expires('edit')->date('Y-m-d');
		if( \is_array($data['reward']) && isset($data['reward']['meta_input']['expiry_date']) && !empty($data['reward']['meta_input']['expiry_date']) )
			$expire = $data['reward']['meta_input']['expiry_date'];

		if( !empty($expire) )
		{
			$expire = \mysql2date(\get_option('date_format'), $expire);
			$expire = sprintf(__("Expires on %s",LWS_WOOREWARDS_PRO_DOMAIN), $expire);
			$expire = "<div class='lwss_selectable lws-sponsor-expiry' data-type='Reward Expiration'>$expire</div>";
		}

		if( \is_object($data['reward']) && \method_exists($data['reward'], 'get_code') && ($code = $data['reward']->get_code()) )
			$values['title'] = $code . ' - ' . $values['title'];

		$html = <<<EOT
<tr><td class='lws-middle-cell'>
	<table class='lwss_selectable lws-sponsor-table' data-type='Rewards Table'>
		<tr>
			<td><div class='lwss_selectable lws-sponsor-img' data-type='Reward Image'>{$img}</div></td>
			<td>
				<div class='lwss_selectable lws-sponsor-title' data-type='Reward Title'>{$values['title']}</div>
				<div class='lwss_selectable lws-sponsor-detail' data-type='Reward Description'>{$values['detail']}</div>
				$expire
			</td>
		</tr>
	</table>
</td></tr>
EOT;

		return $html . $this->getBacklink($settings, $demo);
	}

	protected function getBacklink($settings, $demo=false)
	{
		$url = $demo ? '#' : \esc_attr(\home_url());
		$label = \get_option('lws_woorewards_mail_site_url_text', get_bloginfo('name'));
		if( !$demo )
			$label = \apply_filters('wpml_translate_single_string', $label, 'Widgets', "{$settings['domain']} mail - {$settings['settings_name']} - home page URL label");

		return <<<EOT
<tr><td>
	<div class='lwss_selectable lws-website-link' data-type='Website Link Cell'>
		<a href='{$url}' class='lwss_selectable lws-sponsor-backlink lwss_modify' data-type='Link' data-id='lws_woorewards_mail_site_url_text'>
		<span class='lwss_modify_content' >{$label}</span>
		</a>
	</div>
</td></tr>
EOT;
	}

	protected function placeholders()
	{
		$unlockable = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->load(array(
			'numberposts' => 1,
			'meta_query'  => array(
				array(
					'key'     => 'wre_sponsored_reward',
					'value'   => 'yes',
					'compare' => 'LIKE'
				)
			)
		))->last();
		$user = \wp_get_current_user();

		if( empty($unlockable) )
		{
			$unlockable = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create('lws_woorewards_unlockables_coupon')->last();
			$unlockable->setTestValues();
		}

		return array(
			'sponsor' => $user,
			'type' => $unlockable->getType(),
			'unlockable' => $unlockable,
			'reward' => $unlockable->createReward($user, true)
		);
	}

}
?>
