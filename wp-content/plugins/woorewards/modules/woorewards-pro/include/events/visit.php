<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each time a customer comes back to the site. */
class Visit extends \LWS\WOOREWARDS\Abstracts\Event
{
	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'cooldown'] = $this->getCooldown()->toString();
		return $data;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'cooldown' => '/(p?\d+[DYM])?/i'
			),
			'defaults' => array(
				$prefix.'cooldown' => 'P1D'
			),
			'labels'   => array(
				$prefix.'cooldown' => __("Cooldown", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setCooldown($values['values'][$prefix.'cooldown']);
		}
		return $valid;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Options", LWS_WOOREWARDS_PRO_DOMAIN));

		// cooldown
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/durationfield.php';
		$label = _x("Cooldown", "Recurent visit", LWS_WOOREWARDS_PRO_DOMAIN);
		$label .= \lws_get_tooltips_html(__("Delay until a new visit can be taken into account.", LWS_WOOREWARDS_PRO_DOMAIN));
		$value = $this->getCooldown()->toString();
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}cooldown' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input lws_woorewards_pro_events_visit_force_duration'>";
		$form .= \LWS\WOOREWARDS\Ui\DurationField::compose($prefix.'cooldown', array('value'=>$value));
		$form .= "</div></td></tr>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setCooldown(\LWS\WOOREWARDS\Conveniencies\Duration::postMeta($post->ID, 'wre_event_visit_cooldown'));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		$this->getCooldown()->updatePostMeta($id, 'wre_event_visit_cooldown');
		return $this;
	}

	/** return a Duration instance (min 1 day) */
	public function getCooldown()
	{
		if( !isset($this->cooldown) )
			$this->cooldown = \LWS\WOOREWARDS\Conveniencies\Duration::days(1);
		return $this->cooldown;
	}

	/** @param $days (false|int|Duration)
	 * whatever the value, it will never be less than 1 day. */
	public function setCooldown($days=false)
	{
		if( empty($days) )
			$this->cooldown = \LWS\WOOREWARDS\Conveniencies\Duration::days(1);
		else if( is_a($days, '\LWS\WOOREWARDS\Conveniencies\Duration') )
			$this->cooldown = $days;
		else
			$this->cooldown = \LWS\WOOREWARDS\Conveniencies\Duration::fromString($days);
		if( $this->cooldown->getCount() < 1 )
			$this->cooldown = \LWS\WOOREWARDS\Conveniencies\Duration::days(1);
		return $this;
	}

	/** @return a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Recurrent visit", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		if( !(defined('DOING_AJAX') && DOING_AJAX) )
			\add_action('init', array($this, 'trigger'));
	}

	function trigger()
	{
		if( !empty($userId = \get_current_user_id()) )
		{
			$ok = true;
			$metakey = $this->getType() . '-' . $this->getId();

			// check if too soon
			$today = \date_create()->setTime(0, 0);
			$visit = \get_user_meta($userId, $metakey, true);
			if( !empty($visit) && !empty($visit = \date_create($visit)) )
			{
				$visit->setTime(0, 0)->add($this->getCooldown()->toInterval());
				$ok = ($visit <= $today);
			}

			if( $ok )
			{
				$this->addPoint($userId, __("Recurrent visit", LWS_WOOREWARDS_PRO_DOMAIN));
				\update_user_meta($userId, $metakey, $today->format('Y-m-d'));
			}
		}
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'site' => __("Website", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}
}

?>
