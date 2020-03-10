<?php
namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Compute a earning point estimation and insert it in the cart page. */
class CartPointsPreview
{
	const POS_ASIDE  = 'cart_collaterals';
	const POS_INSIDE = 'middle_of_cart';
	const POS_AFTER  = 'bottom_of_cart';
	const POS_NONE   = 'not_displayed';

	static function register()
	{
		\add_filter('lws_adminpanel_stygen_content_get_'.'cartpointspreview', array(new self(null, self::POS_NONE), 'template'));

		// make an instance for each selected pool
		if( !empty($position = \get_option('lws_woorewards_cart_potential_position')) && $position != self::POS_NONE )
		{
			if( !empty($poolIds = \get_option('lws_woorewards_cart_potential_pool')) )
			{
				new self($poolIds, $position);
			}
		}
	}

	function __construct($poolIds, $position)
	{
		if(!empty($poolIds)){
			$this->pools = array();
			foreach( \LWS_WooRewards_Pro::getActivePools()->asArray() as $pool )
			{
				if( in_array($pool->getId(), $poolIds) )
				{
					$this->pools[] = $pool;
				}
			}
			$this->position = $position;

			if( !empty($hook = $this->getHook($position)) )
			{
				\add_action($hook, array($this, 'display'));
			}
		}
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

	function template($snippet){
		$this->stygen = true;
		$items = array(
			array('system' => __("Standard System", LWS_WOOREWARDS_PRO_DOMAIN), 'points' => rand(5,200).' '.__("Points", LWS_WOOREWARDS_PRO_DOMAIN)),
			array('system' => __("Levelling System", LWS_WOOREWARDS_PRO_DOMAIN), 'points' => rand(10, 500).' '.__("Points", LWS_WOOREWARDS_PRO_DOMAIN))
		);
		$this->position = '';
		$snippet = $this->getContent($items);
		unset($this->stygen);
		return $snippet;
	}

	function display()
	{
		$cart = \WC()->cart;
		$items = array();
		foreach($this->pools as $pool)
		{
			$system = $pool->getOption('display_title');
			$sum = 0;
			foreach( $pool->getEvents()->asArray() as $event )
			{
				if( \is_a($event, 'LWS\WOOREWARDS\PRO\Events\I_CartPreview') && 0 < ($points = $event->getPointsForCart($cart)) )
					$sum += $points;
			}
			if( $sum > 0 )
				$items[] = array('system' => $system, 'points' => \LWS_WooRewards::formatPointsWithSymbol($sum, $pool->getName()));
		}

		if( !empty($items) )
		{
			\wp_enqueue_style('lws_wre_cart_points_preview', LWS_WOOREWARDS_PRO_CSS.'/templates/cartpointspreview.css?stygen=lws_wre_cart_points_preview', array(), LWS_WOOREWARDS_PRO_VERSION);
			echo $this->getContent($items);
		}
	}

	/** @param $items an array of array('system' => /string/, 'points' => /int/) */
	protected function getContent($items=array())
	{
		$wcStyle = $this->position == self::POS_ASIDE ? " style='float:left;'" : '';
		$title = \lws_get_option('lws_woorewards_title_cpp', __("Loyalty points you will earn", LWS_WOOREWARDS_PRO_DOMAIN));
		if( !isset($this->stygen) )
			$title = \apply_filters('wpml_translate_single_string', $title, 'Widgets', "WooRewards - Coupons - Title");

		$html = <<<EOT
<div class='lwss_selectable lws-wre-cartpointspreview-main woocommerce'$wcStyle data-type='Main Div'>
	<table class='shop_table shop_table_responsive'>
		<thead>
			<tr>
				<th colspan='2'>
					<div class='lwss_selectable lwss_modify lws-wre-cartpointspreview-title' data-type='Main title' data-id='lws_woorewards_title_cpp'>
						<span class='lwss_modify_content'>{$title}</span>
					</div>
				</th>
			</tr>
		</thead>
		<tbody>

EOT;

		foreach($items as $item){
			$html .= "<tr><td class='lwss_selectable lws-wre-cartpointspreview-label' data-type='System title'>{$item['system']}</td>";
			$html .= "<td class='lwss_selectable lws-wre-cartpointspreview-points' data-type='System title'>{$item['points']}</td></tr>";
		}

		$html .= <<<EOT
		</tbody>
	</table>
</div>
EOT;

		return $html;
	}
}
