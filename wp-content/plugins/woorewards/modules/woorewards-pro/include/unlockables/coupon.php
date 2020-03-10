<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**
 * Append attributes to free version unlockable coupon as:
 * * permanent @see WC\Cart */
class Coupon extends \LWS\WOOREWARDS\Unlockables\Coupon
{
	use \LWS\WOOREWARDS\PRO\Unlockables\T_DiscountOptions;

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Unlockables\Coupon';
	}

	public function isPermanent()
	{
		return isset($this->permanent) ? $this->permanent : false;
	}

	public function setPermanent($yes=false)
	{
		$this->permanent = boolval($yes);
		return $this;
	}

	public function getItemsUsageLimit()
	{
		return isset($this->itemsUsageLimit) ? $this->itemsUsageLimit : '';
	}

	public function setItemsUsageLimit($limit=0)
	{
		$this->itemsUsageLimit = \absint($limit);
		if( $this->itemsUsageLimit == 0 )
			$this->itemsUsageLimit = '';
		return $this;
	}

	function getExcludedCategories()
	{
		return isset($this->excludedCategories) ? $this->excludedCategories : array();
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setExcludedCategories($categories=array())
	{
		if( !is_array($categories) )
			$categories = @json_decode(@base64_decode($categories));
		if( is_array($categories) )
			$this->excludedCategories = $categories;
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

	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'permanent'] = ($this->isPermanent() ? 'on' : '');
		$data[$prefix.'limit_usage_to_x_items'] = $this->getItemsUsageLimit();
		$data[$prefix.'product_cat'] = base64_encode(json_encode($this->getProductCategories()));
		$data[$prefix.'exclude_cat'] = base64_encode(json_encode($this->getExcludedCategories()));
		return $this->filterData($data, $prefix, $min);
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		if( $this->getPoolType() == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING )
		{
			// permanent on/off
			$label = _x("Permanent", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
			$checked = ($this->isPermanent() ? ' checked' : '');
			$str = "<tr><td class='lcell' nowrap>";
			$str .= "<label for='{$prefix}permanent' class='lws-$context-opt-title'>$label</label>";
			$str .= \lws_get_tooltips_html(__("Applied on all future orders. That reward will replace any previous permanent coupon reward of the same type owned by the customer.", LWS_WOOREWARDS_PRO_DOMAIN));
			$str .= "</td><td class='rcell'>";
			$str .= "<div class='lws-$context-opt-input'><input type='checkbox'$checked id='{$prefix}permanent' name='{$prefix}permanent' class='lws_checkbox'/>";
			$str .= "</div></td></tr>";

			$str .= $this->getFieldsetPlaceholder(false, 1);
			$form = str_replace($this->getFieldsetPlaceholder(false, 1), $str, $form);
		}
		$label = _x("Limit to X items", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = \esc_attr($this->getItemsUsageLimit());
		$str = "<tr><td class='lcell' nowrap>";
		$str .= "<label for='{$prefix}limit_usage_to_x_items' class='lws-$context-opt-title'>$label</label>";
		$str .= \lws_get_tooltips_html(__("If set, the coupon can only be applied on X items in the cart.", LWS_WOOREWARDS_PRO_DOMAIN));
		$str .= "</td><td class='rcell'>";
		$str .= "<div class='lws-$context-opt-input'><input type='text' id='{$prefix}limit_usage_to_x_items' name='{$prefix}limit_usage_to_x_items' value='$value' placeholder='' pattern='\\d*(\\.|,)?\\d*' /></div>";
		$str .= "</div></td></tr>";

		$str .= $this->getFieldsetPlaceholder(false, 1);
		$form = str_replace($this->getFieldsetPlaceholder(false, 1), $str, $form);

		// limit to X products


		$form = $this->filterForm($form, $prefix, $context, 1);

		$form .= $this->getFieldsetBegin(2, __("Allow / Deny Product Categories", LWS_WOOREWARDS_PRO_DOMAIN), 'col40');

		// restriction by product category
		$label   = _x("Product categories", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("Product categories that the coupon will be applied to, or that need to be in the cart in order for the 'Fixed cart discount' to be applied.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-product-cats-point-restriction');
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

		// exclude product category
		$label   = _x("Excluded categories", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("Product categories that the coupon will <b>not</b> be applied to, or that cannot be in the cart in order for the 'Fixed cart discount' to be applied.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-product-cats-point-restriction');
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}exclude_cat' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'><div class='lws-$context-opt-input'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'exclude_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'product_cat'),
			'value' => $this->getExcludedCategories()
		));
		$form .= "</div></td></tr>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'permanent' => 's',
				$prefix.'limit_usage_to_x_items'=> 'd',
				$prefix.'product_cat' => array('D'),
				$prefix.'exclude_cat' => array('D')
			),
			'defaults' => array(
				$prefix.'permanent' => '',
				$prefix.'limit_usage_to_x_items'=> '0',
				$prefix.'product_cat' => array(),
				$prefix.'exclude_cat' => array()
			),
			'labels'   => array(
				$prefix.'permanent'   => __("Permanent", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'limit_usage_to_x_items'=> __("Limit usage to X items", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'product_cat'   => __("Product category", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'exclude_cat'   => __("Excluded category", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true )
		{
			$this->setPermanent($values['values'][$prefix.'permanent']);
			$this->setItemsUsageLimit($values['values'][$prefix.'limit_usage_to_x_items']);
			$this->setProductCategories($values['values'][$prefix.'product_cat']);
			$this->setExcludedCategories($values['values'][$prefix.'exclude_cat']);
		}
		return $valid;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setProductCategories(\get_post_meta($post->ID, 'wre_unlockable_product_cat', true));
		$this->setExcludedCategories(\get_post_meta($post->ID, 'wre_unlockable_exclude_cat', true));
		$this->setPermanent(\get_post_meta($post->ID, 'woorewards_permanent', true));
		$this->setItemsUsageLimit(\get_post_meta($post->ID, 'limit_usage_to_x_items', true));
		$this->optFromPost($post);
		return parent::_fromPost($post);
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_unlockable_product_cat', $this->getProductCategories());
		\update_post_meta($id, 'wre_unlockable_exclude_cat', $this->getExcludedCategories());
		\update_post_meta($id, 'woorewards_permanent', $this->isPermanent() ? 'on' : '');
		\update_post_meta($id, 'limit_usage_to_x_items', $this->getItemsUsageLimit());
		$this->optSave($id);
		return parent::_save($id);
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

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		$descr = parent::getDescription($context);
		if( $this->isPermanent() )
		{
			$attr = _x("permanent", "Coupon", LWS_WOOREWARDS_PRO_DOMAIN);
			$descr .= $context=='edit' ? " ($attr)" : " (<i>$attr</i>)";
		}

		if( !empty($discount = $this->getPartialDescription($context)) )
			$descr .= (', ' . $discount);

		$categories = $this->getProductCategories();
		if( !empty($categories) )
		{
			$msg = _n("Apply only on category %s", "Apply only on categories %s", count($categories), LWS_WOOREWARDS_PRO_DOMAIN);
			$descr .= sprintf('<br/>'.$msg, $this->getProductCategoriesNames($categories, $context));
		}
		$categories = $this->getExcludedCategories();
		if( !empty($categories) )
		{
			$msg = _n("Exclude category %s", "Exclude categories %s", count($categories), LWS_WOOREWARDS_PRO_DOMAIN);
			$descr .= sprintf('<br/>'.$msg, $this->getProductCategoriesNames($categories, $context));
		}
		return $descr;
	}

	protected function buildCouponPostData($code, \WP_User $user)
	{
		$coupon = parent::buildCouponPostData($code, $user);
		$props = array();
		if( $this->isPermanent() )
		{
				$props['usage_limit']          = 0;
				$props['usage_limit_per_user'] = 0;
		}

		if( $this->getItemsUsageLimit() > 0 )
			$props['limit_usage_to_x_items'] = $this->getItemsUsageLimit();

		if( !empty($categories = $this->getProductCategories()) )
			$props['product_categories'] = array_filter(array_map('intval', $categories));

		if( !empty($categories = $this->getExcludedCategories()) )
			$props['excluded_product_categories'] = array_filter(array_map('intval', $categories));

		if( !empty($props) )
			$coupon->set_props($props);
		return $this->filterCouponPostData($coupon, $code, $user);
	}

	protected function createShopCoupon($code, \WP_User $user, $demo=false)
	{
		$coupon = parent::createShopCoupon($code, $user, $demo);
		if( !$demo && $this->isPermanent() && $coupon && !empty($coupon->get_id()) )
		{
			$this->setPermanentcoupon($coupon, $user, $this->getType());
		}
		return $coupon;
	}
}

?>
