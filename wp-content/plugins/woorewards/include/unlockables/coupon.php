<?php
namespace LWS\WOOREWARDS\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**
 * Create a WooCommerce Coupon. */
class Coupon extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'timeout'] = $this->getTimeout()->toString();
		$data[$prefix.'value'] = $this->getValue();
		$data[$prefix.'percent'] = $this->getInPercent() ? 'per' : 'fix';
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = '';

		// percent or fixed
		$label = _x("Discount type", "Coupon Unlockable", LWS_WOOREWARDS_DOMAIN);
		$value = $this->getInPercent() ? 'per' : 'fix';
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}percent' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><select id='{$prefix}percent' name='{$prefix}percent' class='lac_select' data-mode='select'>";
		foreach( array('fix'=>__("Fixed cart discount", LWS_WOOREWARDS_DOMAIN), 'per'=>__("Percentage discount", LWS_WOOREWARDS_DOMAIN)) as $v => $l )
		{
			$selected = ($v == $value ? ' selected' : '');
			$form .= "<option value='$v'$selected>$l</option>";
		}
		$form .= "</select>";
		$form .= "</div></td></tr>";

		// value
		$label = _x("Coupon amount", "Coupon Unlockable", LWS_WOOREWARDS_DOMAIN);
		$currency = \LWS_WooRewards::isWC() ? \get_woocommerce_currency_symbol() : '$';
		$value = empty($this->getValue()) ? '' : \esc_attr($this->getValue());
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}value' class='lws-$context-opt-title'>$label (<span class='{$prefix}currency_hide currency_fix'>$currency</span><span class='{$prefix}currency_hide currency_per'>%</span>)</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input type='text' id='{$prefix}value' name='{$prefix}value' value='$value' placeholder='5' pattern='\\d*(\\.|,)?\\d*' /></div>";
		$form .= "</td></tr>";

		// timeout
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/durationfield.php';
		$label = _x("Validity period", "Coupon Unlockable", LWS_WOOREWARDS_DOMAIN);
		$value = $this->getTimeout()->toString();
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}timeout' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'>";
		$form .= \LWS\WOOREWARDS\Ui\DurationField::compose($prefix.'timeout', array('value'=>$value));
		$form .= "</div></td></tr>";

		$form .= $this->getFieldsetPlaceholder(false, 1);
		$form = str_replace($this->getFieldsetPlaceholder(false, 1), $form, parent::getForm($context));
		return $form;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'timeout' => '/(p?\d+[DYM])?/i',
				$prefix.'value'   => 'F',
				$prefix.'percent' => 's',
			),
			'defaults' => array(
				$prefix.'timeout' => '',
				$prefix.'value'   => '0',
				$prefix.'percent' => 'fix'
			),
			'labels'   => array(
				$prefix.'timeout' => __("Validity period", LWS_WOOREWARDS_DOMAIN),
				$prefix.'value'   => __("Coupon amount", LWS_WOOREWARDS_DOMAIN),
				$prefix.'percent' => __("Discount in percent or fixed price", LWS_WOOREWARDS_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setInPercent($values['values'][$prefix.'percent'] == 'per');
			if( $this->getInPercent() )
				$values['values'][$prefix.'value'] = min(100.0, $values['values'][$prefix.'value']);
			$this->setValue    ($values['values'][$prefix.'value']);
			$this->setTimeout  ($values['values'][$prefix.'timeout']);
		}
		return $valid;
	}

	public function getValue()
	{
		return isset($this->value) ? $this->value : 1;
	}

	public function setValue($value=0.0)
	{
		$this->value = floatval(str_replace(',', '.', $value));
		return $this;
	}

	public function setTestValues()
	{
		$this->setValue(rand(15, 50)/10.0);
		$this->setTimeout(rand(5, 78).'D');
		return $this;
	}

	/** @return (bool) if percent instead fix value */
	public function getInPercent()
	{
		return isset($this->inPercent) && $this->inPercent;
	}

	public function setInPercent($yes=true)
	{
		$this->inPercent = $yes;
		return $this;
	}

	/** return a Duration instance */
	public function getTimeout()
	{
		if( !isset($this->timeout) )
			$this->timeout = \LWS\WOOREWARDS\Conveniencies\Duration::void();
		return $this->timeout;
	}

	/** @param $days (false|int|Duration) */
	public function setTimeout($days=false)
	{
		if( empty($days) )
			$this->timeout = \LWS\WOOREWARDS\Conveniencies\Duration::void();
		else if( is_a($days, '\LWS\WOOREWARDS\Conveniencies\Duration') )
			$this->timeout = $days;
		else
			$this->timeout = \LWS\WOOREWARDS\Conveniencies\Duration::fromString($days);
		return $this;
	}

	public function getDisplayType()
	{
		return _x("Fixed/Percentage discount", "getDisplayType", LWS_WOOREWARDS_DOMAIN);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		return $this->getCouponDescription($context);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getCouponDescription($context='backend', $date=false)
	{
		$value = '';
		if( $this->getInPercent() )
			$value = trim(trim(\number_format_i18n($this->getValue(), 2), '0'), '.,') . '%';
		else
			$value = (\LWS_WooRewards::isWC() && $context != 'edit') ? \wc_price($this->getValue()) : \number_format_i18n($this->getValue(), 2);

		$str = sprintf(
			__("%s discount on an order", LWS_WOOREWARDS_DOMAIN),
			$value
		);

		if( !$this->getTimeout()->isNull() )
		{
			$str .= ' - ';
			if( $date )
			{
				$str .= sprintf(
					__('valid up to %s', LWS_WOOREWARDS_DOMAIN),
					\date_i18n(\get_option('date_format'), $this->getTimeout()->getEndingDate($date)->getTimestamp())
				);
			}
			else
			{
				$str .= sprintf(
					__('valid for %1$d %2$s', LWS_WOOREWARDS_DOMAIN),
					$this->getTimeout()->getCount(),
					$this->getTimeout()->getPeriodText()
				);
			}
		}
		return $str;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setTimeout(\LWS\WOOREWARDS\Conveniencies\Duration::postMeta($post->ID, 'wre_unlockable_timeout'));
		$this->setValue(\get_post_meta($post->ID, 'wre_unlockable_value', true));
		$this->setInPercent(boolval(\get_post_meta($post->ID, 'wre_unlockable_percent', true)));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_unlockable_value', $this->getValue());
		\update_post_meta($id, 'wre_unlockable_percent', $this->getInPercent() ? 'on' : '');
		$this->getTimeout()->updatePostMeta($id, 'wre_unlockable_timeout');
		return $this;
	}

	public function getCost($context='edit')
	{
		if( empty($this->getValue()) && ($context == 'view' || $context == 'front') )
			return _x("No discount", "Cannot be bought cause no discount", LWS_WOOREWARDS_DOMAIN);
		else
			return parent::getCost($context);
	}

	public function isPurchasable($points=PHP_INT_MAX, $userId=null)
	{
		if( empty($this->getValue()) )
			return false;
		else
			return parent::isPurchasable($points, $userId);
	}

	public function createReward(\WP_User $user, $demo=false)
	{
		if( !\LWS_WooRewards::isWC() )
			return false;

		if( !\is_email($user->user_email) )
		{
			error_log(\get_class()."::apply - invalid email for user {$user->ID}");
			return false;
		}

		if( $demo )
			$code = strtoupper(__('TESTCODE', LWS_WOOREWARDS_DOMAIN));
		else if( empty($code = apply_filters('lws_woorewards_new_coupon_label', '', $user, $this)) )
			$code = $this->uniqueCode($user, max(8, intval(\get_option('lws_woorewards_coupon_code_length', 10))));

		if( false === ($coupon = $this->createShopCoupon($code, $user, $demo)) )
			return false;

		$this->lastCode = $code;
		return $coupon;
	}

	/** For point movement historic purpose. Can be override to return a reason.
	 *	Last generated coupon code is consumed by this function. */
	public function getReason($context='backend')
	{
		if( isset($this->lastCode) ){
			$reason = sprintf(__("Coupon code : %s", LWS_WOOREWARDS_DOMAIN), $this->lastCode);
			if( $context == 'frontend' )
				$reason .= '<br/>' . $this->getDescription($context);
//			unset($this->lastCode);
			return $reason;
		}
		return $this->getDescription($context);
	}

	/** @return a saved WC_Coupon instance */
	protected function createShopCoupon($code, \WP_User $user, $demo=false)
	{
		if( !$demo )
			\do_action('wpml_switch_language_for_email', $user->user_email); // switch to customer language before fixing content

		$coupon = $this->buildCouponPostData($code, $user);

		if( !$demo )
		{
			$coupon->save();
			if( empty($coupon->get_id()) )
			{
				\do_action('wpml_restore_language_from_email');
				error_log("Cannot generate a shop_coupon: WC error");
				error_log(print_r($coupon, true));
				return false;
			}

			\wp_update_post(array(
				'ID' => $coupon->get_id(),
				'post_author'  => $user->ID,
				'post_content' => $this->getTitle()
			));
			\update_post_meta($coupon->get_id(), 'reward_origin', $this->getType());
			\update_post_meta($coupon->get_id(), 'reward_origin_id', $this->getId());

			\do_action('wpml_restore_language_from_email');
			\do_action('woocommerce_coupon_options_save', $coupon->get_id(), $coupon);
		}
		return $coupon;
	}

	/** @return WC_Coupon instance
	 * @see WC_Meta_Box_Coupon_Data::save */
	protected function buildCouponPostData($code, \WP_User $user)
	{
		$txt = $this->getCustomExcerpt($user);
		$coupon = new \WC_Coupon();
		$coupon->set_props(array(
			'code'                 => $code,
			'description'          => $txt,
			'discount_type'        => $this->getInPercent() ? 'percent' : 'fixed_cart',
			'amount'               => $this->getValue(),
			'date_expires'         => !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate()->format('Y-m-d') : '',
			'usage_limit'          => 1,
			'usage_limit_per_user' => 1,
			'email_restrictions'   => array($user->user_email),
		));
		return $coupon;
	}

	protected function getCustomExcerpt($user)
	{
		$txt = $this->getCustomDescription(false);
		if( empty($txt) )
			$txt = $this->getCouponDescription('frontend', \date_create());
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
		return \wc_format_coupon_code($code);
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

	/**	WooCommerce redirection.
	 *	Allow filtering by customer_email directly in shop_coupon admin list
	 *	and push a link to them inside userspoints editlist. */
	static function addUiFilters()
	{
		if( \is_admin() )
		{
			\add_filter('query_vars', array(\get_class(), 'addQueryVars'));
			\add_action('restrict_manage_posts', array(\get_class(), 'showQueryParsing'), 10, 1);
			\add_action('parse_query', array(\get_class(), 'parseQuery'));
			\add_filter('lws_woorewards_ui_userspoints_rewards_cell', array(\get_class(), 'seeUserCoupons'), 10, 2);
		}
	}

	/** @see parseQuery */
	static function addQueryVars($vars)
	{
		$vars[] = 'customer_email';
		return $vars;
	}

	/** Show coupon custom filter wher applied. */
	static function showQueryParsing($postType)
	{
		if( $postType == 'shop_coupon' && isset($_REQUEST['customer_email']) && !empty($email = \sanitize_email($_REQUEST['customer_email'])) )
		{
			echo "<label for='customer_email' class='lws-wr-coupon-filter-customer_email'>" . __("Customer Email", LWS_WOOREWARDS_DOMAIN) . "</label>";
			echo "<input  id='customer_email' class='lws-wr-coupon-filter-customer_email' type='email' name='customer_email' value='" . \esc_attr($email) . "' aria-describedby='customer email'>";
		}
	}

	/** Allow filtering by customer_email directly in shop_coupon admin list */
	static function parseQuery(&$query)
	{
		if( \is_admin() && $query->query && isset($query->query['post_type']) && $query->query['post_type'] == 'shop_coupon' )
		{
			$email = isset($query->query['customer_email']) ? trim($query->query['customer_email']) : '';
			if( !empty($email) )
			{
				$customer_email = serialize(array($email));
				$query->query_vars['meta_query'][] = array(
					'key' => 'customer_email',
					'value' => $customer_email
				);
			}
		}
	}

	/** Link to 'shop_coupon' posts list, filtered by 'Allowed email'.
	 * Works for generated coupon when 'Allowed email' array is a single email. */
	static function seeUserCoupons($content, $user)
	{
		if( !empty($user) )
		{
			global $wpdb;
			$sql = "SELECT COUNT(ID) FROM {$wpdb->posts} as p INNER JOIN {$wpdb->postmeta} as c ON p.ID=c.post_id AND c.meta_key='customer_email' AND c.meta_value=%s WHERE p.post_type='shop_coupon' AND post_status='publish'";
			$c = $wpdb->get_var($wpdb->prepare($sql, serialize(array($user['user_email']))));
			if( empty($c) )
			{
				static $disp = false;
				if( $disp === false )
					$disp = __("No coupon", LWS_WOOREWARDS_DOMAIN);
				$content[] = "<div class='lws_wre_rewards_no_link'>$disp</div>";
			}
			else
			{
				$url = \esc_attr(\add_query_arg(array('post_type'=>'shop_coupon', 'customer_email'=>$user['user_email']), \admin_url('edit.php')));
				if( !empty($url) )
				{
					static $link = false;
					if( $link === false )
						$link = __("See coupons", LWS_WOOREWARDS_DOMAIN);
					$content[] = sprintf("<a class='lws_wre_rewards_link' href='$url' target='_blank'>$link (%d)</a>", $c);
				}
			}
		}
		return $content;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", LWS_WOOREWARDS_DOMAIN),
			'shop_coupon' => __("Coupon", LWS_WOOREWARDS_DOMAIN),
			'sponsorship' => _x("Sponsored", "unlockable category", LWS_WOOREWARDS_DOMAIN)
		));
	}
}

?>