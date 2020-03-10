<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if (!defined('ABSPATH')) {
    exit();
}

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget for customer to input email for sponsorship.
 * Can be used as a Widget, a Shortcode [lws_sponsorship] or a Guttenberg block. */
class EventsWidget extends \LWS\WOOREWARDS\PRO\Ui\Widgets\Widget
{
    public static function install()
    {
        self::register(get_class());
        $me = new self(false);
        \add_shortcode('wr_events', array($me, 'shortcode'));
        \add_filter('lws_adminpanel_stygen_content_get_'.'events_template', array($me, 'template'));
        \add_action('wp_enqueue_scripts', array( $me , 'scripts' ), 100); // priority: hope to be after theme enqueue without using dependency
    }

    public function scripts()
    {
        \wp_enqueue_style('woorewards-events', LWS_WOOREWARDS_PRO_CSS.'/templates/events.css?stygen=lws_woorewards_events_template', array(), LWS_WOOREWARDS_PRO_VERSION);
    }

    /** Will be instanciated by WordPress at need */
    public function __construct($asWidget=true)
    {
        if ($asWidget) {
            parent::__construct(
                'lws_woorewards_events',
                __("WooRewards Earning Points", LWS_WOOREWARDS_PRO_DOMAIN),
                array(
                    'description' => __("Display Ways to Earn points on a Loyalty System", LWS_WOOREWARDS_PRO_DOMAIN)
                )
            );
        }
    }

