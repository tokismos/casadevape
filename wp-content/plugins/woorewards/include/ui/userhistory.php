<?php
namespace LWS\WOOREWARDS\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Display default pool user point history.
 * Shortcodes wr_show_history and woorewards_historic do the same but the second is for backward compatibility. */
class UserHistory
{
	function __construct()
	{
		/** attribute 'stack' default value is 'default' */
		\add_shortcode('woorewards_historic' , array($this, 'show'));
		\add_shortcode('wr_show_history' , array($this, 'show'));
		\add_action('wp_enqueue_scripts', array($this, 'scripts'));
	}

	public function scripts()
	{
		\wp_enqueue_style('woorewards-history' , LWS_WOOREWARDS_CSS.'/history.css', array(), LWS_WOOREWARDS_VERSION);
	}

	/** shortcode: return the user point history as html table. */
	public function show($atts=array(), $content='')
	{
		$user_id = \get_current_user_id();
		if( empty($user_id) )
			return $content;

		$atts = \shortcode_atts(array('pool_name'=>\get_option('lws_wr_default_pool_name', 'default')), $atts, 'woorewards_historic');

		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'name' => $atts['pool_name'],
			'deep' => false
		))->last();

		if( !empty($pool) )
		{
			$stack = $pool->getStack($user_id);
			$points = $stack->getHistory();
			return self::format($points, $stack->get());
		}
		else
			return $content;
	}

	/**	@param $points (array of {op_date, op_value, op_result, op_reason})
	 *	@param $total (int) current points */
	static function format($points, $total)
	{
		$date_format = \get_option('date_format');
		$reset = __("Reset to %d", LWS_WOOREWARDS_DOMAIN);
		$label = __("Total", LWS_WOOREWARDS_DOMAIN);

		$content = "<div class='lws-woorewards-historic-show-current'>";
		$content .= "<span class='lws-woorewards-historic-show-current-label'>$label</span>";
		$content .= "<span class='lws-woorewards-historic-show-current-points'>$total</span>";
		$content .= "</div><div class='lws-woorewards-historic-points'>";

		foreach($points as &$point)
		{
			$point['op_date'] = \mysql2date($date_format, $point['op_date']);
			if( is_null($point['op_value']) )
				$point['op_value'] = sprintf($reset, $point['op_result']);
			else
				$point['op_value'] = sprintf("%+d", $point['op_value']);

			$content .= "<div class='lws-woorewards-historic-points-row'>";
			$content .= "<div class='lws-woorewards-historic-points-cell-date'>{$point['op_date']}</div>";
			$content .= "<div class='lws-woorewards-historic-points-cell-commentar'>{$point['op_reason']}</div>";
			$content .= "<div class='lws-woorewards-historic-points-cell-points'>{$point['op_value']}</div>";
			$content .= "</div>";
		}

		$content .= "</div>";
		return $content;
	}

}

?>