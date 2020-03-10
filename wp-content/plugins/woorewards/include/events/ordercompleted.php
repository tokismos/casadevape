<?php
namespace LWS\WOOREWARDS\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn point each time a customer complete an order. */
class OrderCompleted extends \LWS\WOOREWARDS\Abstracts\Event
{
	public function getDisplayType()
	{
		return _x("Place an order", "getDisplayType", LWS_WOOREWARDS_DOMAIN);
	}

	protected function _fromPost(\WP_Post $post)
	{
		return $this;
	}

	protected function _save($id)
	{
		return $this;
	}

	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), 101);
	}

	function orderDone($order)
	{
		$userId = $order->order->get_customer_id('edit');
		$this->user = empty($userId) ? false : \get_user_by('ID', $userId);
		if( $this->user == false )
			return $order;

		$reason = sprintf(__("Order #%s completed", LWS_WOOREWARDS_DOMAIN), $order->order->get_order_number());
		$this->addPoint($userId, $reason);

		return $order;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", LWS_WOOREWARDS_DOMAIN),
			'order' => __("Order", LWS_WOOREWARDS_DOMAIN)
		));
	}
}

?>