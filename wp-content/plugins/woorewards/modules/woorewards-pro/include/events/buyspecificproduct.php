<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points when a specified product is bought. */
class BuySpecificProduct extends \LWS\WOOREWARDS\Abstracts\Event
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	use \LWS\WOOREWARDS\PRO\Events\T_ExcludedProducts;

	function getDescription($context='backend')
	{
		$name = '?';
		if( $context == 'backend' )
			$name = $this->getProductEditLink();
		else if( $context == 'raw' )
			$name = $this->getProductName();
		else
			$name = $this->getProductLink();
		return sprintf(__("Buy product %s", LWS_WOOREWARDS_PRO_DOMAIN), $name);
	}

	/** add help about how it works */
	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Product to buy", LWS_WOOREWARDS_PRO_DOMAIN),'col50');

		// product
		$label = _x("Product", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = $this->getProductId();
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}product_id' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'>";
		$form .= "<input id='{$prefix}product_id' name='{$prefix}product_id' class='lac_select' data-ajax='lws_woorewards_wc_product_list' data-mode='research' value='$value'>";
		$form .= "</div></td></tr>";

		// multiply by quantity
		$label   = _x("Quantity Multiplier", "Buy Specific Product Event", LWS_WOOREWARDS_PRO_DOMAIN);
		if( $context == 'achievements' )
			$tooltip = \lws_get_tooltips_html(__("If checked, action will be counted once per bought product. Otherwise, only once per order containing the product.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-buycat-multiply');
		else
			$tooltip = \lws_get_tooltips_html(__("If checked, points will be earned for each product in the cart meeting the conditions. Otherwise, points will be earned only once per order", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-buycat-multiply');
		$checked = $this->isQtyMultiply() ? 'checked' : '';
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}qty_multiply' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input class='lws_checkbox' type='checkbox' id='{$prefix}qty_multiply' name='{$prefix}qty_multiply' $checked/></div>";
		$form .= "</td></tr>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'product_id'] = $this->getProductId();
		$data[$prefix.'qty_multiply'] = $this->isQtyMultiply() ? 'on' : '';
		return $data;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'product_id' => 'D',
				$prefix.'qty_multiply' => 's',
			),
			'defaults' => array(
				$prefix.'qty_multiply' => ''
			),
			'labels'   => array(
				$prefix.'product_id'   => __("Product", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'qty_multiply' => __("Quantity Multiplier", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setProductId		($values['values'][$prefix.'product_id']);
			$this->setQtyMultiply   ($values['values'][$prefix.'qty_multiply']);
		}
		return $valid;
	}

	function isQtyMultiply()
	{
		return isset($this->qtyMultiply) && $this->qtyMultiply;
	}

	public function setQtyMultiply($yes=false)
	{
		$this->qtyMultiply = boolval($yes);
		return $this;
	}

	public function getProductId()
	{
		return isset($this->productId) ? $this->productId : false;
	}

	/** @return (false|WC_Product) */
	public function getProduct()
	{
		if( \LWS_WooRewards::isWC() && !empty($id = $this->getProductId()) ){
			return \wc_get_product($id);
		}
		return false;
	}

	public function getProductName()
	{
		if( !empty($product = $this->getProduct()) )
			return $product->get_title();
		return false;
	}

	public function getProductUrl()
	{
		if( !empty($product = $this->getProduct()) )
			return \get_permalink($product->get_id());
		return false;
	}

	/** @return html <a> */
	public function getProductLink()
	{
		if( !empty($product = $this->getProduct()) )
		{
			return sprintf(
				"<a target='_blank' href='%s' class='lws-woorewards-free-product-link'>%s</a>",
				\esc_attr(\get_permalink($product->get_id())),
				$product->get_title()
			);
		}
		return false;
	}

	/** @return html <a> */
	public function getProductEditLink()
	{
		if( !empty($product = $this->getProduct()) )
		{
			return sprintf(
				"<a href='%s'>%s</a>",
				\esc_attr(\get_edit_post_link($product->get_id())),
				$product->get_title()
			);
		}
		return false;
	}

	public function setProductId($id=0)
	{
		$this->productId = \absint($id);
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setProductId(\get_post_meta($post->ID, 'wre_event_product_id', true));
		$this->setQtyMultiply(\get_post_meta($post->ID, 'wre_event_qty_multiply', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_product_id', $this->getProductId());
		\update_post_meta($id, 'wre_event_qty_multiply', $this->isQtyMultiply() ? 'on' : '');
		return $this;
	}

	/** @return a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Buy a specific product", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), 40);
	}

	function orderDone($order)
	{
		if( empty($pId = $this->getProductId()) )
			return $order;
		$pId = \apply_filters('wpml_object_id', $pId, 'product', true);
		$userId = $order->order->get_customer_id('edit');
		$this->user = empty($userId) ? false : \get_user_by('ID', $userId);
		if( $this->user == false )
			return $order;

		$noEarning = $this->getExclusionFromOrder($order->order);
		$name = '';
		$boughtCount = 0;
		foreach( $order->items as $item )
		{
			$product = $order->order->get_product_from_item($item->item);
			if( !empty($product) && (\apply_filters('wpml_object_id', $product->get_id(), 'product', true) == $pId
			|| ($product->is_type('variation') && \apply_filters('wpml_object_id', $product->get_parent_id(), 'product', true) == $pId)) )
			{
				$qty = $item->item->get_quantity();
				$qty = $this->useExclusion($noEarning, $product, $qty);
				if( $qty > 0 )
				{
					$name = $product->get_title();
					$boughtCount += $qty;
				}
			}
		}

		if( $boughtCount > 0 )
		{
			$reason = empty($name) ? __("Specific product bought", LWS_WOOREWARDS_PRO_DOMAIN) : sprintf(__("Bought a %s", LWS_WOOREWARDS_PRO_DOMAIN), $name);
			$pointsCount = ($this->isQtyMultiply() ? $boughtCount : 1);
			$this->addPoint($userId, $reason, $pointsCount);
		}
		return $order;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", LWS_WOOREWARDS_PRO_DOMAIN),
			'product'  => __("Product", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}

	function getPointsForProduct(\WC_Product $product)
	{
		if( !empty($pId = $this->getProductId()) )
		{
			$pId = \apply_filters('wpml_object_id', $pId, 'product', true);
			if( \apply_filters('wpml_object_id', $product->get_id(), 'product', true) == $pId )
				return $this->getMultiplier();
			if( $product->is_type('variation') && \apply_filters('wpml_object_id', $product->get_parent_id(), 'product', true) == $pId )
				return $this->getMultiplier();
		}
		return 0;
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		if( !empty($pId = $this->getProductId()) )
		{
			$pId = \apply_filters('wpml_object_id', $pId, 'product', true);
			$noEarning = $this->getExclusionFromCart($cart);
			$inCartQty = 0;

			foreach( $cart->get_cart() as $item )
			{
				if( isset($item['product_id']) && !empty($product = \wc_get_product($item['product_id'])) )
				{
					if( \apply_filters('wpml_object_id', $product->get_id(), 'product', true) == $pId
					|| ($product->is_type('variation') && \apply_filters('wpml_object_id', $product->get_parent_id(), 'product', true) == $pId) )
					{
						$qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
						$qty = $this->useExclusion($noEarning, $product, $qty);
						if( $qty > 0 )
							$inCartQty += $qty;
					}
				}
			}

			if( $inCartQty > 0 )
				return $this->getMultiplier() * ($this->isQtyMultiply() ? $inCartQty : 1);
		}
		return 0;
	}
}

?>
