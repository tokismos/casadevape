<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each year at register date. */
class Birthday extends \LWS\WOOREWARDS\Abstracts\Event
{
	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'umkey'] = $this->getBirthdayMetaKey();
		if( is_array($data[$prefix.'umkey']) )
			$data[$prefix.'umkey'] = implode(', ', $data[$prefix.'umkey']);
		$data[$prefix.'early'] = $this->getEarlyTrigger()->toString();
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Information", LWS_WOOREWARDS_PRO_DOMAIN), 'col30');

		$settingsUrl = \esc_attr(\add_query_arg(
			array(
				'page' => LWS_WOOREWARDS_PAGE.'.settings',
				'tab' => 'woocommerce'
			),
			admin_url('admin.php#lws_group_targetable_birthday')
		));
		$form .= "<tr><td colspan='2'><div class='lws-editlist-field-help'>";
		$form .= "<div class='lws-editlist-field-help-icon lws-icon-info'></div>";
		$form .= "<div class='lws-editlist-field-help-text'>";
		$form .= sprintf(__("If you don't already have a birthday field in your customer registration form, go <a %s>here</a> to add one.", LWS_WOOREWARDS_PRO_DOMAIN), " target='_blank' href='{$settingsUrl}'");
		$form .= "</div></div></td></tr>";

		// early trigger
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/durationfield.php';
		$label = _x("Early trigger", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("Trigger X days before birthday.", LWS_WOOREWARDS_PRO_DOMAIN));
		$value = $this->getEarlyTrigger()->toString();
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}early' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'>";
		$form .= \LWS\WOOREWARDS\Ui\DurationField::compose($prefix.'early', array('value'=>$value));
		$form .= "</div></td></tr>";

		$label = _x("Birthday meta key", "Ask for user meta key", LWS_WOOREWARDS_PRO_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("In case you use a third party registration form, you can set the database user meta key used for the birthday date.", LWS_WOOREWARDS_PRO_DOMAIN));
		$value = $this->getBirthdayMetaKey();
		$value = \esc_attr(is_array($value) ? implode(',', $value) : $value);
		$placeholder = $this->getDefaultBirthdayMetaKey();
		$form .= "<tr class='lws_advanced_option'><td class='lcell' nowrap><label for='{$prefix}umkey' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'><div class='lws-$context-opt-input'><input type='text' id='{$prefix}umkey' name='{$prefix}umkey' value='$value' placeholder=''{$placeholder}' /></div>";
		$form .= "</td></tr>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'early' => '/(p?\d+[DYM])?/i',
				$prefix.'umkey' => 'T'
			),
			'defaults' => array(
				$prefix.'early' => '',
			),
			'labels'   => array(
				$prefix.'early' => __("Early trigger", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'umkey' => __("Birthday meta key", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setBirthdayMetaKey($values['values'][$prefix.'umkey']);
			$this->setEarlyTrigger   ($values['values'][$prefix.'early']);
		}
		return $valid;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setBirthdayMetaKey(\get_post_meta($post->ID, 'wre_event_product_umkey', true));
		$this->setEarlyTrigger(\LWS\WOOREWARDS\Conveniencies\Duration::postMeta($post->ID, 'wre_unlockable_early_trigger'));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_product_umkey', $this->getBirthdayMetaKey());
		$this->getEarlyTrigger()->updatePostMeta($id, 'wre_unlockable_early_trigger');
		return $this;
	}

	/** @return a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Birthday", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	function getDescription($context='backend')
	{
		$early = $this->getEarlyTrigger();
		if( $early->isNull() )
			return __("At birthday", LWS_WOOREWARDS_PRO_DOMAIN);
		else
			return sprintf(__('%1$d %2$s before birthday', LWS_WOOREWARDS_PRO_DOMAIN), $early->getCount(), $early->getPeriodText());
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		if( !empty($this->getBirthdayMetaKey()) )
			\add_action('lws_woorewards_daily_event', array($this, 'trigger'));
	}

	/** return a Duration instance */
	public function getEarlyTrigger()
	{
		if( !isset($this->earlyTrigger) )
			$this->earlyTrigger = \LWS\WOOREWARDS\Conveniencies\Duration::void();
		return $this->earlyTrigger;
	}

	/** @param $days (false|int|Duration) */
	public function setEarlyTrigger($days=false)
	{
		if( empty($days) )
			$this->earlyTrigger = \LWS\WOOREWARDS\Conveniencies\Duration::void();
		else if( is_a($days, '\LWS\WOOREWARDS\Conveniencies\Duration') )
			$this->earlyTrigger = $days;
		else
			$this->earlyTrigger = \LWS\WOOREWARDS\Conveniencies\Duration::fromString($days);
		return $this;
	}

	/** @return string a usermeta.meta_key to store thi last rewarded birthday */
	protected function getMetaKey()
	{
		if( !isset($this->mkey) )
			$this->mkey = $this->getType() .'-'. $this->getId();
		return $this->mkey;
	}

	protected function setBirthdayMetaKey($key)
	{
		if( is_array($key) )
			$this->birthdayMetaKey = array_filter(array_map('trim', $key));
		else
		{
			$keys = explode(',', $key);
			if( count($keys) > 1 )
				$this->birthdayMetaKey = array_filter(array_map('trim', $keys));
			else
				$this->birthdayMetaKey = trim($key);
		}
	}

	protected function getDefaultBirthdayMetaKey()
	{
		return 'billing_birth_date';
	}

	/** @return (array|string) a list of usermeta.meta_key where a birthday date could be found. */
	protected function getBirthdayMetaKey()
	{
		return isset($this->birthdayMetaKey) && !empty($this->birthdayMetaKey) ? $this->birthdayMetaKey : $this->getDefaultBirthdayMetaKey();
	}

	/** Look for all users once a day */
	function trigger()
	{
		$mkey = $this->getMetaKey();
		$mbirthday = $this->getBirthdayMetaKey();
		$mbirthday = implode("','", array_map('esc_sql', is_array($mbirthday) ? $mbirthday : array($mbirthday)));

		global $wpdb;

		$birth = "DATE(birth.meta_value)";
		$anni = "DATE(anni.meta_value)";
		$early = $this->getEarlyTrigger();
		if( !$early->isNull() )
		{
			$interval = $early->getSqlInterval();
			$birth = "DATE_SUB({$birth}, {$interval})";
			$anni = "DATE_SUB({$anni}, {$interval})";
		}

		$sql = <<<EOT
SELECT birth.user_id, MAX(DATE(birth.meta_value)) as ref, MAX(DATE(anni.meta_value)) as saved, MAX(DATE(u.user_registered)) as registered FROM {$wpdb->usermeta} as birth
LEFT JOIN {$wpdb->usermeta} as anni ON anni.user_id=birth.user_id AND anni.meta_key='{$mkey}'
INNER JOIN {$wpdb->users} as u ON u.ID=birth.user_id
WHERE birth.meta_key IN ('{$mbirthday}') AND birth.meta_value <> ''
AND {$birth} <= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
AND (anni.meta_value IS NULL OR {$anni} <= DATE_SUB(CURDATE(), INTERVAL 1 YEAR))
GROUP BY birth.user_id
EOT;
		$users = $wpdb->get_results($sql);
		if( !is_array($users) )
			return;

		foreach( $users as $user )
		{
			$this->addPointsPerYear(
				$user->user_id,
				empty($user->ref) ? false : \date_create($user->ref),
				empty($user->saved) ? false : \date_create($user->saved),
				empty($user->registered) ? false : \date_create($user->registered),
				$early->isNull() ? false : $early->toInterval()
			);
		}
	}

	/** Starting one year after max(reference, last), add points for each year up to today.
	 *	Assume any $last is the same day as reference, only year should change.
	 *	@param $reference the original date.
	 *	@param $last if set, replace the original date.
	 *	@param $min if set, do not give points before that date. */
	protected function addPointsPerYear($user_id, $reference, $last=false, $min=false, $interval=false)
	{
		static $today = false;
		if( !$today )
			$today = \date_create();

		$year = new \DateInterval('P1Y');
		if( !empty($last) )
		{
			$reference = $last;
			$reference->add($year);
		}
		if( empty($reference) )
			return;

		if( !empty($min) )
			$reference->setDate($min->format('Y'), $reference->format('n'), $reference->format('j'));
		if( !empty($interval) )
			$reference->sub($interval);
		$reference->setTime(0, 0);

		$date = false;
		while( $reference <= $today )
		{
			if( empty($min) || $reference >= $min )
				$date = $reference->format('Y-m-d');
			$reference->add($year);
		}

		if( !empty($date) )
		{
			\update_user_meta($user_id, $this->getMetaKey(), $date);
			$this->addPoint($user_id, sprintf(_x("Birthday %s", "Gain reason", LWS_WOOREWARDS_PRO_DOMAIN), $date));
		}
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'periodic' => __("Periodic", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}
}

?>