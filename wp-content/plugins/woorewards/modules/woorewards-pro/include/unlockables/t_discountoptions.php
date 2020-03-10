<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Shared options through all kind of shop_coupon unlockable.
 * use @see FreeProduct @see Coupon
 *
 * Manage options:
 * * indivdual use (bool)
 * * exclude on sale items (bool)
 * * minimum order amount to use the shop coupon in an order (float) (!= min amount to earn points in an Event)
 */
trait T_DiscountOptions
{
	public function isIndividualUse()
	{
		return isset($this->individualUse) ? $this->individualUse : false;
	}

	public function isExcludeSaleItems()
	{
		return isset($this->excludeSaleItems) ? $this->excludeSaleItems : false;
	}

	public function getOrderMinimumAmount()
	{
		return isset($this->orderMinimumAmount) ? $this->orderMinimumAmount : '';
	}

	public function getCouponExcerpt()
	{
		return isset($this->couponExcerpt) ? $this->couponExcerpt : '';
	}

	public function setIndividualUse($yes=false)
	{
		$this->individualUse = boolval($yes);
		return $this;
	}

	public function setExcludeSaleItems($yes=false)
	{
		$this->excludeSaleItems = boolval($yes);
		return $this;
	}

	public function setOrderMinimumAmount($amount=0.0)
	{
		$this->orderMinimumAmount = max(0.0, floatval(str_replace(',', '.', $amount)));
		if( $this->orderMinimumAmount == 0.0 )
			$this->orderMinimumAmount = '';
		return $this;
	}

	public function setCouponExcerpt($excerpt)
	{
		$this->couponExcerpt = $excerpt;
		return $this;
	}

	protected function filterCouponPostData($coupon=array(), $code, \WP_User $user)
	{
		$coupon->set_props(array(
			'minimum_amount'     => $this->getOrderMinimumAmount(),
			'exclude_sale_items' => $this->isExcludeSaleItems(),
			'individual_use'     => $this->isIndividualUse()
		));
		return $coupon;
	}

	protected function filterData($data=array(), $prefix='', $min=false)
	{
		$data[$prefix.'minimum_amount']     = $this->getOrderMinimumAmount();
		$data[$prefix.'exclude_sale_items'] = $this->isExcludeSaleItems() ? 'on' : '';
		$data[$prefix.'individual_use']     = $this->isIndividualUse() ? 'on' : '';
		$data[$prefix.'coupon_excerpt']     = $this->getCouponExcerpt();
		return $data;
	}

