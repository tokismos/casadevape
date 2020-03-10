<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** A shop_coupon using all available point to compute the amount at redeem time.
 *
 * Create a WooCommerce Coupon. */
class VariableDiscount extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	use \LWS\WOOREWARDS\PRO\Unlockables\T_DiscountOptions;

	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'value'] = $this->getValue();
		$data[$prefix.'autoapply'] = ($this->isAutoApply() ? 'on' : '');
		$data[$prefix.'timeout'] = $this->getTimeout()->toString();
		return $this->filterData($data, $prefix, $min);
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		// change meaning of basic cost
		$pattern = "/(<label[^>]*for='{$prefix}cost'[^>]*>)(.*)(<\\/label>)/iU";
		$form = preg_replace_callback($pattern, function($match){
			return $match[1] . _x("Mininum points", "Unlockable cost", LWS_WOOREWARDS_PRO_DOMAIN) . $match[3];
		}, $form);

		$form .= $this->getFieldsetBegin(2, __("Coupon options", LWS_WOOREWARDS_PRO_DOMAIN), 'col40');

		// value
		$label = _x("Amount per Point", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$currency = \LWS_WooRewards::isWC() ? \get_woocommerce_currency_symbol() : '$';
		$points = \LWS_WooRewards::getPointSymbol(1, $this->getPoolName());
		$value = empty($this->getValue()) ? '' : \esc_attr($this->getValue());
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}value' class='lws-$context-opt-title'>$label ($currency/$points)</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input type='text' id='{$prefix}value' name='{$prefix}value' value='$value' placeholder='0.01' pattern='\\d*(\\.|,)?\\d*' /></div>";
		$form .= "</td></tr>";

		// autoapply on/off
		$label = _x("Auto-apply on next cart", "VariableDiscount Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$checked = ($this->isAutoApply() ? ' checked' : '');
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}autoapply' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input type='checkbox'$checked id='{$prefix}autoapply' name='{$prefix}autoapply' class='lws_checkbox'/>";
		$form .= "</div></td></tr>";

		// timeout
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/durationfield.php';
		$label = _x("Validity period", "VariableDiscount Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = $this->getTimeout()->toString();
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}timeout' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'>";
		$form .= \LWS\WOOREWARDS\Ui\DurationField::compose($prefix.'timeout', array('value'=>$value));
		$form .= "</div></td></tr>";

		$form .= $this->getFieldsetEnd(2);
		return $this->filterForm($form, $prefix, $context);
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'autoapply' => 's',
				$prefix.'value'   => 'F',
				$prefix.'timeout' => '/(p?\d+[DYM])?/i'
			),
			'defaults' => array(
				$prefix.'autoapply' => '',
				$prefix.'timeout' => ''
			),
			'labels'   => array(
				$prefix.'autoapply'   => __("Auto-apply", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'value'   => __("Coupon amount", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'timeout' => __("Validity period", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true )
		{
			$this->setValue    ($values['values'][$prefix.'value']);
			$this->setAutoApply($values['values'][$prefix.'autoapply']);
			$this->setTimeout  ($values['values'][$prefix.'timeout']);
		}
		return $valid;
	}

	public function getValue()
	{
		return isset($this->value) ? $this->value : 0.01;
	}

	public function setValue($value=0.0)
	{
		$this->value = floatval(str_replace(',', '.', $value));
		return $this;
	}

	public function setTestValues()
	{
		$this->setValue(rand(1, 200)/100.0);
		$this->setTimeout(rand(5, 78).'D');
		return $this;
	}

	public function isAutoApply()
	{
		return isset($this->autoapply) ? $this->autoapply : false;
	}

	public function setAutoApply($yes=false)
	{
		$this->autoapply = boolval($yes);
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
		return _x("Variable discount", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
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
		$amount = isset($this->lastAmount) ? $this->lastAmount : $this->getCouponAmount(\get_current_user_id());
		$value = (\LWS_WooRewards::isWC() && $context != 'edit') ? \wc_price($amount) : \number_format_i18n($amount, 2);
		$txt = $this->isAutoApply() ? __("%s discount on your next order", LWS_WOOREWARDS_PRO_DOMAIN) : __("%s discount on an order", LWS_WOOREWARDS_PRO_DOMAIN);
		$str = sprintf($txt, $value);

		if( !$this->getTimeout()->isNull() )
		{
			$str .= ' - ';
			if( $date )
			{
				$str .= sprintf(
					__('valid up to %s', LWS_WOOREWARDS_PRO_DOMAIN),
					\date_i18n(\get_option('date_format'), $this->getTimeout()->getEndingDate($date)->getTimestamp())
				);
			}
			else
			{
				$str .= sprintf(
					__('valid for %1$d %2$s', LWS_WOOREWARDS_PRO_DOMAIN),
					$this->getTimeout()->getCount(),
					$this->getTimeout()->getPeriodText()
				);
			}
		}

		if( !empty($discount = $this->getPartialDescription($context)) )
			$str .= (', ' . $discount);
		return $str;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setValue(\get_post_meta($post->ID, 'wre_unlockable_value', true));
		$this->setAutoApply(\get_post_meta($post->ID, 'woorewards_autoapply', true));
		$this->setTimeout(\LWS\WOOREWARDS\Conveniencies\Duration::postMeta($post->ID, 'wre_unlockable_timeout'));
		$this->optFromPost($post);
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_unlockable_value', $this->getValue());
		\update_post_meta($id, 'woorewards_autoapply', $this->isAutoApply() ? 'on' : '');
		$this->getTimeout()->updatePostMeta($id, 'wre_unlockable_timeout');
		$this->optSave($id);
		return $this;
	}

	/** Multiplier is registered by Pool, it is applied to the points generated by the event. */
	public function getCost($context='edit')
	{
		$cost = parent::getCost($context);
		if( $context == 'front' || $context == 'pay' )
		{
			if( is_numeric($cost) && $this->getPool() && !empty($userId = \get_current_user_id()) )
				$cost = max($cost, $this->getPool()->getPoints($userId));
		}

		if( $context == 'view' || $context == 'front' )
			$cost .= "<sup>+</sup>";
		return $cost;
	}

	public function getUserCost($userId)
	{
		$cost = parent::getCost('pay');
		if( is_numeric($cost) && $this->getPool() && !empty($userId) )
			$cost = max($cost, $this->getPool()->getPoints($userId));
		return intval($cost);
	}

	public function getCouponAmount($userId)
	{
		return $this->getValue() * $this->getUserCost($userId);
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
			$code = strtoupper(__('TESTCODE', LWS_WOOREWARDS_PRO_DOMAIN));
		else if( empty($code = apply_filters('lws_woorewards_new_coupon_label', '', $user, $this)) )
			$code = $this->uniqueCode($user, max(8, intval(\get_option('lws_woorewards_coupon_code_length', 10))));

		if( false === ($coupon = $this->createShopCoupon($code, $user, $demo)) )
			return false;

		$this->lastAmount = $coupon->get_amount();
		$this->lastCode = $code;
		return $coupon;
	}

	/** For point movement historic purpose. Can be override to return a reason.
	 *	Last generated coupon code is consumed by this function. */
	public function getReason($context='backend')
	{
		if( isset($this->lastCode) ){
			$reason = sprintf(__("Coupon code : %s", LWS_WOOREWARDS_PRO_DOMAIN), $this->lastCode);
			if( $context == 'frontend' )
				$reason .= '<br/>' . $this->getDescription($context);
			return $reason;
		}
		return $this->getDescription($context);
	}

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
			if( $this->isAutoApply() )
				\update_post_meta($coupon->get_id(), 'woorewards_permanent', 'on');

			\do_action('wpml_restore_language_from_email');
			\do_action('woocommerce_coupon_options_save', $coupon->get_id(), $coupon);
		}
		return $coupon;
	}

	protected function buildCouponPostData($code, \WP_User $user)
	{
		$txt = $this->getCustomExcerpt($user);

		$coupon = new \WC_Coupon();
		$coupon->set_props(array(
			'code'                   => $code,
			'description'            => $txt,
			'discount_type'          => 'fixed_cart',
			'amount'                 => $this->getCouponAmount($user->ID),
			'date_expires'           => !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate()->format('Y-m-d') : '',
			'usage_limit'            => 1,
			'usage_limit_per_user'   => 1,
			'email_restrictions'     => array($user->user_email)
		));
		return $this->filterCouponPostData($coupon, $code, $user);
	}

	protected function getCustomExcerpt($user)
	{
		$txt = $this->getCouponExcerpt();
		if( !empty($txt) )
		{
			$expiry = !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate() : false;
			$txt = $this->expiryInText($txt, $expiry);
			$amount = $this->getCouponAmount($user->ID);
			$value = \LWS_WooRewards::isWC() ? \wc_price($amount) : \number_format_i18n($amount, 2);
			$txt = str_replace('[amount]', $value, $txt);
		}
		else
		{
			$txt = $this->getCustomDescription(false);
			if( empty($txt) )
				$txt = $this->getCouponDescription('frontend', \date_create());
		}
		return $txt;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_STANDARD  => __("Standard", LWS_WOOREWARDS_PRO_DOMAIN),
			'woocommerce' => __("WooCommerce", LWS_WOOREWARDS_PRO_DOMAIN),
			'shop_coupon' => __("Coupon", LWS_WOOREWARDS_PRO_DOMAIN)
		);
	}
}

?>