    /** ensure all required fields exist. */
    public function update($new_instance, $old_instance)
    {
			$new_instance = \wp_parse_args(
				array_merge($old_instance, $new_instance),
				$this->defaultArgs()
			);

			\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Earning methods - Header", $new_instance['header']);
			\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Earning methods - Description", $new_instance['text']);

			return $new_instance;
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
            \esc_attr(_x("Earn Loyalty Points", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
        );

        // header
        $this->eFormFieldText(
            $this->get_field_id('header'),
            __("Header", LWS_WOOREWARDS_PRO_DOMAIN),
            $this->get_field_name('header'),
            \esc_attr($instance['header']),
            \esc_attr(_x("How to earn loyalty points ", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
        );

        $options = array();
        foreach (\LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('deep' => false))->asArray() as $pool) {
            $options[$pool->getId()] = $pool->getOption('display_title');
        }

        // pool
        $this->eFormFieldSelect(
            $this->get_field_id('pool'),
            __("Select Loyalty System", LWS_WOOREWARDS_PRO_DOMAIN),
            $this->get_field_name('pool'),
            $options,
            $instance['pool']
        );

        // text
        $this->eFormFieldText(
            $this->get_field_id('text'),
            __("Text displayed to users", LWS_WOOREWARDS_PRO_DOMAIN),
            $this->get_field_name('text'),
            \esc_attr($instance['text']),
            \esc_attr(_x("Perform the actions described below to earn loyalty points", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
        );
    }

    protected function defaultArgs()
    {
        return array(
            'title'  => '',
            'header' => '',
            'text'   => '',
            'pool'   => ''
        );
    }

    /**	Display the widget,
     *	@see https://developer.wordpress.org/reference/classes/wp_widget/
     * 	display parameters in $args
     *	get option from $instance */
    public function widget($args, $instance)
    {
        echo $args['before_widget'];
        echo $args['before_title'];
        echo \apply_filters('widget_title', empty($instance['title']) ? _x("Earn Loyalty Points", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN) : $instance['title'], $instance);
        echo $args['after_title'];
        echo $this->shortcode($instance, '');
        echo $args['after_widget'];
    }

    public function template($snippet)
    {
			$this->stygen = true;
			$atts = $this->defaultArgs();
			$atts['pool'] = \get_option('lws_wr_default_pool_name', 'default');
			$events = array(
				array('desc' => 'Buy the product <a href="#">WooRewards</a>', 'earned' => '123'),
				array('desc' => 'Spend money', 'earned' => '5 Points/1 &#36;'),
				array('desc' => 'Review a product', 'earned' => '5'),
				array('desc' => 'Recurrent visit', 'earned' => '1'),
			);
			$content = $this->shortcode($atts, $events);
			unset($this->stygen);
			return $content;
    }

    public function shortcode($atts=array(), $events='')
    {
        $atts = \shortcode_atts($this->defaultArgs(), $atts, 'wr_events');
        if ($events=='') {
            $events = $this->getEvents($atts);
        }
        return $this->getContent($atts, $events);
    }

    public function getContent($atts= array(), $events='')
    {
			$labels = array(
				'desc' => __("Action to perform", LWS_WOOREWARDS_PRO_DOMAIN),
				'points' => __("Points earned", LWS_WOOREWARDS_PRO_DOMAIN)
			);

			if( empty($atts['header']) )
				$atts['header'] = \lws_get_option('lws_woorewards_events_widget_message', __("How to earn loyalty points", LWS_WOOREWARDS_PRO_DOMAIN));
			if( empty($atts['text']) )
				$atts['text'] = \lws_get_option('lws_woorewards_events_widget_text', __("Perform the actions described below to earn loyalty points", LWS_WOOREWARDS_PRO_DOMAIN));

			if( !isset($this->stygen) )
			{
				$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Earning methods - Header");
				$atts['text'] = \apply_filters('wpml_translate_single_string', $atts['text'], 'Widgets', "WooRewards - Earning methods - Description");
			}

			$lines = '';
			foreach($events as $event){
				$lines .= "<div class='lwss_selectable lws-wr-event-line' data-type='Earning Method Line'>";
				$lines .= "<div class='lwss_selectable lws-wr-event-text' data-type='Earning Method'>{$event['desc']}</div>";
				$lines .= "<div class='lwss_selectable lws-wr-event-points' data-type='Earning Method'>{$event['earned']}</div>";
				$lines .= "</div>";
			};

        $content = <<<EOT
<div class='lwss_selectable lws-wr-events-cont' data-type='Main Conteneur'>
	<div class='lwss_selectable lwss_modify lws-wr-events-header' data-id='lws_woorewards_events_widget_message' data-type='Header'>
		<span class='lwss_modify_content'>{$atts['header']}</span>
	</div>
	<div class='lwss_selectable lwss_modify lws-wr-events-text' data-id='lws_woorewards_events_widget_text' data-type='Message to users'>
		<span class='lwss_modify_content'>{$atts['text']}</span>
	</div>
	<div class='lwss_selectable lws-wr-eventslist' data-type='Earning Methods'>
		<div class='lwss_selectable lws-wr-event-title-line' data-type='Title Line'>
			<div class='lwss_selectable lws-wr-event-title-desc' data-type='Description Title'>{$labels['desc']}</div>
			<div class='lwss_selectable lws-wr-event-title-points' data-type='Points Title'>{$labels['points']}</div>
		</div>
		$lines
	</div>
</div>
EOT;

			return $content;
    }

    private function getEvents($atts=array())
    {
        $pool = $this->getPool($atts);

        /* Ways to earn points */
        $events = array();
        foreach ($pool->getEvents()->asArray() as $item) {
            $eventInfo = array();
            $eventInfo['desc'] = ($item->getTitle()!='') ? $item->getTitle() : $item->getDescription('frontend');
            $eventInfo['earned'] = $item->getMultiplier('view');
            $events[] = $eventInfo;
        }
        return $events;
    }

    /** @param $atts (in/out array) */
    private function getPool(&$atts)
    {
        $pool = false;
        if (!is_array($atts)) {
            $atts = array();
        }
        if (!isset($atts['pool'])) {
            $atts['pool'] = '';
        }

        if (empty($atts['pool'])) { // v2 compatibilty: no pool specified means 'levelling' prefab
            $pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
                    'numberposts' => 1,
                    'meta_query'  => array(
                        array(
                            'key'     => 'wre_pool_prefab',
                            'value'   => 'yes', // This cannot be empty because of a bug in WordPress
                            'compare' => 'LIKE'
                        ),
                        array(
                            'key'     => 'wre_pool_type',
                            'value'   => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
                            'compare' => 'LIKE'
                        )
                    )
                ))->last();
        } else {
            $pool = \LWS_WooRewards_Pro::getLoadedPools()->find($atts['pool']);
            if (empty($pool) && is_numeric($atts['pool']) && $atts['pool'] > 0) {
                $pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p'=>$atts['pool']))->last();
            }
            if (empty($pool)) {
                $pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('name'=>$atts['pool']))->last();
            }
        }
        return $pool;
    }
}