	protected function filterForm($content='', $prefix='', $context='editlist', $column=2)
	{

		// coupon description
		$label = _x("Coupon description", "Unlockable coupon buildup", LWS_WOOREWARDS_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("Text used for generated coupon.<br/>Here, the balise <b>[expiry]</b> will be replaced by the computed coupon expiry date.<br/>If omitted, reward description will be used.", LWS_WOOREWARDS_PRO_DOMAIN), 'lws-wr-coupon-descr');
		$value = isset($this->couponExcerpt) ? \htmlspecialchars($this->couponExcerpt, ENT_QUOTES) : '';
		$descr  = "<tr class='lws_advanced_option'><td class'lcell' nowrap><label for='{$prefix}coupon_excerpt' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$descr .= "<td class='rcell'><div class='lws-editlist-fs-table-row-rcell lws-$context-opt-input'>";
		$descr .= "<textarea id='{$prefix}coupon_excerpt' name='{$prefix}coupon_excerpt' >$value</textarea>";
		$descr .= "</div></td></tr>";
		$descr .= $this->getFieldsetPlaceholder(false, 0);
		$content = str_replace($this->getFieldsetPlaceholder(false, 0), $descr, $content);

		$str = '';

		// minimum amount
		$label = _x("Minimum spend", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = \esc_attr($this->getOrderMinimumAmount());
		$str .= "<tr><td class='lcell' nowrap>";
		$str .= "<label for='{$prefix}minimum_amount' class='lws-$context-opt-title'>$label</label></td>";
		$str .= "<td class='rcell'>";
		$str .= "<div class='lws-$context-opt-input'><input type='text' id='{$prefix}minimum_amount' name='{$prefix}minimum_amount' value='$value' placeholder='' pattern='\\d*(\\.|,)?\\d*' /></div>";
		$str .= "</td></tr>";

		// individual use on/off
		$label = _x("Individual use only", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$checked = ($this->isIndividualUse() ? ' checked' : '');
		$str .= "<tr><td class='lcell' nowrap>";
		$str .= "<label for='{$prefix}individual_use' class='lws-$context-opt-title'>$label</label></td>";
		$str .= "<td class='rcell'>";
		$str .= "<div class='lws-$context-opt-input'><input type='checkbox'$checked id='{$prefix}individual_use' name='{$prefix}individual_use' class='lws_checkbox'/></div>";
		$str .= "</td></tr>";

		// exclude sale items on/off
		$label = _x("Exclude sale items", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$checked = ($this->isExcludeSaleItems() ? ' checked' : '');
		$str .= "<tr><td class='lcell' nowrap>";
		$str .= "<label for='{$prefix}exclude_sale_items' class='lws-$context-opt-title'>$label</label></td>";
		$str .= "<td class='rcell'>";
		$str .= "<div class='lws-$context-opt-input'><input type='checkbox'$checked id='{$prefix}exclude_sale_items' name='{$prefix}exclude_sale_items' class='lws_checkbox'/></div>";
		$str .= "</td></tr>";

		$str .= $this->getFieldsetPlaceholder(false, $column);
		return str_replace($this->getFieldsetPlaceholder(false, $column), $str, $content);
	}

	/** @return bool */
	protected function optSubmit($prefix='', $form=array(), $source='editlist')
	{
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'minimum_amount'     => 'f',
				$prefix.'individual_use'     => 's',
				$prefix.'exclude_sale_items' => 's',
				$prefix.'coupon_excerpt'     => 't',
			),
			'defaults' => array(
				$prefix.'minimum_amount'     => '',
				$prefix.'individual_use'     => '',
				$prefix.'exclude_sale_items' => '',
				$prefix.'coupon_excerpt'     => '',
			),
			'labels'   => array(
				$prefix.'minimum_amount'     => __("Minimum spend", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'individual_use'     => __("Individual use only", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'exclude_sale_items' => __("Exclude sale items", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'coupon_excerpt'     => __("Coupon description", LWS_WOOREWARDS_PRO_DOMAIN),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$this->setOrderMinimumAmount($values['values'][$prefix.'minimum_amount']);
		$this->setIndividualUse     ($values['values'][$prefix.'individual_use']);
		$this->setExcludeSaleItems  ($values['values'][$prefix.'exclude_sale_items']);
		$this->setCouponExcerpt     ($values['values'][$prefix.'coupon_excerpt']);
		return true;
	}

	protected function optFromPost(\WP_Post $post)
	{
		$this->setOrderMinimumAmount(\get_post_meta($post->ID, 'minimum_amount', true));
		$this->setIndividualUse     (\get_post_meta($post->ID, 'individual_use', true));
		$this->setExcludeSaleItems  (\get_post_meta($post->ID, 'exclude_sale_items', true));
		$this->setCouponExcerpt     (\get_post_meta($post->ID, 'coupon_excerpt', true));
		return $this;
	}

	protected function optSave($id)
	{
		\update_post_meta($id, 'minimum_amount',     $this->getOrderMinimumAmount());
		\update_post_meta($id, 'individual_use',     $this->isIndividualUse() ? 'on' : '');
		\update_post_meta($id, 'exclude_sale_items', $this->isExcludeSaleItems() ? 'on' : '');
		\update_post_meta($id, 'coupon_excerpt',     $this->getCouponExcerpt());
		return $this;
	}

	protected function getCustomExcerpt($user)
	{
		$txt = $this->getCouponExcerpt();
		if( !empty($txt) )
		{
			$expiry = !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate() : false;
			$txt = $this->expiryInText($txt, $expiry);
		}
		else
		{
			$txt = $this->getCustomDescription(false);
			if( empty($txt) )
				$txt = $this->getCouponDescription('frontend', \date_create());
		}
		return $txt;
	}

	/** Loop through random coupon code until find one unique for the user. */
	protected function uniqueCode(\WP_User $user, $length = 10)
	{
		global $wpdb;
		$code = $this->randString($length);
		$sql = "select count(*) from {$wpdb->posts} as p";
		$sql .= " LEFT JOIN {$wpdb->postmeta} as m ON m.post_id=p.ID AND m.meta_key='customer_email' AND m.meta_value=%s";
		$sql .= " where post_title=%s AND post_type='shop_coupon'";
		while (0 < $wpdb->get_var($wpdb->prepare($sql, serialize(array($user->user_email)), $code)))
			$code = $this->randString($length);
		return $code;
	}

	/** generate a random coupon label */
	protected function randString($length = 10)
	{
		$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for( $i = 0; $i < $length; $i++ ) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	/** if permanent, invalidates the old ones. */
	function setPermanentcoupon($coupon, $user, $unlockType)
	{
		\update_post_meta($coupon->get_id(), 'woorewards_permanent', 'on');

		global $wpdb; // get all other post coming from same unlockable type with permanent mark.
		$sql = <<<EOT
SELECT p.ID, ucount.meta_value as usage_count FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as orig ON p.ID=orig.post_id AND orig.meta_key='reward_origin' AND orig.meta_value=%s
INNER JOIN {$wpdb->postmeta} as perm ON p.ID=perm.post_id AND perm.meta_key='woorewards_permanent' AND perm.meta_value='on'
INNER JOIN {$wpdb->postmeta} as mail ON p.ID=mail.post_id AND mail.meta_key='customer_email' AND mail.meta_value=%s
LEFT JOIN {$wpdb->postmeta} as ucount ON p.ID=ucount.post_id AND ucount.meta_key='usage_count'
WHERE p.ID<>%d
EOT;
		$args = array(
			$unlockType,
			serialize(array($user->user_email)),
			$coupon->get_id()
		);

		foreach( $wpdb->get_results($wpdb->prepare($sql, $args)) as $old )
		{
			$limit = max(intval($old->usage_count), 1);
			\update_post_meta($old->ID, 'usage_count', $limit);
			\update_post_meta($old->ID, 'usage_limit_per_user', $limit);
			\update_post_meta($old->ID, 'usage_limit', $limit);
		}
	}

	function getPartialDescription($context='backend')
	{
		$descr = array();
		if( $min = $this->isIndividualUse() )
			$descr[] = __("individual use only", LWS_WOOREWARDS_PRO_DOMAIN);
		if( $min = $this->isExcludeSaleItems() )
			$descr[] = __("exclude sale items", LWS_WOOREWARDS_PRO_DOMAIN);
		if( floatval($min = $this->getOrderMinimumAmount()) > 0.0 )
		{
			$value = (\LWS_WooRewards::isWC() && $context != 'edit') ? \wc_price($min) : \number_format_i18n($min, 2);
			$descr[] = sprintf(__("required %s minimal amount", LWS_WOOREWARDS_PRO_DOMAIN), $value);
		}
		return implode(', ', $descr);
	}

	/** replace the balise [expiry] by the computed expiration date */
	protected function expiryInText($text, $expiry, $dft='')
	{
		$date = $expiry ? \date_i18n(\get_option('date_format'), $expiry->getTimestamp()) : $dft;
		return str_replace('[expiry]', $date, $text);
	}

}