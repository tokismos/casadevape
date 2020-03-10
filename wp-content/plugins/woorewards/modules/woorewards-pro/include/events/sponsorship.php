<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each time a customer denounces a friend. */
class Sponsorship extends \LWS\WOOREWARDS\Abstracts\Event
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
		return _x("Sponsor a friend", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_sponsorship_done', array($this, 'trigger'), 10, 2);
	}

	function trigger($sponsor, $sponsored)
	{
		$reason = sprintf(__("Customer sponsors %s", LWS_WOOREWARDS_PRO_DOMAIN), $sponsored);
		$this->addPoint($sponsor->ID, $reason);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'sponsorship' => __("Available for sponsored", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}
}

?>