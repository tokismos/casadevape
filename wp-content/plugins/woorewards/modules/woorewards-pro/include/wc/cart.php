<?php
namespace LWS\WOOREWARDS\PRO\WC;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** New feature on WC cart
 * * Automatically add permanent coupon to the cart.
 * * Add product associated with a free product coupon. */
class Cart
{
	public function __construct()
	{
		\add_action('woocommerce_before_cart', array($this, 'completeCart'));
		if( !empty(\get_option('lws_woorewards_apply_coupon_by_reload', '')) )
			\add_action('woocommerce_before_cart', array($this, 'applyCoupon'));

		// act just before wc
		$wcAjaxEvents = array('apply_coupon' => array(true, 'addFreeProduct'));
		foreach( $wcAjaxEvents as $ajaxEvent => $options )
		{
			\add_action('wp_ajax_woocommerce_' . $ajaxEvent, array($this, $options[1]), 0); // prior to wc
			if ( $options[0] )
			{
				\add_action('wp_ajax_nopriv_woocommerce_' . $ajaxEvent, array($this, $options[1]), 0); // prior to wc
				// WC AJAX can be used for frontend ajax requests.
				\add_action('wc_ajax_' . $ajaxEvent, array($this, $options[1]), 0); // prior to wc
			}
		}
		\add_filter('woocommerce_cart_totals_coupon_label', array($this, 'getCouponLabel'), 1000, 2);
	}

	/** add indication about the free product if any */
	function getCouponLabel($text, $coupon)
	{
		if( $this->isFreeProductCoupon($coupon) )
		{
			$limit = $coupon->get_limit_usage_to_x_items('edit');
			foreach($coupon->get_product_ids() as $productId )
			{
				$products = \get_posts(array( /// in case a multilanguage plugin is working somewhere
					'fields' => 'ids',
					'p' => $productId,
					'post_type'=>'product',
					'suppress_filters' => false,
					'posts_per_page' => 1
				));
				if( !empty($products) )
					$productId = $products[0];

				$link = sprintf("<a target='_blank' href='%s'>%s</a>", \esc_attr(\get_permalink($productId)), \get_the_title($productId));
				$text .= '<br/>' . sprintf(__("Free %s", LWS_WOOREWARDS_PRO_DOMAIN), $link);
				if( 0 >= --$limit )
					break;
			}
		}
		return $text;
	}

	/** Do before WC actions.
	 * If the coupon is a WooRewards Free product, add that product in cart if not already in. */
	function addFreeProduct()
	{
		if( !isset($_POST['coupon_code']) || empty($_POST['coupon_code']) )
			return;
		if( !\wc_coupons_enabled() )
			return;

		$code = \wc_format_coupon_code(\sanitize_text_field(\wp_unslash($_POST['coupon_code'])));
		$coupon = new \WC_Coupon($code);

		// is one of us? type + product_ids + woorewards_mark
		if( $this->isFreeProductCoupon($coupon) && ($ids = $coupon->get_product_ids()) )
		{
			$ids = array_combine($ids, $ids);
			$prodQty = 0;
			foreach( \WC()->cart->get_cart() as $item )
			{
				if( isset($ids[$item['data']->get_id()]) )
					++$prodQty;
			}

			$limit = $coupon->get_limit_usage_to_x_items('edit');
			while( $limit > $prodQty )
			{
				++$prodQty;
				\WC()->cart->add_to_cart(reset($ids), 1, 0, array(), array('woorewards-freeproduct' => true));
			}
		}
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

	/** if $_REQUEST contains a coupon to add, we apply it on the cart */
	public function applyCoupon()
	{
		if( isset($_REQUEST['wrac_n']) && isset($_REQUEST['wrac_c']) && \wp_verify_nonce($_REQUEST['wrac_n'], 'wr_apply_coupon') )
		{
			if( !empty($code = \sanitize_text_field($_REQUEST['wrac_c'])) )
			{
				$stack = array_keys(\WC()->cart->get_coupons());
				if( !in_array($code, $stack) )
				{
					if( $id = \wc_get_coupon_id_by_code($code) )
					{
						$wcCoupon = new \WC_Coupon($id);
						if( $wcCoupon->is_valid() )
							\WC()->cart->add_discount($code);
						else
							error_log("Try to apply an invalid coupon ({$id}): ".$code);
					}
					else
						error_log("Try to apply an unknown coupon: ".$code);
				}
			}
		}
	}

	/** Automatically add any current user available permanent coupon to the cart */
	public function completeCart()
	{
		$user = \wp_get_current_user();
		if( !empty($user->ID) )
		{
			$stack = array_keys(\WC()->cart->get_coupons());
			foreach($this->loadCoupons($user) as $coupon)
			{
				if( !in_array($coupon->title, $stack) )
				{
					$wcCoupon = new \WC_Coupon($coupon->ID);
					if( $wcCoupon->is_valid() )
						\WC()->cart->add_discount($coupon->title);
				}
			}
		}
	}

	/** return array of post{ID, title} */
	private function loadCoupons($user)
	{
		if( empty($user->user_email) )
			return array();
		$todayDate = strtotime(date('Y-m-d'));

		global $wpdb;
		$sql = <<<EOT
SELECT p.ID as ID, p.post_title as title from {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as mail ON p.ID = mail.post_id AND mail.meta_key='customer_email'
INNER JOIN {$wpdb->postmeta} as w ON p.ID = w.post_id AND w.meta_key='woorewards_permanent' AND w.meta_value<>''
LEFT JOIN {$wpdb->postmeta} as l ON p.ID = l.post_id AND l.meta_key='usage_limit'
LEFT JOIN {$wpdb->postmeta} as u ON p.ID = u.post_id AND u.meta_key='usage_count'
LEFT JOIN {$wpdb->postmeta} as e ON p.ID = e.post_id AND e.meta_key='expiry_date'
WHERE mail.meta_value=%s AND post_type = 'shop_coupon' AND post_status = 'publish'
AND (e.meta_value IS NULL OR e.meta_value = '' OR e.meta_value >= '{$todayDate}')
AND (u.meta_value < l.meta_value OR u.meta_value IS NULL OR l.meta_value IS NULL OR l.meta_value=0)
EOT;
		return $wpdb->get_results($wpdb->prepare($sql, serialize(array($user->user_email))));
	}
}

?>
