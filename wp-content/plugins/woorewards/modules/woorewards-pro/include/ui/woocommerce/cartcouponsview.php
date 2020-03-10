<?php
namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Compute a earning point estimation and insert it in the cart page. */
class CartCouponsView
{
	const POS_ASIDE  = 'cart_collaterals';
	const POS_INSIDE = 'middle_of_cart';
	const POS_AFTER  = 'on'; // bottom_of_cart
	const POS_NONE   = 'not_displayed';

	static function register()
	{
		\add_filter('lws_adminpanel_stygen_content_get_'.'cartcouponsview', array(new self(null, self::POS_NONE), 'template'));
		\add_shortcode('wr_cart_coupons_view', array(new self(null, self::POS_NONE), 'shortcode'));

		// make an instance for each selected pool
		if( !empty($position = \get_option('lws_woorewards_cart_collaterals_coupons')) && $position != self::POS_NONE )
		{
			new self($position);
		}
	}

	function __construct($position)
	{
		$this->position = $position;

		if( !empty($hook = $this->getHook($position)) )
			\add_action($hook, array($this, 'display'));
	}

	protected function getHook($position)
	{
		if( $position == self::POS_ASIDE )
			return 'woocommerce_cart_collaterals';
		else if( $position == self::POS_INSIDE )
			return 'woocommerce_after_cart_table';
		else if( $position == self::POS_AFTER )
			return 'woocommerce_after_cart';
		else
			return false;
	}

	function template($snippet)
	{
		$this->stygen = true;
		$coupons = array(
			(object)['post_title' => 'CODETEST1', 'post_excerpt' => _x("A fake coupon", "stygen", LWS_WOOREWARDS_PRO_DOMAIN)],
			(object)['post_title' => 'CODETEST2', 'post_excerpt' => _x("Another fake coupon", "stygen", LWS_WOOREWARDS_PRO_DOMAIN)]
		);
		$snippet = "<div class='lwss_selectable lws-wre-cartcouponsview-main woocommerce' data-type='Main Div'>";
		$snippet .= $this->getHead();
		$snippet .= $this->getContent($coupons, false, true);
		$snippet .= "</div>";
		unset($this->stygen);
		return $snippet;
	}

	function shortcode()
	{
		if( empty($userId = \get_current_user_id()) )
			return;
		if( empty($coupons = $this->getCoupons($userId)) )
			return;
		if( !empty($coupons) )
		{
			\wp_enqueue_style('lws_wre_cart_coupons_view', LWS_WOOREWARDS_PRO_CSS.'/templates/cartcouponsview.css?stygen=lws_wre_cart_coupons_view', array(), LWS_WOOREWARDS_PRO_VERSION);
			$wcStyle = $this->position == self::POS_ASIDE ? " style='float:left;max-width:48%;'" : '';
			$html = "<div class='lwss_selectable lws-wre-cartcouponsview-main woocommerce'$wcStyle data-type='Main Div'>";
			$html .= $this->getHead();
			$html .= $this->getcontent($coupons, 'lws_woorewards_coupons');
			$html .= "</div>";
			return $html;
		}
	}

	function display()
	{
		echo($this->shortcode());
	}

	public function getHead($id=false)
	{
		$id = empty($id) ? '' : " id='$id'";
		$stygenId = 'lws_woorewards_title_cart_coupons_view';
		$title = \lws_get_option($stygenId, _x("Available Coupons", "Table content", LWS_WOOREWARDS_PRO_DOMAIN));
		if( !isset($this->stygen) )
			$title = \apply_filters('wpml_translate_single_string', $title, 'Widgets', "WooRewards - Coupons - Title");
		$str = "<h2 class='lwss_selectable lwss_modify lws-wr-shop-coupon-title'$id data-id='{$stygenId}' data-type='Title'>";
		$str .= "<span class='lwss_modify_content'>{$title}</span>";
		$str .= "</h2>";
		return $str;
	}

	public function getCoupons($userId)
	{
		$dataGet = new \LWS\WOOREWARDS\PRO\VariousData();
		return $dataGet->getCoupons($userId);
	}

	/** @param $coupons (array) a coupon list.
	 *	@param $tableId (slug) DOM element id */
	public function getContent($coupons = array(), $tableId=false, $demo=false)
	{
		$btnText = \lws_get_option('lws_woorewards_cart_coupons_button', __("Use Coupon", LWS_WOOREWARDS_PRO_DOMAIN));
		if( !isset($this->stygen) )
			$btnText = \apply_filters('wpml_translate_single_string', $btnText, 'Widgets', "WooRewards - Coupons - Button");

		$reloadNonce = false;
		if( !$demo && !empty(\get_option('lws_woorewards_apply_coupon_by_reload', '')) )
		{
			$nonce = \esc_attr(urlencode(\wp_create_nonce('wr_apply_coupon')));
			$reloadNonce = " data-reload='wrac_n={$nonce}&wrac_c=%s'";
		}

		$done = (\LWS_WooRewards::isWC() && !empty($tableId)) ? array_keys(\WC()->cart->get_coupons()) : array();
		$content = '';
		foreach( $coupons as $coupon )
		{
			$code = \esc_attr($coupon->post_title);
			$css = $demo ? '' : (' coupon-'.strtolower($code));
			$hidden = in_array(strtolower($coupon->post_title), $done) ? " style='display:none;'" : '';
			$content .= "<tr class='lwss_selectable lws_wr_cart_coupon_row$css' data-type='Row'$hidden>";
			$content .= "<td class='lwss_selectable lws-wr-cart-coupon-code' data-type='Coupon code column'>{$coupon->post_title}</td>";
			$descr = \apply_filters('lws_woorewards_coupon_content', $coupon->post_excerpt, $coupon);
			$content .= "<td class='lwss_selectable lws-wr-cart-coupon-description' data-type='Description column'>{$descr}</td>";
			if( $demo || !empty($tableId) ){
				$content .= "<td class='lwss_selectable lws-wr-cart-coupon-button' data-type='Button column'>";
				$attr = '';
				if( $reloadNonce )
					$attr = sprintf($reloadNonce, \esc_attr(urlencode($coupon->post_title)));
				$content .=	"<div class='lwss_selectable lwss_modify lws-cart-button lws_woorewards_add_coupon' data-id='lws_woorewards_cart_coupons_button' data-coupon='$code'{$attr} data-type='Add to cart'>";
				$content .= "<span class='lwss_modify_content'>{$btnText}</span></div></td>";
			}
			$content .= "</tr>";
		}

		if( !empty($content) )
		{
			$id = empty($tableId) ? '' : " id='$tableId'";
			$content = "<table class='shop_table shop_table_responsive'$id>{$content}</table>";
		}
		return $content;
	}

}
