<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points when a bought product belong to a specified category. */
class BuyInCategory extends \LWS\WOOREWARDS\Abstracts\Event
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	use \LWS\WOOREWARDS\PRO\Events\T_ExcludedProducts;

	function getDescription($context='backend')
	{
		$categories = $this->getProductCategories();
		$msg = '';
		if( $this->getMinProductCount() > 1 )
		{
			$msg = _n('Buy at least %2$d products in category %1$s', 'Buy at least %2$d products in categories %1$s', count($categories), LWS_WOOREWARDS_PRO_DOMAIN);
			return sprintf($msg, $this->getProductCategoriesNames($categories, $context), $this->getMinProductCount());
		}
		else
		{
			$msg = _n("Buy products in category %s", "Buy products in categories %s", count($categories), LWS_WOOREWARDS_PRO_DOMAIN);
			return sprintf($msg, $this->getProductCategoriesNames($categories, $context));
		}
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

	/** add help about how it works */
	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Product categories", LWS_WOOREWARDS_PRO_DOMAIN),'col50');

		// The product category
		$label   = _x("Categories", "Buy In Category Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}product_cat' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'><div class='lws-$context-opt-input'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'product_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'product_cat'),
			'value' => $this->getProductCategories()
		));
		$form .= "</div></td></tr>";

		// multiply by quantity
		$label   = _x("Quantity Multiplier", "Buy In Category Event", LWS_WOOREWARDS_PRO_DOMAIN);
		if( $context == 'achievements' )
			$tooltip = \lws_get_tooltips_html(__("If checked, action will be counted once per product in the cart meeting the conditions. Otherwise, only once per order.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-buycat-multiply');
		else
			$tooltip = \lws_get_tooltips_html(__("If checked, points will be earned for each product in the cart meeting the conditions. Otherwise, points will be earned only once per order", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-buycat-multiply');
		$checked = $this->isQtyMultiply() ? 'checked' : '';
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}qty_multiply' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input class='lws_checkbox' type='checkbox' id='{$prefix}qty_multiply' name='{$prefix}qty_multiply' $checked/></div>";
		$form .= "</td></tr>";

		// value
		$label = _x("Minimum product count", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = \esc_attr($this->getMinProductCount());
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}product_count' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input type='text' id='{$prefix}product_count' name='{$prefix}product_count' value='$value' placeholder='1' pattern='\\d*(\\.|,)?\\d*' /></div>";
		$form .= "</td></tr>";


		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'product_cat'] = base64_encode(json_encode($this->getProductCategories()));
		$data[$prefix.'product_count'] = $this->getMinProductCount();
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
				$prefix.'product_cat' => array('D'),
				$prefix.'product_count' => 'D',
				$prefix.'qty_multiply' => 's',
			),
			'defaults' => array(
				$prefix.'product_count' => '1',
				$prefix.'qty_multiply' => ''
			),
			'required' => array(
				$prefix.'product_cat' => true
			),
			'labels'   => array(
				$prefix.'product_cat'   => __("Product category", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'product_count' => __("Minimum product count", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'qty_multiply' => __("Quantity Multiplier", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setProductCategories	($values['values'][$prefix.'product_cat']);
			$this->setMinProductCount	($values['values'][$prefix.'product_count']);
			$this->setQtyMultiply     ($values['values'][$prefix.'qty_multiply']);
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

	function getMinProductCount()
	{
		return isset($this->minProductCount) ? $this->minProductCount : 1;
	}

	function setMinProductCount($n)
	{
		$this->minProductCount = max(1, intval($n));
		return $this;
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

	protected function _fromPost(\WP_Post $post)
	{
		$this->setProductCategories(\get_post_meta($post->ID, 'wre_event_product_cat', true));
		$this->setMinProductCount(\get_post_meta($post->ID, 'wre_event_min_product_count', true));
		$this->setQtyMultiply(\get_post_meta($post->ID, 'wre_event_qty_multiply', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_product_cat', $this->getProductCategories());
		\update_post_meta($id, 'wre_event_min_product_count', $this->getMinProductCount());
		\update_post_meta($id, 'wre_event_qty_multiply', $this->isQtyMultiply() ? 'on' : '');
		return $this;
	}

	/** @return a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Buy in category", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), 50);
	}

	function orderDone($order)
	{
		if( empty($categories = $this->getProductCategories()) )
			return $order;
		$userId = $order->order->get_customer_id('edit');
		$this->user = empty($userId) ? false : \get_user_by('ID', $userId);
		if( $this->user == false )
			return $order;

		$noEarning = $this->getExclusionFromOrder($order->order);
		$floor = $this->getMinProductCount();
		$boughtInCat = 0;
		foreach( $order->items as $item )
		{
			$product = $order->order->get_product_from_item($item->item);
			if( !empty($product) && $this->isProductInCategory($product, $categories) )
			{
				$qty = $item->item->get_quantity();
				$qty = $this->useExclusion($noEarning, $product, $qty);
				$boughtInCat += $qty;
			}
		}
		if( $boughtInCat < $floor )
			return $order;

		$reason = sprintf(
			_n("Product bought in category %s", "Product bought in categories %s", count($categories), LWS_WOOREWARDS_PRO_DOMAIN),
			$this->getProductCategoriesNames($categories, 'raw')
		);
		//Take quantity ordered into account or not
		$pointsCount = ($this->isQtyMultiply() ? $boughtInCat : 1);

		$this->addPoint($userId, $reason, $pointsCount);
		return $order;
	}

	private function isProductInCategory($product, $whiteList, $taxonomy='product_cat')
	{
		$product_cats = \wc_get_product_cat_ids($product->get_id());
		if( !empty($parentId = $product->get_parent_id()) )
			$product_cats = array_merge($product_cats, \wc_get_product_cat_ids($parentId));

		// If we find an item with a cat in our allowed cat list, the product is valid.
		return !empty(array_intersect($product_cats, $whiteList));
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
		if( $this->getMinProductCount() == 1 && !empty($categories = $this->getProductCategories()) )
		{
			if( $this->isProductInCategory($product, $categories) )
				return $this->getMultiplier();
		}
		return 0;
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		if( !empty($categories = $this->getProductCategories()) )
		{
			$noEarning = $this->getExclusionFromCart($cart);
			$floor = $this->getMinProductCount();
			$boughtInCat = 0;
			foreach( $cart->get_cart() as $item )
			{
				if( isset($item['product_id']) && !empty($product = \wc_get_product($item['product_id'])) )
				{
					if( $this->isProductInCategory($product, $categories) )
					{
						$qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
						$qty = $this->useExclusion($noEarning, $product, $qty);
						$boughtInCat += $qty;
					}
				}
			}
			if( $boughtInCat >= $floor ){
				$pointsCount = ($this->isQtyMultiply() ? $boughtInCat : 1);
				return $this->getMultiplier() * $pointsCount ;
			}
		}
		return 0;
	}
}

?>
