<?php
namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Compute a earning point estimation and insert it in the product page. */
class ProductPointsPreview
{
	const POS_BEFORE = 'before_summary';
	const POS_INSIDE = 'inside_summary';
	const POS_AFTER  = 'after_summary';
	const POS_NONE   = 'not_displayed';

	static function register()
	{
		\add_filter('lws_adminpanel_stygen_content_get_'.'productpointspreview', array(new self(null, self::POS_NONE), 'template'));
		\add_shortcode('wr_product_points_preview', array(new self(null, self::POS_NONE), 'shortcode'));

		// make an instance for each selected pool
		if( !empty($position = \get_option('lws_woorewards_product_potential_position')) && $position != self::POS_NONE )
		{
			if( !empty($poolIds = \get_option('lws_woorewards_product_potential_pool')) )
			{
				new self($poolIds, $position);
			}
		}
	}

	function __construct($poolIds, $position)
	{
		$this->poolIds = $poolIds;
		$this->position = $position;

		if( !empty($hook = $this->getHook($position)) )
			\add_action($hook, array($this, 'display'));
	}

	protected function getHook($position)
	{
		if( $position == self::POS_BEFORE )
			return 'woocommerce_before_single_product_summary';
		else if( $position == self::POS_INSIDE )
			return 'woocommerce_single_product_summary';
		else if( $position == self::POS_AFTER )
			return 'woocommerce_after_single_product_summary';
		else
			return false;
	}

	function template($snippet){
		$this->stygen = true;
		$snippet = $this->getContent(
			array(
				array('system'=>'Standard System', 'points'=>'256 '.__('Points', LWS_WOOREWARDS_PRO_DOMAIN)),
				array('system'=>'Levelling System', 'points'=>'128 '.__('Points', LWS_WOOREWARDS_PRO_DOMAIN)),
			)
		);
		unset($this->stygen);
		return $snippet;
	}

	function shortcode()
	{
		global $product;
		if(empty($this->poolIds))
			$this->poolIds = \get_option('lws_woorewards_product_potential_pool');
		if( !empty($product) && \is_a($product, 'WC_Product') )
		{
			$points = $this->getPointsForProduct($product);
			if( !empty($points) )
			{
				\wp_enqueue_style('lws_wre_product_points_preview', LWS_WOOREWARDS_PRO_CSS.'/templates/productpointspreview.css?stygen=lws_wre_product_points_preview', array(), LWS_WOOREWARDS_PRO_VERSION);
				return $this->getContent($points);
			}
		}
		return false;
	}

	function display()
	{
		echo($this->shortcode());
	}

	protected function getPools()
	{
		$this->pools = array();
		foreach( \LWS_WooRewards_Pro::getActivePools()->asArray() as $pool )
		{
			if( in_array($pool->getId(), $this->poolIds) )
			{
				$this->pools[] = $pool;
			}
		}
		return $this->pools;
	}

	protected function getPointsForProduct($product)
	{
		$points = array();
		foreach($this->getPools() as $pool)
		{
			$system = $pool->getOption('display_title');
			$total = 0;
			$min = false;
			foreach( $pool->getEvents()->asArray() as $event )
			{
				if( \is_a($event, 'LWS\WOOREWARDS\PRO\Events\I_CartPreview') )
				{
					$preview = $event->getPointsForProduct($product);
					if( is_array($preview) )
					{
						if( $min === false )
							$min = $total;
						$min += min($preview);
						$total += max($preview);
					}
					else
					{
						$total += $preview;
						if( $min !== false )
							$min += $preview;
					}
				}
			}
			if( $total > 0 )
			{
				$value = \LWS_WooRewards::formatPointsWithSymbol($total, empty($pool)? '': $pool->getName());
				if( $min !== false )
				{
					$value = (
						\LWS_WooRewards::formatPointsWithSymbol($min, empty($pool)? '': $pool->getName())
						. _x(" – ", 'min/max point preview separator', LWS_WOOREWARDS_PRO_DOMAIN)
						. $value
					);
				}
				$points[] = array(
					'system' => $system,
					'points' => $value,
				);
			}
		}
		return $points;
	}

	protected function getContent($points)
	{
		$label = \lws_get_option('lws_woorewards_label_ppp', __("With this product, you will earn ", LWS_WOOREWARDS_PRO_DOMAIN));
		if( !isset($this->stygen) )
			$label = \apply_filters('wpml_translate_single_string', $label, 'Widgets', "WooRewards - Product Points Preview - Title");

		$sep = _x(", ", "product point preview separator", LWS_WOOREWARDS_PRO_DOMAIN);
		$pcontent = '';
		$last = (count($points) -1);
		for($i=0 ; $i<count($points) ; ++$i)
		{
			$name = sprintf(_x("in %s",'points in loyalty sytem', LWS_WOOREWARDS_PRO_DOMAIN), $points[$i]['system']);
			$virgule = $i < $last ? $sep: '';
			$pcontent .= "<div class='lwss_selectable lws-wre-productpointspreview-points' data-type='Points'>{$points[$i]['points']}<span class='lwss_selectable lws-wre-productpointspreview-lsystem' data-type='Loyalty System'> {$name}</span>{$virgule}</div>";
		}

		return <<<EOT
<div class='lwss_selectable lws-wre-productpointspreview-main' data-type='Main Div'>
	<div class='lwss_selectable lwss_modify lws-wre-productpointspreview-label' data-type='Label' data-id='lws_woorewards_label_ppp'>
		<span class='lwss_modify_content'>{$label}</span>
	</div>
	{$pcontent}
</div>
EOT;
	}
}

