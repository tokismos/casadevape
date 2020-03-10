<?php
namespace LWS\WOOREWARDS\PRO\Mails;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class PointsReminder
{
	protected $template = 'pointsreminder';

	public function __construct()
	{
		\add_action('lws_woorewards_daily_event', array($this , 'remind'));

		\add_filter('lws_woorewards_mails', array($this, 'addTemplate'), 40);// priority: mail order in settings
		\add_filter('lws_mail_settings_' . $this->template, array($this, 'settings'));
		\add_filter('lws_mail_body_' . $this->template, array($this, 'body'), 10, 3);
		\add_filter('lws_mail_arguments_' . $this->template, array($this, 'arguments'), 10, 2);
	}

	public function addTemplate($arr)
	{
		$arr[] = $this->template;
		return $arr;
	}

	public function settings( $settings )
	{
		$settings['domain']        = 'woorewards';
		$settings['settings_name'] = __("Points Expiry Reminder", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['about']         = __("Inform a user about points that will expire.", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['about']        .= '<br/>';
		$settings['about']        .= sprintf(__("The shortcode <b>%s</b> in texts will be replaced by the real expiration date.", LWS_WOOREWARDS_PRO_DOMAIN), '[deadline]');
		$settings['subject']       = __("Your loyalty points will soon expire", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['title']         = __("Your loyalty points will expire the [deadline]", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['header']        = __("Your loyalty points are about to expire due to inactivity.", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['footer']        = __("Powered by WooRewards", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['css_file_url']  = LWS_WOOREWARDS_PRO_CSS . '/mails/pointsreminder.css';
		$settings['fields']['remind'] = array(
			'id' => 'lws_woorewards_points_reminder_days',
			'title' => __("Days before points expiry to send the reminder email.", LWS_WOOREWARDS_PRO_DOMAIN),
			'type' => 'text',
			'extra' => array('help' => __("The reminder system will send emails to your customers to warn them about the deadline of their loyalty points.", LWS_WOOREWARDS_PRO_DOMAIN).'</br/>'
			.__("Set an empty or 0 value if you don't want to send reminder emails.", LWS_WOOREWARDS_PRO_DOMAIN))
		);
		$settings['subids']        = array('lws_woorewards_mail_pointsreminder_url_text'=>"{$settings['domain']} mail - {$settings['settings_name']} - URL label");
		return $settings;
	}

	public function arguments($settings, $data)
	{
		if( \is_wp_error($data) )
			$data = $this->placeholders();

		$deadline = $data->details->timeout->getEndingDate($data->last_date);
		$deadline = \date_i18n(\get_option('date_format'), $deadline->getTimestamp());

		foreach( array('subject', 'title', 'header', 'footer') as $k )
			$settings[$k] = str_replace('[deadline]', $deadline, $settings[$k]);
		return $settings;
	}

	public function body( $html, $data, $settings )
	{
		if( !empty($html) )
			return $html;
		$url = '#';
		if( $demo = \is_wp_error($data) )
			$data = $this->placeholders();
		else
			$url = \esc_attr(\home_url());

		$deadline = $data->details->timeout->getEndingDate($data->last_date);
		$deadline = \date_i18n(\get_option('date_format'), $deadline->getTimestamp());

		$label = \get_option('lws_woorewards_mail_pointsreminder_url_text', get_bloginfo('name'));
		if( !$demo )
			$label = \apply_filters('wpml_translate_single_string', $label, 'Widgets', "{$settings['domain']} mail - {$settings['settings_name']} - URL label");
		$label = str_replace('[deadline]', $deadline, $label);

		return <<<EOT
<tr><td>
	<div class='lwss_selectable lws-pointsreminder-link' data-type='Website Link Cell'>
		<a href='{$url}' class='lwss_selectable lws-pointsreminder-backlink lwss_modify' data-type='Link' data-id='lws_woorewards_mail_pointsreminder_url_text'>
		<span class='lwss_modify_content'>{$label}</span>
		</a>
	</div>
</td></tr>
EOT;
	}

	protected function placeholders()
	{
		$d = random_int(7, 31);
		$p = $d + (($i = intval(\get_option('lws_woorewards_points_reminder_days',0))) > 0 ? $i : 3);
		$user = \wp_get_current_user();
		$user->last_date = \date_create()->sub(new \DateInterval('P'.$p.'D'));
		$user->details = (object)array(
			'timeout' => \LWS\WOOREWARDS\Conveniencies\Duration::days($d),
			'pools' => array(__("TEST System", LWS_WOOREWARDS_PRO_DOMAIN))
		);
		return $user;
	}

	// the trigger that will be launched daily
	public function remind()
	{
		if( ($countdown = intval(\get_option('lws_woorewards_points_reminder_days',0))) > 0 )
		{
			$stacks = $this->getStacks($countdown);
			foreach( $stacks as $stack => $details )
			{
				$once = 'lws_woorewards_points_reminded_'.$stack;
				$users = $this->getUsers($stack, $details, $once);
				foreach( $users as $user )
				{
					$user->last_date = \date_create($user->last_date);
					$user->details = (object)$details;
					\do_action('lws_mail_send', $user->user_email, $this->template, $user);
					\update_user_meta($user->ID, $once, \date_create()->format('Y-m-d H:i:s'));
				}
			}
		}
	}

	/** @return a array with stacks that can expiry
	 * with details about delay and pools */
	protected function getStacks($countdown=0)
	{
		$prevent = new \DateInterval("P{$countdown}D");
		$stacks = array();
		foreach( \LWS_WooRewards_Pro::getLoadedPools()->asArray() as $pool )
		{
			$delay = $pool->getOption('point_timeout');
			if( !$delay->isNull() )
			{
				$stackId = $pool->getStackId();
				$deadline = \date_create()->add($prevent)->sub($delay->toInterval());
				if( !isset($stacks[$stackId]) || $stacks[$stackId]['deadline'] > $deadline )
				{
					$stacks[$stackId] = array('deadline' => $deadline, 'timeout' => $delay);
					$stacks[$stackId]['pools'][$pool->getId()] = $pool->getOption('display_title');
				}
			}
		}
		return $stacks;
	}

	/** @return array with impacted customers
	 * @param $stacks @see getStacks() */
	protected function getUsers($stack, $details, $once)
	{
		$key = \LWS\WOOREWARDS\Abstracts\IPointStack::MetaPrefix . $stack;
		$table = \LWS\WOOREWARDS\Core\PointStack::table();
		global $wpdb;

		$query = <<<EOT
SELECT l.* FROM (
 SELECT u.ID, u.user_login, u.user_email, DATE(MAX(h.mvt_date)) as last_date
 FROM {$table} as h
 INNER JOIN {$wpdb->users} as u ON u.ID=h.user_id
 LEFT JOIN wp_usermeta as p ON p.user_id=u.ID AND p.meta_key=%s
 WHERE stack=%s
 AND (p.meta_value IS NOT NULL OR p.meta_value > 0)
 GROUP BY h.user_id
) as l
LEFT JOIN wp_usermeta as m ON m.user_id=l.ID AND m.meta_key=%s
WHERE l.last_date <= date(%s)
AND (m.meta_value IS NULL OR l.last_date > STR_TO_DATE(m.meta_value, '%%Y-%%m-%%d %%h:%%i:%%s'))
EOT;

		$users = $wpdb->get_results($wpdb->prepare(
			$query,
			$key,
			$stack,
			$once,
			$details['deadline']->format('Y-m-d')
		));
		return $users;
	}

}
