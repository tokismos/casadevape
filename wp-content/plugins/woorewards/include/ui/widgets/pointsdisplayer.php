<?php
namespace LWS\WOOREWARDS\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide a widget to let display points depending on pool selection. */
class PointsDisplayer extends \WP_Widget
{
	public static function install()
	{
		return new self(false);
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget = true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_pointsdisplayer',
				__("WooRewards Points Displayer", LWS_WOOREWARDS_DOMAIN),
				array(
					'description' => __("Let your customers see their points.", LWS_WOOREWARDS_DOMAIN)
				)
			);
		}
		else
		{
			\add_action('widgets_init', function(){\register_widget(get_class());});
			\add_shortcode( 'wr_show_points', array($this, 'showPoints') );
			\add_shortcode( 'wr_simple_points', array($this, 'showPointsOnly') );
			// backward compatibility
			\add_shortcode( 'lws_show_points', array($this, 'retroShowPoints') );
			\add_shortcode( 'lws_show_points_only', array($this, 'retroShowPointsOnly') );

			\add_filter('lws_adminpanel_stygen_content_get_'.'wr_display_points', array($this, 'template'));

			\add_action('init', function(){
				\wp_register_style(
					'woorewards-show-points',
					LWS_WOOREWARDS_URL.'/css/templates/displaypoints.css?stygen=lws_woorewards_displaypoints_template',
					array(),
					LWS_WOOREWARDS_VERSION
				);
			}, 9);

			if( function_exists('register_block_type') )
				add_action( 'init', array($this, 'gutenberg') );
		}
	}

	public function template($snippet)
	{
		$this->stygen = true;
		$snippet = $this->showPoints(array('pool' => 'default'));
		unset($this->stygen);
		return $snippet;
	}

	/** @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/tutorials/block-tutorial/writing-your-first-block-type/ */
	function gutenberg()
	{
		// gutenberg block must be reistered in javascript too and define client-side render and form
		$guid = 'woorewards-gutenberg-show-points';
		\wp_register_script(
			$guid,
			LWS_WOOREWARDS_JS.'/gutenberg/pointsdisplayer.js',
			array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n', 'woorewards-gutenberg', 'jquery'),
			LWS_WOOREWARDS_VERSION
    );
    \wp_localize_script($guid, 'lws_wr_show_points', array(
			'stygen_href' => \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.settings', 'tab'=>'sty_widgets'), \admin_url('admin.php#lws_group_targetable_showpoints'))
		));

		\register_block_type('woorewards/show-points', array(
			'editor_script'   => $guid,
			'editor_style'   => 'woorewards-show-points',
			'attributes'      => array( // shortcode attributes
				'title' => array('type'=>'string', 'default'=>'')
			),
			'render_callback' => function($atts=array(), $content=''){ // render the shortcode placeholder
				$atts['title'] = \esc_attr($atts['title']);
				return "[wr_show_points title='{$atts['title']}']";
			}
		));
	}

	/**	If no 'description' set, use those defined in stygen.
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 *	@see showPoints()
	 * 	Display the widget,
	 *	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		$content = $this->showPoints(
			array(
				'pool'             => isset($instance['pool_name']) ? $instance['pool_name'] : \get_option('lws_wr_default_pool_name', 'default'),
				'title'            => isset($instance['description']) ? $instance['description'] : '',
				'more_details_url' => isset($instance['more_details_url']) ? $instance['more_details_url'] : ''
			),
			false
		);
		if( !empty($content) )
		{
			echo $args['before_widget'];
			echo $args['before_title'];
			echo \apply_filters('widget_title', $instance['title'], $instance);
			echo $args['after_title'];
			echo $content;
			echo $args['after_widget'];
		}
	}

	/** ensure all required fields exist. */
	function update( $new_instance, $old_instance )
	{
		$new_instance = \wp_parse_args(
			array_merge($old_instance, $new_instance),
			array(
				'title'            => '',
				'description'      => '',
				'pool_name'        => \get_option('lws_wr_default_pool_name', 'default'),
				'more_details_url' => ''
			)
		);

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards Show Points - title", $new_instance['description']);
    \do_action('wpml_register_single_string', 'Widgets', "WooRewards Show Points - details", $new_instance['more_details_url']);

		return $new_instance;
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = \wp_parse_args($instance, array(
			'title'            => '',
			'description'      => '',
			'pool_name'        => \get_option('lws_wr_default_pool_name', 'default'),
			'more_details_url' => ''
		));
		if( empty($instance['pool_name']) )
			$instance['pool_name'] = \get_option('lws_wr_default_pool_name', 'default');

		// title
		$this->formFieldText(
			$this->get_field_id('title'),
			__("Title", LWS_WOOREWARDS_DOMAIN),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr(_x("Current Points", "frontend", LWS_WOOREWARDS_DOMAIN))
		);

		// description
		$this->formFieldText(
			$this->get_field_id('description'),
			__("Header", LWS_WOOREWARDS_DOMAIN),
			$this->get_field_name('description'),
			\esc_attr($instance['description']),
			\esc_attr(\get_option('lws_woorewards_displaypoints_title'))
		);

		// detail page redirect button
		if(defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED)
		{
			$this->formFieldText(
				$this->get_field_id('more_details_url'),
				__("A <i>More details</i> page URL", LWS_WOOREWARDS_DOMAIN),
				$this->get_field_name('more_details_url'),
				\esc_attr($instance['more_details_url']),
				\esc_attr(\LWS_WooRewards::isWC() ? \wc_get_endpoint_url('lws_woorewards', '', \wc_get_page_permalink('myaccount')) : '')
			);
		}

		$options = array();
		foreach(\LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('deep' => false))->asArray() as $pool)
			$options[$pool->getName()] = $pool->getOption('display_title');

		// pool
		$this->formFieldSelect(
			$this->get_field_id('pool_name'),
			__("Select Loyalty System", LWS_WOOREWARDS_DOMAIN),
			$this->get_field_name('pool_name'),
			$options,
			$instance['pool_name']
		);
	}

	/** echo a form select line @param $options (array) value=>text */
	private function formFieldSelect($id, $label, $name, $options, $value)
	{
		$input = "<select id='$id' name='$name'>";
		foreach( $options as $v => $txt )
		{
			$selected = $v == $value ? ' selected' : '';
			$input .= "<option value='$v'$selected>$txt</option>";
		}
		$input .= "</select>";
		$this->formField($id, $label, $input);
	}

	/** echo a form text line */
	private function formFieldText($id, $label, $name, $value, $placeholder='')
	{
		$input = "<input class='widefat' id='$id' name='$name' type='text' value='$value' placeholder='$placeholder'/>";
		$this->formField($id, $label, $input);
	}

	/** echo a form entry line */
	private function formField($id, $label, $input)
	{
		echo "<p><label for='$id'>$label</label>$input</p>";
	}

	/** convert v2 args to new version @see showPoints */
	public function retroShowPoints($atts=array(), $content='')
	{
		$out = '';
		if( isset($atts['show_standard']) )
			$out .= $this->showPoints($atts, $content);
		if( isset($atts['show_loyalty']) )
		{
			$atts = array_merge($atts, array('pool' => $this->getLevellingPoolName()));
			$out .= $this->showPoints($atts, $content);
		}
		return $out;
	}

	/** convert v2 args to new version @see showPointsOnly */
	public function retroShowPointsOnly($atts=array(), $content='')
	{
		$out = '';
		foreach( $atts as $k => $v )
		{
			if( $k === 'show_standard' || (is_numeric($k) && $v === 'show_standard') )
			{
				$out .= $this->showPointsOnly($atts, $content);
				break;
			}
		}

		foreach( $atts as $k => $v )
		{
			if( $k === 'show_loyalty' || (is_numeric($k) && $v === 'show_loyalty') )
			{
				$out .= $this->showPointsOnly(array_merge($atts, array('pool' => $this->getLevellingPoolName())), $content);
				break;
			}
		}
		return $out;
	}

	protected function getLevellingPoolName()
	{
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare(
			"SELECT post_name FROM {$wpdb->posts}
INNER JOIN {$wpdb->postmeta} as p ON ID=p.post_id AND p.meta_key='wre_pool_prefab' AND p.meta_value='yes'
INNER JOIN {$wpdb->postmeta} as t ON ID=t.post_id AND t.meta_key='wre_pool_type' AND t.meta_value=%s
WHERE post_type=%s LIMIT 0, 1",
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
			\LWS\WOOREWARDS\Core\Pool::POST_TYPE
		));
	}

	/** @brief shortcode [wr_show_points]
	 *	Display a stylable point presentation for current user.
	 *	Default pool is 'default'.
	 *	usage: All attributes are optionnal
	 *	@code
	 *	[wr_show_points pool="<poolname>" title="<my Own Title>" more_details_url="<more details button url>"]
	 *	@endcode */
	public function showPoints($atts=array(), $content='')
	{
		$demo = (isset($this->stygen) ? $this->stygen : false);
		if( !empty($userId = \get_current_user_id()) || $demo )
		{

			if( !$demo )
				\wp_enqueue_style('woorewards-show-points');

			$atts = \shortcode_atts(
				array(
					'pool'        => '',
					'title'            => '',
					'more_details_url' => ''
				),
				$atts,
				'wr_show_points'
			);
			$poolname = empty($atts['pool']) ? \get_option('lws_wr_default_pool_name', 'default') : $atts['pool'];
			$pointstotal = ($demo ? rand(42, 128) : $this->getPoolPoints($poolname, $userId));
			if( false === $pointstotal )
				return false;

			$myacc_url    = $atts['more_details_url'];
			$displaytitle = !empty($atts['title']) ? $atts['title'] : \get_option('lws_woorewards_displaypoints_title', '');
			if( !$demo )
				$displaytitle = \apply_filters('wpml_translate_single_string', $displaytitle, 'Widgets', "WooRewards Show Points - title");
			$details = '';
			if( !empty($detail_url = \apply_filters('lws_woorewards_displaypoints_detail_url', '', $poolname, $pointstotal, $demo)) )
			{
				$href = ($demo ? '#' : \esc_attr($detail_url));
				$label = \lws_get_option('lws_woorewards_button_more_details', __("More Details", LWS_WOOREWARDS_DOMAIN));
				if( !$demo )
					$label = \apply_filters('wpml_translate_single_string', $label, 'Widgets', "WooRewards Show Points - details");

				$details = <<<EOT
<div class='lwss_selectable lws-displaypoints-bcont' data-type='Button Line'>
	<a class='lwss_selectable lwss_modify lws-displaypoints-button' data-id='lws_woorewards_button_more_details' data-type='Button' href='{$href}'>
		<span class='lwss_modify_content'>{$label}</span>
	</a>
</div>
EOT;
			}

			$content = <<<EOT
<div class='lwss_selectable lws-displaypoints-main' data-type='Main Div'>
	<div class='lwss_selectable lwss_modify lws-displaypoints-label' data-id='lws_woorewards_displaypoints_title' data-type='Header'>
		<div class='lwss_modify_content'>{$displaytitle}</div>
	</div>
	<div class='lwss_selectable lws-displaypoints-points' data-type='Points'>{$pointstotal}</div>
	{$details}
</div>
EOT;
		}else{
			$content = \lws_get_option('lws_wooreward_showpoints_nouser', __("Please log in if you want to see your loyalty points", LWS_WOOREWARDS_DOMAIN));
		}
		return $content;
	}

	/** @brief shortcode [wr_simple_points]
	 *	Display a simple point value for current user.
	 *	Default pool is 'default'.
	 *	usage: All attributes are optionnal
	 *	@code
	 *	[wr_simple_points pool="<poolname>"]
	 *	@endcode */
	public function showPointsOnly($atts=array(), $content='')
	{
		if( !empty($userId = \get_current_user_id()) )
		{
			$atts = \shortcode_atts(array('pool'=>\get_option('lws_wr_default_pool_name', 'default')), $atts, 'wr_simple_points');
			$pointstotal = intval($this->getPoolPoints($atts['pool'], $userId));
			$content = "<span class='lws-wr-simple-points lws-wr-simple-points-{$atts['pool']}'>{$pointstotal}</span>";
		}
		return $content;
	}

	/** @return points for a pool_name and a user_id */
	function getPoolPoints($poolName, $userId)
	{
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'name' => $poolName,
			'deep' => false
		))->last();

		$pointstotal = '0';
		if( $pool )
		{
			if( !$pool->userCan($userId) )
				$pointstotal = false;
			else
				$pointstotal  = $pool->getPoints($userId);
		}
		return $pointstotal;
	}
}
?>