<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Earn points for each money spend on an order. */
class OrderAmount extends \LWS\WOOREWARDS\Events\OrderAmount
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	use \LWS\WOOREWARDS\PRO\Events\T_ExcludedProducts;

	function getDescription($context='backend')
	{
		$descr = parent::getDescription($context);

		if( $this->getAfterDiscount() )
		{
			$descr .= (' '._x("(after discount)", "Earning method description", LWS_WOOREWARDS_PRO_DOMAIN));
		}
		else
		{
			$categories = $this->getProductCategories();
			if( !empty($categories) )
			{
				$msg = _n("Limited to category %s", "Limited to categories %s", count($categories), LWS_WOOREWARDS_PRO_DOMAIN);
				$descr .= sprintf('<br/>'.$msg, $this->getProductCategoriesNames($categories, $context));
			}
			$negCat = $this->getProductExcludedCategories();
			if( !empty($negCat) )
			{
				$msg = _n("Exclude category %s", "Exclude categories %s", count($negCat), LWS_WOOREWARDS_PRO_DOMAIN);
				$descr .= sprintf('<br/>'.$msg, $this->getProductCategoriesNames($negCat, $context));
			}
		}

		if( $this->getShipping() )
		{
			$descr .= '<br/>';
			$descr .= __("Include shipping", LWS_WOOREWARDS_PRO_DOMAIN);
		}
		return $descr;
	}

	protected function getProductCategoriesNames($categories, $context='backend', $sep=', ')
	{
		$descr = '';
		$mid = '';
		foreach( $categories as $cat )
		{
			$descr .= $mid;
			$term = \get_term($cat, 'product_cat');
			if( !$term || is_wp_error($term) )
			{
				$descr .= _x("Unknown", "Unknown category", LWS_WOOREWARDS_PRO_DOMAIN);
			}
			else
			{
				$name = \htmlentities($term->name);

				$url = '';
				if( $context == 'backend' )
					$url = \esc_attr(\get_edit_tag_link($cat, 'product_cat'));

				if( empty($url) )
				{
					if( $context == 'raw' )
						$descr .= $name;
					else
						$descr .= "<b>{$name}</b>";
				}
				else
					$descr .= "<a href='{$url}'>{$name}</a>";
			}
			$mid = $sep;
		}
		return $descr;
	}

	function getForm($context='editlist')
	{
		\wp_enqueue_script('lws_woorewards_orderamount', LWS_WOOREWARDS_PRO_JS . '/orderamount.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION, true);
		\wp_enqueue_style('lws_woorewards_orderamount', LWS_WOOREWARDS_PRO_CSS . '/orderamount.css', array(), LWS_WOOREWARDS_PRO_VERSION);

		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Options", LWS_WOOREWARDS_PRO_DOMAIN),'col10 pdr5');

		// shipping included
		$label   = _x("Include shipping amount", "Order Amount Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$checked = $this->getShipping() ? 'checked' : '';
		$form .= "<tr class=''><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}include_shipping' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input class='lws_checkbox' type='checkbox' id='{$prefix}include_shipping' name='{$prefix}include_shipping' $checked/></div>";
		$form .= "</td></tr>";

		// compute points after discount
		$label   = _x("Use amount after discount", "Order Amount Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("Some options are not compatible with computing points after discount and will be disabled.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-product-cats-point-restriction');
		$checked = '';//$this->isAfterDiscount() ? 'checked' : '';
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}after_discount' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input class='lws_checkbox' type='checkbox' id='{$prefix}after_discount' name='{$prefix}after_discount' $checked/></div>";
		$form .= "</td></tr>";

		// threshold effect applied
		$label   = _x("Threshold effect", "Order Amount Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("If checked, customers will earn points for each multiple of this amount. If unchecked, points earned will be rounded to the closest value.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-amount-threshold-effect');
		$checked = '';//$checked = $this->getThresholdEffect() ? 'checked' : '';
		$form .= "<tr class='lws_advanced_option'><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}threshold_effect' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input class='lws_checkbox' type='checkbox' id='{$prefix}threshold_effect' name='{$prefix}threshold_effect' $checked/></div>";
		$form .= "</td></tr>";

		$form .= $this->getFieldsetEnd(2);
		$form .= $this->getFieldsetBegin(3, __("Allow / Deny Categories", LWS_WOOREWARDS_PRO_DOMAIN),'col50 lws_woorewards_orderamount_after_discount_relative');

		// restriction by product category
		$label   = _x("Allowed categories", "Order Amount Event", LWS_WOOREWARDS_PRO_DOMAIN);
		if( $context == 'achievements' )
			$tooltip = \lws_get_tooltips_html(__("If left empty, all bought products are taken into account. If you set one or more categories, only the products which belong to these categories will be used.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-product-cats-point-restriction');
		else
			$tooltip = \lws_get_tooltips_html(__("If left empty, all bought products can give loyalty points. If you set one or more categories, only the products which belong to these categories will award points.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-product-cats-point-restriction');
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}product_cat' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'><div class='lws-$context-opt-input'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'product_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'product_cat'),
			'value' => $this->getProductCategories()
		));
		$form .= "</div></td></tr>";

		// restriction by product category blacklist
		$label   = _x("Denied categories", "Order Amount Event", LWS_WOOREWARDS_PRO_DOMAIN);
		if( $context == 'achievements' )
			$tooltip = \lws_get_tooltips_html(__("If left empty, all bought products are taken into account. If you set one or more categories, the products which belong to these categories will <b>not</b> be used.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-product-cats-point-restriction');
		else
			$tooltip = \lws_get_tooltips_html(__("If left empty, all bought products can give loyalty points. If you set one or more categories, the products which belong to these categories will <b>not</b> award points.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-product-cats-point-restriction');
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}product_neg_cat' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'><div class='lws-$context-opt-input'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'product_neg_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'product_cat'),
			'value' => $this->getProductCategories()
		));
		$form .= "</div></td></tr>";

		$form .= $this->getFieldsetEnd(3);
		return $form;
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'after_discount'] = $this->getAfterDiscount() ? 'on' : '';
		$data[$prefix.'product_cat'] = base64_encode(json_encode($this->getProductCategories()));
		$data[$prefix.'product_neg_cat'] = base64_encode(json_encode($this->getProductExcludedCategories()));
		$data[$prefix.'threshold_effect'] = $this->getThresholdEffect() ? 'on' : '';
		return $data;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'after_discount' => 's',
				$prefix.'product_cat' => array('D'),
				$prefix.'product_neg_cat' => array('D'),
				$prefix.'threshold_effect' => 's',
			),
			'defaults' => array(
				$prefix.'after_discount' => '',
				$prefix.'product_cat' => array(),
				$prefix.'product_neg_cat' => array(),
				$prefix.'threshold_effect' => '',
			),
			'labels'   => array(
				$prefix.'after_discount' => __("After Discount", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'product_cat'   => __("Product category", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'product_neg_cat'   => __("Excluded category", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setThresholdEffect(boolval($values['values'][$prefix.'threshold_effect']));
			$this->setAfterDiscount(boolval($values['values'][$prefix.'after_discount']));
			if( $this->getAfterDiscount() )
			{
				$this->setProductCategories(array());
				$this->setProductExcludedCategories(array());
			}
			else
			{
				$this->setProductCategories($values['values'][$prefix.'product_cat']);
				$this->setProductExcludedCategories($values['values'][$prefix.'product_neg_cat']);
			}
		}
		return $valid;
	}

	function getProductCategories()
	{
		return isset($this->productCategories) ? $this->productCategories : array();
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setProductCategories($categories=array())
	{
		if( !is_array($categories) )
			$categories = @json_decode(@base64_decode($categories));
		if( is_array($categories) )
			$this->productCategories = $categories;
		return $this;
	}

	function getProductExcludedCategories()
	{
		return isset($this->productExcludedCategories) ? $this->productExcludedCategories : array();
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setProductExcludedCategories($categories=array())
	{
		if( !is_array($categories) )
			$categories = @json_decode(@base64_decode($categories));
		if( is_array($categories) )
			$this->productExcludedCategories = $categories;
		return $this;
	}

	/** Compute points on final order amount,
	 *	after fees and discounts applied.
	 *  @return bool */
	public function getAfterDiscount()
	{
		return isset($this->afterDiscount) && $this->afterDiscount;
	}

	public function setAfterDiscount($yes=true)
	{
		$this->afterDiscount = $yes;
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setAfterDiscount(boolval(\get_post_meta($post->ID, 'wre_event_after_discount', true)));
		$this->setProductCategories(\get_post_meta($post->ID, 'wre_event_product_cat', true));
		$this->setProductExcludedCategories(\get_post_meta($post->ID, 'wre_event_product_neg_cat', true));
		return parent::_fromPost($post);
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_after_discount', $this->getAfterDiscount());
		\update_post_meta($id, 'wre_event_product_cat', $this->getProductCategories());
		\update_post_meta($id, 'wre_event_product_neg_cat', $this->getProductExcludedCategories());
		return parent::_save($id);
	}

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Events\OrderAmount';
	}

	/** override to take care of order content: product categories
	 * order amount is sum of accepted product prices.
	 *
	 * Shipping is still added whatever category or not */
	function getOrderAmount(&$order)
	{
		$amount = 0;
		if( $this->getAfterDiscount() )
		{
			$amount = $order->order->get_total('edit');
			$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
			if( !$inc_tax )
				$amount -= $order->order->get_total_tax('edit'); // remove shipping tax too

			if( !$this->getShipping() ) // remove shipping and shipping tax if not already done with the rest of taxes
			{
				$amount -= floatval($order->order->get_shipping_total('edit'));
				if( $inc_tax )
					$amount -= floatval($order->order->get_shipping_tax('edit'));
			}
			$amount = max(0, $amount);
		}
		else
		{
			$categories = $this->getProductCategories();
			$exclude = $this->getProductExcludedCategories();
			$noEarning = $this->getExclusionFromOrder($order->order);

			if( empty($categories) && empty($exclude) && empty($noEarning) )
			{
				$amount = parent::getOrderAmount($order);
			}
			else
			{
				$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
				foreach( $order->items as $item )
				{
					$product = $order->order->get_product_from_item($item->item);
					if( !empty($product) )
					{
						if( !empty($exclude) && $this->isProductInCategory($product, $exclude) )
							continue;
						if( !empty($categories) && !$this->isProductInCategory($product, $categories) )
							continue;

						if( empty($noEarning) )
							$amount += $item->amount;
						else
						{
							$qty = $item->item->get_quantity();
							$qty = $this->useExclusion($noEarning, $product, $qty);
							if( $qty > 0 )
							{
								if( $inc_tax )
									$amount += floatval(\wc_get_price_including_tax($product)) * $qty;
								else
									$amount += floatval(\wc_get_price_excluding_tax($product)) * $qty;
							}
						}
					}
				}

				// should be strange, but since option is still available, we Must use it if checked
				if( $this->getShipping() ) // add shipping if required, whatever category
				{
					$amount += floatval($order->order->get_shipping_total('edit'));
					if( $order->inc_tax )
						$amount += floatval($order->order->get_shipping_tax('edit'));
				}
			}
		}
		return $amount;
	}

	private function isProductInCategory($product, $whiteList, $taxonomy='product_cat')
	{
		$product_cats = \wc_get_product_cat_ids($product->get_id());
		if( !empty($parentId = $product->get_parent_id()) )
			$product_cats = array_merge($product_cats, \wc_get_product_cat_ids($parentId));

		// If we find an item with a cat in our allowed cat list, the product is valid.
		return !empty(array_intersect($product_cats, $whiteList));
	}

	function getPointsForProduct(\WC_Product $product)
	{
		$valid = true;
		if( !empty($categories = $this->getProductCategories()) && !$this->isProductInCategory($product, $categories) )
			$valid = false;
		if( !empty($exclude = $this->getProductExcludedCategories()) && $this->isProductInCategory($product, $exclude) )
			$valid = false;

		$price = 0;
		if( $valid )
		{
			if( count($product->get_children()) > 1 )
			{
				$prices = array();
				foreach( $product->get_children() as $varId )
				{
					if( $variation = wc_get_product($varId) )
					{
						// Hide out of stock variations if 'Hide out of stock items from the catalog' is checked.
						if( !$variation || !$variation->exists() || ('yes' === \get_option('woocommerce_hide_out_of_stock_items') && ! $variation->is_in_stock()) )
							continue;

						if( \method_exists($variation, 'variation_is_visible') )
						{
							// Filter 'woocommerce_hide_invisible_variations' to optionally hide invisible variations (disabled variations and variations with empty price).
							if( \apply_filters('woocommerce_hide_invisible_variations', true, $product->get_id(), $variation) && !$variation->variation_is_visible() )
								continue;
						}

						if( !empty(\get_option('lws_woorewards_order_amount_includes_taxes', '')) )
							$prices[] = floatval(\wc_get_price_including_tax($variation));
						else
							$prices[] = floatval(\wc_get_price_excluding_tax($variation));
					}
				}

				$min = min($prices);
				$max = max($prices);
				$price = ($min != $max) ? array($min, $max) : $max;
			}
			else
			{
				if( !empty(\get_option('lws_woorewards_order_amount_includes_taxes', '')) )
					$price = floatval(\wc_get_price_including_tax($product));
				else
					$price = floatval(\wc_get_price_excluding_tax($product));
			}
		}

		if( is_array($price) )
		{
			$mul = $this->getMultiplier();
			foreach( $price as &$p )
				$p = intval(round($this->getPointsForAmount($p) * $mul));
			return $price;
		}
		else
			return intval(round($this->getPointsForAmount($price) * $this->getMultiplier()));
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
		$amount = 0;

		if( $this->getAfterDiscount() )
		{
			$amount = $cart->get_total('edit');
			if( !$inc_tax )
				$amount -= $cart->get_total_tax('edit'); // remove shipping tax too

			if( !$this->getShipping() ) // remove shipping and shipping tax if not already done with the rest of taxes
			{
				$amount -= floatval($cart->get_shipping_total('edit'));
				if( $inc_tax )
					$amount -= floatval($cart->get_shipping_tax('edit'));
			}
			$amount = max(0, $amount);
		}
		else
		{
			$categories = $this->getProductCategories();
			$exclude = $this->getProductExcludedCategories();
			$noEarning = $this->getExclusionFromCart($cart);

			if( empty($categories) && empty($exclude) && empty($noEarningà) )
			{
				$amount = floatval($cart->get_subtotal());
				if( $inc_tax )
					$amount += floatval($cart->get_subtotal_tax());
			}
			else foreach( $cart->get_cart() as $item )
			{
				$pId = (isset($item['variation_id']) && $item['variation_id']) ? $item['variation_id'] : (isset($item['product_id']) ? $item['product_id'] : false);
				if( $pId && !empty($product = \wc_get_product($pId)) )
				{
					if( !empty($exclude) && $this->isProductInCategory($product, $exclude) )
						continue;
					if( !empty($categories) && !$this->isProductInCategory($product, $categories) )
						continue;

					$qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
					$qty = $this->useExclusion($noEarning, $product, $qty);
					if( $qty > 0 )
					{
						if( $inc_tax )
							$amount += floatval(\wc_get_price_including_tax($product)) * $qty;
						else
							$amount += floatval(\wc_get_price_excluding_tax($product)) * $qty;
					}
				}
			}

			if( $this->getShipping() )
			{
				$amount += floatval($cart->get_shipping_total('edit'));
				if( $inc_tax )
					$amount += floatval($cart->get_shipping_tax('edit'));
			}
		}
		return intval(round($this->getPointsForAmount($amount) * $this->getMultiplier()));
	}
}

?>
