<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** @return array(product_id => object{quantity, ersatz}) to exclude from earning.
 * With ersatz a array of product ids (as key)
 * product_id and ersatz share their quantities.
 * So using a product_id, you can remove all ersatz from the array.
 * A negative quantity means no limit. */
trait T_ExcludedProducts
{
	function getExclusionFromOrder(\WC_Order $order)
	{
		$excluded = array();
		foreach( $order->get_items('coupon') as $appliedCoupon )
		{
			$coupon = new \WC_Coupon($appliedCoupon->get_code());
			if( !empty($coupon) && $this->isFreeProductCoupon($coupon) )
			{
				$limit = $coupon->get_limit_usage_to_x_items('edit');
				$products = $coupon->get_product_ids();
				$excluded = $this->mergeExclusion($excluded, $products, $limit);
			}
		}
		return $excluded;
	}

	function getExclusionFromCart(\WC_Cart $cart)
	{
		$excluded = array();
		foreach( $cart->get_coupons() as $coupon )
		{
			if( $this->isFreeProductCoupon($coupon) )
			{
				$limit = $coupon->get_limit_usage_to_x_items('edit');
				$products = $coupon->get_product_ids();
				$excluded = $this->mergeExclusion($excluded, $products, $limit);
			}
		}
		return $excluded;
	}

	/** @param $excluded (in/out) result of getExclusionFromCart() or getExclusionFromOrder()
	 * @param $product (WC_Product) expected product
	 * @param $quantity expected quantity
	 * @return the not excluded quantity rest.
	 * If all excluded product quantity is used the product and all its ersatz are removed from the array.
	 * else the quantity of the product and all its ersatz is decreased by $quantity. */
	function useExclusion(&$excluded, \WC_Product $product, $quantity)
	{
		$productId = $product->get_id();
		if( !isset($excluded[$productId]) && $product->is_type('variation') )
			$productId = $product->get_parent_id();

		$rest = $quantity;
		if( isset($excluded[$productId]) )
		{
			if( $excluded[$productId]->quantity <= 0 )
			{
				$rest = 0;
			}
			else
			{
				if( $excluded[$productId]->quantity > $quantity )
				{
					$rest = 0;
					$excluded[$productId]->quantity -= $quantity;
					foreach( $excluded[$productId]->ersatz as $id => $ignored )
						$excluded[$id]->quantity = $excluded[$productId]->quantity;
				}
				else
				{
					$rest = $quantity - $excluded[$productId]->quantity;
					$ersatz = $excluded[$productId]->ersatz;
					foreach( $ersatz as $id => $ignored )
						unset($excluded[$id]);
				}
			}
		}
		return $rest;
	}

	protected function mergeExclusion($excluded, $products, $count)
	{
		$products = array_combine($products, array_pad(array(), count($products), true));
		foreach( $products as $productId => $ignored )
		{
			if( isset($excluded[$productId]) )
			{
				if( $count <= 0 || $excluded[$productId]->quantity <= 0 )
					$excluded[$productId]->quantity	= -1;
				else
					$excluded[$productId]->quantity += $count;
				$excluded[$productId]->ersatz = array_merge($excluded[$productId]->ersatz, $products);
			}
			else
				$excluded[$productId] = (object)array(
					'quantity' => $count > 0 ? $count : -1,
					'ersatz' => $products
 				);
		}
		return $excluded;
	}

	protected function isFreeProductCoupon($coupon)
	{
		if( $coupon->get_discount_type() != 'percent' )
			return false;
		if( count($coupon->get_product_ids()) <= 0 )
			return false;
		if( \get_post_meta($coupon->get_id(), 'woorewards_freeproduct', true) != 'yes' )
			return false;
		return true;
	}
}
