<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget for customer to input email for sponsorship.
 * Can be used as a Widget, a Shortcode [lws_sponsorship] or a Guttenberg block. */
class CouponsWidget extends \LWS\WOOREWARDS\PRO\Ui\Widgets\Widget
{
	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('wr_shop_coupons', array($me, 'shortcode'));
		\add_filter('lws_adminpanel_stygen_content_get_'.'wc_shop_coupon', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array( $me , 'scripts' ), 100); // priority: hope to be after theme enqueue without using dependency

		\add_filter('lws_woorewards_coupon_content', array($me, 'getCouponContent'), 10, 2);
	}

	public function scripts()
	{
		\wp_enqueue_script('woorewards-wc-coupons', LWS_WOOREWARDS_PRO_JS . '/coupons.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION, true);
		\wp_enqueue_style('woorewards-wc-coupons', LWS_WOOREWARDS_PRO_CSS.'/templates/coupons.css?stygen=lws_woorewards_wc_coupons_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_wc_coupons',
				__("WooRewards Coupons", LWS_WOOREWARDS_PRO_DOMAIN),
				array(
					'description' => __("Display WooCommerce Coupons owned by the customer.", LWS_WOOREWARDS_PRO_DOMAIN)
				)
			);
		}
	}

	/** ensure all required fields exist. */
	function update( $new_instance, $old_instance )
	{
		return \wp_parse_args(
			array_merge($old_instance, $new_instance),
			$this->defaultArgs()
		);
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());

		// title
		$this->eFormFieldText(
			$this->get_field_id('title'),
			__("Title", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr(_x("Available Coupons", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
		);
		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Available Coupons", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
		);
	}

	protected function defaultArgs()
	{
		return array(
			'title'  => '',
			'header'  => ''
		);
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		$begin = "<div class='lws-wr-shop-coupon-cont'>";
		$end = "</div>";
		$emptyContent = \get_option('lws_wooreward_wc_coupons_empty', '');
		$emptyContent = empty($emptyContent) ? __("No coupon.", LWS_WOOREWARDS_PRO_DOMAIN) : $emptyContent;
		if( !empty($userId = \get_current_user_id()) )
		{
			echo $args['before_widget'];
			echo $args['before_title'];
			echo \apply_filters('widget_title', empty($instance['title']) ? _x("Available Coupons", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN) : $instance['title'], $instance);
			echo $args['after_title'];
			echo !empty($coupons = $this->getCoupons($userId)) ? $begin . $this->getHead($instance) . $this->getContent($coupons) . $end : $emptyContent;
			echo $args['after_widget'];
		}
	}

	function template($snippet)
	{
		$this->stygen = true;
		$coupons = array(
			(object)['post_title' => 'CODETEST1', 'post_excerpt' => _x("A fake coupon", "stygen", LWS_WOOREWARDS_PRO_DOMAIN)],
			(object)['post_title' => 'CODETEST2', 'post_excerpt' => _x("Another fake coupon", "stygen", LWS_WOOREWARDS_PRO_DOMAIN).' - '._x("valid for 7 days", "stygen", LWS_WOOREWARDS_PRO_DOMAIN)]
		);
		$begin = "<div class='lws-wr-shop-coupon-cont'>";
		$end = "</div>";
		$content = ($begin . $this->getHead($this->defaultArgs()) . $this->getContent($coupons, false, true) . $end);
		unset($this->stygen);
		return $content;
	}



	/** @brief shortcode [lws_sponsorship]
	 *	Display input box to set sponsored email, then a div for server answer. */
	public function shortcode($atts=array(), $content='')
	{
		$atts = \wp_parse_args($atts, $this->defaultArgs());
		$begin = "<div class='lws-wr-shop-coupon-cont'>";
		$end = "</div>";
		if( empty($userId = \get_current_user_id()) )
			return $begin . \lws_get_option('lws_wooreward_wc_coupons_nouser',__("Please log in to see the coupons you have", LWS_WOOREWARDS_PRO_DOMAIN)) .$end;
		if( empty($coupons = $this->getCoupons($userId)) )
			return $content;
		return $begin . $this->getHead($atts) . $this->getContent($coupons) . $end;
	}

	/** @param $coupons (array) a coupon list.
	 *	@param $tableId (slug) DOM element id */
	public function getContent($coupons = array(), $tableId=false, $demo=false)
	{
		$content = '';
		foreach( $coupons as $coupon )
		{
			$code = \esc_attr($coupon->post_title);
			$content .= "<tr class='lwss_selectable lws-wr-shop-coupon-row' data-type='Row'>";
			$content .= "<td class='lwss_selectable lws-wr-shop-coupon-code' data-type='Coupon'>{$code}</td>";
			$descr = \apply_filters('lws_woorewards_coupon_content', $coupon->post_excerpt, $coupon);
			$content .= "<td class='lwss_selectable lws-wr-shop-coupon-description' data-type='Description'>{$descr}</td>";
			$content .= "</tr>";
		}

		if( !empty($content) )
		{
			$content = "<table class='lwss_selectable lws-wr-shop-coupon-table' data-type='Table'>{$content}</table>";
		}
		return $content;
	}

	public function getHead($atts=array(), $id=false)
	{
		$id = empty($id) ? '' : " id='$id'";
		if( empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woorewards_wc_coupons_template_head', __("Available Coupons", LWS_WOOREWARDS_PRO_DOMAIN));
		if( !isset($this->stygen) )
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Coupons Widget - Header");
		return "<div class='lwss_selectable lwss_modify lws-wr-shop-coupon-head'$id data-id='lws_woorewards_wc_coupons_template_head' data-type='Header'><span class='lwss_modify_content'>{$atts['header']}</span></div>";
	}

	public function getCoupons($userId)
	{
		$dataGet = new \LWS\WOOREWARDS\PRO\VariousData();
		return $dataGet->getCoupons($userId);
	}

	/** Backward compatibility: v2 does not set a proper text
	 * v3 add a meta 'reward_origin' to let us use the post_content.
	 * @brief Rewrite shop_coupon text on-the-fly.
	 * @param $content (string) the coupon content for display.
	 * @param $coupon the origin post. */
	function getCouponContent($content, $coupon)
	{
		$value = false;

		if( !empty($coupon) && isset($coupon->discount_type) && empty(\get_post_meta($coupon->ID, 'reward_origin', true)) )
		{
			if( $coupon->discount_type == 'fixed_cart' )
			{
				$value = \wc_price($coupon->coupon_amount);
				$content = sprintf(__("%s discount", LWS_WOOREWARDS_PRO_DOMAIN), $value);
				if( \wc_prices_include_tax() )
				{
					$content .= (' <i>('. _x("including tax", "fixed cart coupon", LWS_WOOREWARDS_PRO_DOMAIN) . ')</i>');
				}
			}
			else if( $coupon->discount_type == 'percent' )
			{
				$value = trim(trim(\number_format_i18n($coupon->coupon_amount, 2), '0'), '.,') . '%';
				$content = sprintf(__("%s discount", LWS_WOOREWARDS_PRO_DOMAIN), $value);
				if( !empty($coupon->product_ids) )
				{
					if( $coupon->coupon_amount == '100' )
						$content = __("Free product", LWS_WOOREWARDS_PRO_DOMAIN);
					foreach( explode(',', $coupon->product_ids) as $id )
					{
						$link = sprintf("<a target='_blank' href='%s'>%s</a>", \esc_attr(\get_permalink($id)), \get_the_title($id));
						if( $coupon->coupon_amount == '100' )
							$content .= (' ' . $link);
						else
							$content .= sprintf(__(" on %s", LWS_WOOREWARDS_PRO_DOMAIN), $link);
					}
				}
			}
		}

		if( $value !== false && isset($coupon->expiry_date) && !empty($coupon->expiry_date) )
		{
			$content .= '<br/>';
			$content .= sprintf(
				__("Expiry on %s", LWS_WOOREWARDS_PRO_DOMAIN),
				\date_i18n(\get_option('date_format'), $coupon->expiry_date)
			);
		}
		return $content;
	}
}

?>
