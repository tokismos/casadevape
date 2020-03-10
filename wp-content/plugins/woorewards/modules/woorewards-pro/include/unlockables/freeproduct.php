<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** A free product is a usual shop coupon with:
 * * usage restricted to 1 single product.
 * * discount amount is 100%
 *
 * @note should we manage product variation?
 *
 * Create a WooCommerce Coupon. */
class FreeProduct extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	use \LWS\WOOREWARDS\PRO\Unlockables\T_DiscountOptions;

	static function registerFeatures()
	{
		// warn admin for reward about deleted products
		\add_action('before_delete_post', array(\get_class(), 'productDeleted'));
	}

	/** If an offered product is deleted, raise an admin notice. */
  static function productDeleted($postid)
  {
		// does a product deleted
		if( empty($product = \get_post($postid)) || $product->post_type != 'product' )
			return;

		// does it belong to a rewards
		global $wpdb;
		$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(p.ID) FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as m ON p.ID=m.post_id AND m.meta_key='wre_unlockable_product_id' AND m.meta_value=%s
INNER JOIN {$wpdb->postmeta} as t ON p.ID=t.post_id AND t.meta_key='wre_unlockable_type' AND t.meta_value='lws_woorewards_pro_unlockables_freeproduct'
WHERE post_type='lws-wre-unlockable'", $postid));

		if( !empty($count) )
		{
			\lws_admin_add_notice(
				'woorewards-free-product-deleted-'.$postid,
				sprintf(__("The deleted product <b>%s</b> was used by rewards.", LWS_WOOREWARDS_PRO_DOMAIN), \apply_filters('the_title', $product->post_title, $postid)),
				array(
					'dismissible' => true,
					'forgettable' => true,
					'level' => 'warning',
					'once' => false
				)
			);
		}
	}

	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'timeout'] = $this->getTimeout()->toString();
		$data[$prefix.'product_id'] = $this->getProductId();
		return $this->filterData($data, $prefix, $min);
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Coupon options", LWS_WOOREWARDS_PRO_DOMAIN), 'col40');

		// product
		$label = _x("Offered Product", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = $this->getProductId();
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}product_id' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'>";
		$form .= "<input id='{$prefix}product_id' name='{$prefix}product_id' class='lac_select' data-ajax='lws_woorewards_wc_product_list' data-mode='research' value='$value'>";
		$form .= "</div></td></tr>";

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
				$prefix.'timeout' => '/(p?\d+[DYM])?/i',
				$prefix.'product_id' => 'D'
			),
			'defaults' => array(
				$prefix.'timeout' => ''
			),
			'labels'   => array(
				$prefix.'timeout' => __("Validity period", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'product_id'   => __("Product", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true )
		{
			$this->setProductId($values['values'][$prefix.'product_id']);
			$this->setTimeout  ($values['values'][$prefix.'timeout']);
		}
		return $valid;
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

	public function setProductId($id=0)
	{
		$this->productId = \absint($id);
		return $this;
	}

	public function setTestValues()
	{
		global $wpdb;
		// pick a not free product randomly
		$this->setProductId($wpdb->get_var("SELECT ID FROM {$wpdb->posts} INNER JOIN {$wpdb->postmeta} ON ID=post_id AND meta_key='_regular_price' AND meta_value>0 WHERE post_type='product' ORDER BY RAND() LIMIT 0, 1"));
		$this->setTimeout(rand(5, 78).'D');
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
		return _x("Free product", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
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
		$str = sprintf(
			__("%s offered with an order", LWS_WOOREWARDS_PRO_DOMAIN),
			empty($link = $this->getProductLink()) ? ($context=='edit' ? '***' : __("[Unknown product]", LWS_WOOREWARDS_PRO_DOMAIN)) : $link
		);

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

	/** use product image by default if any but can be override by user */
	public function getThumbnailUrl()
	{
		if( empty($this->getThumbnail()) && !empty($product = $this->getProduct()) && !empty($imgId = $product->get_image_id()) )
			return \wp_get_attachment_image_url($imgId);
		else
			return parent::getThumbnailUrl();
	}

	/** use product image by default if any but can be override by user */
	public function getThumbnailImage($size='lws_wr_thumbnail')
	{
		if( empty($this->getThumbnail()) && !empty($product = $this->getProduct()) && !empty($imgId = $product->get_image_id()) )
			return \wp_get_attachment_image($imgId, $size, false, array('class'=>'lws-wr-thumbnail lws-wr-unlockable-thumbnail'));
		else
			return parent::getThumbnailImage($size);
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setTimeout(\LWS\WOOREWARDS\Conveniencies\Duration::postMeta($post->ID, 'wre_unlockable_timeout'));
		$this->setProductId(\get_post_meta($post->ID, 'wre_unlockable_product_id', true));
		$this->optFromPost($post);
		return $this;
	}

	protected function _save($id)
	{
		$this->getTimeout()->updatePostMeta($id, 'wre_unlockable_timeout');
		\update_post_meta($id, 'wre_unlockable_product_id', $this->getProductId());
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

		if( empty($this->getProductId()) )
		{
			error_log(\get_class()."::apply - undefined free product");
			if( !$demo )
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
			else if( !empty($name = $this->getProductName()) )
				$reason .= " ($name)";
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
			\update_post_meta($coupon->get_id(), 'woorewards_freeproduct', 'yes');
			\update_post_meta($coupon->get_id(), 'reward_origin', $this->getType());
			\update_post_meta($coupon->get_id(), 'reward_origin_id', $this->getId());

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
			'discount_type'          => 'percent',
			'amount'                 => 100,
			'date_expires'           => !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate()->format('Y-m-d') : '',
			'usage_limit'            => 1,
			'usage_limit_per_user'   => 1,
			'email_restrictions'     => array($user->user_email),
			'product_ids'            => array($this->getProductId()),
			'limit_usage_to_x_items' => 1
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
			'wc_product'  => __("Product", LWS_WOOREWARDS_PRO_DOMAIN),
			'sponsorship' => _x("Sponsored", "unlockable category", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}
}

?>