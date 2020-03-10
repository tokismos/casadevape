<?php
namespace LWS\WOOREWARDS\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn point the first time a customer complete an order. */
class FirstOrder extends \LWS\WOOREWARDS\Abstracts\Event
{
	public function getDisplayType()
	{
		return _x("Place a first order", "getDisplayType", LWS_WOOREWARDS_DOMAIN);
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
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), 100);
	}

	function orderDone($order)
	{
		$userId = $order->order->get_customer_id('edit');
		$this->user = empty($userId) ? false : \get_user_by('ID', $userId);
		if( $this->user == false )
			return $order;

		if( $this->getOrderCount($userId, $order->order_id) > 0 )
			return $order;

		$reason = sprintf(__("First customer order #%s", LWS_WOOREWARDS_DOMAIN), $order->order->get_order_number());
		$this->addPoint($userId, $reason);

		return $order;
	}

	protected function getOrderCount($userId, $exceptOrderId=false)
	{
		$args = array($userId);
		global $wpdb;

		$sql = "SELECT COUNT(ID) FROM {$wpdb->posts}
INNER JOIN {$wpdb->postmeta} ON ID=post_id AND meta_key='_customer_user' AND meta_value=%d
WHERE post_type='shop_order'";

		if( !empty($exceptOrderId) )
		{
			$args[] = $exceptOrderId;
			$sql .= " AND ID<>%d";
		}

		return $wpdb->get_var($wpdb->prepare($sql, $args));
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