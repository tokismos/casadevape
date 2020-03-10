<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points when a visitor register after following a referral link. */
class ReferralRegister extends \LWS\WOOREWARDS\Abstracts\Event
{
	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		return $this;
	}

	/** @return a human readable type for UI */
	public function getDisplayType()
	{
		return _x("User register with Referral", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('user_register', array($this, 'trigger'), 999999, 1);
	}

	function trigger($userId)
	{
		if( !isset($_COOKIE['lws_referral_'.COOKIEHASH]) )
			return;

		$referral = trim($_COOKIE['lws_referral_'.COOKIEHASH]);
		global $wpdb;
		$refId = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='lws_woorewards_user_referral_token' AND meta_value=%s", $referral));
		if( empty($refId) )
			return;

		if( $userId == $refId )
			return $order;

		$this->addPoint($refId, __("User register with your referral", LWS_WOOREWARDS_PRO_DOMAIN));
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'referral' => __("Referral", LWS_WOOREWARDS_PRO_DOMAIN),
			'social' => __("Social network", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}
}

?>