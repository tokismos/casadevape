<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each time a customer denounces a friend. */
class Register extends \LWS\WOOREWARDS\Abstracts\Event
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
		return _x("User register", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('user_register', array($this, 'trigger'), 999999, 1);
	}

	function trigger($user_id)
	{
		$this->addPoint($user_id, __("User register", LWS_WOOREWARDS_PRO_DOMAIN));
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'site' => __("Website", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}
}

?>