<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** A free shipping reward.
 * Create a WooCommerce Coupon. */
class FreeShipping extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	use \LWS\WOOREWARDS\PRO\Unlockables\T_DiscountOptions;

	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'permanent'] = ($this->isPermanent() ? 'on' : '');
		$data[$prefix.'timeout'] = $this->getTimeout()->toString();
		return $this->filterData($data, $prefix, $min);
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Coupon options", LWS_WOOREWARDS_PRO_DOMAIN), 'col40');

		// timeout
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/durationfield.php';
		$label = _x("Validity period", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = $this->getTimeout()->toString();
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}timeout' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'>";
		$form .= \LWS\WOOREWARDS\Ui\DurationField::compose($prefix.'timeout', array('value'=>$value));
		$form .= "</div></td></tr>";

		if( $this->getPoolType() == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING )
		{
			// permanent on/off
			$label = _x("Permanent", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
			$checked = ($this->isPermanent() ? ' checked' : '');
			$form .= "<tr><td class='lcell' nowrap>";
			$form .= "<label for='{$prefix}permanent' class='lws-$context-opt-title'>$label</label></td>";
			$form .= "<td class='rcell'>";
			$form .= "<div class='lws-$context-opt-input'><input type='checkbox'$checked id='{$prefix}permanent' name='{$prefix}permanent' class='lws_checkbox'/>";
			$form .= \lws_get_tooltips_html(__("Applied on all future orders.", LWS_WOOREWARDS_PRO_DOMAIN));
			$form .= "</div></td></tr>";
		}

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
				$prefix.'permanent' => 's',
				$prefix.'timeout' => '/(p?\d+[DYM])?/i'
			),
			'defaults' => array(
				$prefix.'permanent' => '',
				$prefix.'timeout' => ''
			),
			'labels'   => array(
				$prefix.'permanent'   => __("Permanent", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'timeout' => __("Validity period", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true )
		{
			$this->setPermanent($values['values'][$prefix.'permanent']);
			$this->setTimeout  ($values['values'][$prefix.'timeout']);
		}
		return $valid;
	}

	public function setTestValues()
	{
		global $wpdb;
		$this->setTimeout(rand(5, 78).'D');
		return $this;
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
		return _x("Free shipping", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
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
		$str = _x("Free shipping", "public description", LWS_WOOREWARDS_PRO_DOMAIN);

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

		if( $this->isPermanent() )
		{
			$attr = _x("permanent", "Coupon", LWS_WOOREWARDS_PRO_DOMAIN);
			$str .= $context=='edit' ? " ($attr)" : " (<i>$attr</i>)";
		}

		if( !empty($discount = $this->getPartialDescription($context)) )
			$str .= (', ' . $discount);
		return $str;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setPermanent(\get_post_meta($post->ID, 'woorewards_permanent', true));
		$this->setTimeout(\LWS\WOOREWARDS\Conveniencies\Duration::postMeta($post->ID, 'wre_unlockable_timeout'));
		$this->optFromPost($post);
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_permanent', $this->isPermanent() ? 'on' : '');
		$this->getTimeout()->updatePostMeta($id, 'wre_unlockable_timeout');
		$this->optSave($id);
		return $this;
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
			if( $this->isPermanent() )
				$this->setPermanentcoupon($coupon, $user, $this->getType());

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
			'amount'                 => 0,
			'date_expires'           => !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate()->format('Y-m-d') : '',
			'usage_limit'            => $this->isPermanent() ? 0 : 1,
			'usage_limit_per_user'   => $this->isPermanent() ? 0 : 1,
			'email_restrictions'     => array($user->user_email),
			'free_shipping'          => true,
		));
		return $this->filterCouponPostData($coupon, $code, $user);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", LWS_WOOREWARDS_PRO_DOMAIN),
			'shop_coupon' => __("Coupon", LWS_WOOREWARDS_PRO_DOMAIN),
			'sponsorship' => _x("Sponsored", "unlockable category", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}
}

?>