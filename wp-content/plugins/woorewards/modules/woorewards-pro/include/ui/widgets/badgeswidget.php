<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if (!defined('ABSPATH')) {
    exit();
}

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget to display badges
 * Can be used as a Widget or a Shortcode [lws_badges]. */
class BadgesWidget extends \LWS\WOOREWARDS\PRO\Ui\Widgets\Widget
{
    public static function install()
    {
        self::register(get_class());
        $me = new self(false);
        \add_shortcode('lws_badges', array($me, 'shortcode'));
        \add_filter('lws_adminpanel_stygen_content_get_'.'badges_template', array($me, 'template'));
        \add_action('wp_enqueue_scripts', array( $me , 'scripts' ), 100); // priority: hope to be after theme enqueue without using dependency
    }

    public function scripts()
    {
        \wp_enqueue_style('woorewards-badges-widget', LWS_WOOREWARDS_PRO_CSS.'/templates/badges.css?stygen=lws_woorewards_badges_template', array(), LWS_WOOREWARDS_PRO_VERSION);
    }

    /** Will be instanciated by WordPress at need */
    public function __construct($asWidget=true)
    {
        if ($asWidget) {
            parent::__construct(
                'lws_woorewards_badges',
                __("WooRewards Badges", LWS_WOOREWARDS_PRO_DOMAIN),
                array(
                    'description' => __("Display Badges", LWS_WOOREWARDS_PRO_DOMAIN)
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

			\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Badges - Title", $new_instance['header']);

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
            \esc_attr(_x("Badges", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
        );

        // header
        $this->eFormFieldText(
            $this->get_field_id('header'),
            __("Header", LWS_WOOREWARDS_PRO_DOMAIN),
            $this->get_field_name('header'),
            \esc_attr($instance['header']),
            \esc_attr(_x("Here is the list of badges available on this website", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
        );

		// behavior
		$this->eFormFieldSelect(
			$this->get_field_id('display'),
			__("Filter Badges", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('display'),
			array(
				'all'      => __("All", LWS_WOOREWARDS_PRO_DOMAIN),
				'owned'     => __("Owned only (requires a logged customer)", LWS_WOOREWARDS_PRO_DOMAIN)
			),
			$instance['display']
		);

    }

    protected function defaultArgs()
    {
        return array(
            'title'  => '',
            'header' => '',
            'display'=> 'all',
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
        echo \apply_filters('widget_title', empty($instance['title']) ? _x("Badges List", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN) : $instance['title'], $instance);
        echo $args['after_title'];
        echo $this->shortcode($instance, '');
        echo $args['after_widget'];
    }

    public function template($snippet)
    {
			$this->stygen = true;
			$atts = $this->defaultArgs();
			$badges = array(
				array(
					'thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/cat.png',
					'title' => 'The Cat',
					'description' => "Look at me. You know I'm cute even when I break your furniture",
					'unlockDate' => false,
					'rarityPercent' => '54.3',
					'rarityLabel' => 'Common',
				),
				array(
					'thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/horse.png',
					'title' => 'The White Horse',
					'description' => "Arya Stark : I'm out of this s***",
					'unlockDate' => false,
					'rarityPercent' => '9.6',
					'rarityLabel' => 'Epic',
				),
				array(
					'thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/chthulu.png',
					'title' => 'Chtulhu rules',
					'description' => "You unleashed the power of Chthulu over the world",
					'unlockDate' => date_create(),
					'rarityPercent' => '1.2',
					'rarityLabel' => 'Legendary',
				),
			);
      $content = $this->shortcode($atts, $badges);
      unset($this->stygen);
      return $content;
    }

    public function shortcode($atts=array(), $badges='')
    {
        $atts = \wp_parse_args($atts, $this->defaultArgs());
        if($badges=='')
            $badges = $this->getbadges($atts);
        return $this->getContent($atts, $badges);
    }

    public function getContent($atts= array(), $badges='')
    {
		$labels = array(
			'rarity' 	=> __("Rarity", LWS_WOOREWARDS_PRO_DOMAIN),
			'unlock' 	=> __("Unlock Date", LWS_WOOREWARDS_PRO_DOMAIN),
		);

        if (empty($atts['header']))
            $atts['header'] = \lws_get_option('lws_woorewards_badges_widget_message', _x("Here is the list of badges available on this website", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN));
        if( !isset($this->stygen) )
					$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Badges - Title");

        $bcontent= "<div class='lwss_selectable lws-badges-container' data-type='Badges Container'>";
        foreach( $badges as $badge )
        {
            if($atts['display']=='all' || ($atts['display']=='owned' && !empty($badge['unlockDate'])))
            {
                $unlockLine ='';
                $ownedDiv = "<div class='lwss_selectable lws-badge-container' data-type='Badge'>";
                if(!empty($badge['unlockDate'])){
                    $unlockLine ="<div class='lwss_selectable lws-badge-date' data-type='Unlock Date'>{$labels['unlock']} : {$badge['unlockDate']->format("Y-m-d H:i:s")}</div>";
                    $ownedDiv = "<div class='lwss_selectable lws-owned-badge-container' data-type='Owned Badge'>";
                }
                $bcontent.= $ownedDiv;
                $bcontent.= "<div class='.lwss_selectable lws-badge-imgcol' data-type='Image'><img class='lws-badge-img' src='{$badge['thumbnail']}'/></div>";
                $bcontent.= "<div class='.lwss_selectable lws-badge-contentcol' data-type='Content'>";
                $bcontent.= "<div class='.lwss_selectable lws-badge-title' data-type='Title'>{$badge['title']}</div>";
                $bcontent.= "<div class='.lwss_selectable lws-badge-text' data-type='Description'>{$badge['description']}</div>";
                $bcontent.= "<div class='.lwss_selectable lws-badge-extraInfo' data-type='Extra Information'>";
                $bcontent.= "<div class='.lwss_selectable lws-badge-rarity' data-type='Rarity'>{$badge['rarityLabel']} - {$badge['rarityPercent']}%</div>";
                $bcontent.= $unlockLine;
                $bcontent.= "</div>";
                $bcontent.= "</div>";
                $bcontent.= "</div>";
            };
        }
        $bcontent .= "</div>";

        $content = <<<EOT
            <div class='lwss_selectable lws-woorewards-badges-cont' data-type='Main Container'>
				<div class='lwss_selectable lwss_modify lws-wr-badges-header' data-id='lws_woorewards_badges_widget_message' data-type='Header'>
					<span class='lwss_modify_content'>{$atts['header']}</span>
                </div>
                $bcontent
			</div>
EOT;

        return $content;
    }

    private function getbadges($atts=array())
    {

        /* Ways to earn points */
        $badges = array();
		$user = \wp_get_current_user();
        $all_badges = \LWS\WOOREWARDS\PRO\Core\Badge::loadBy('', true);
		foreach( $all_badges as $a_badge )
		{
			$badge['thumbnail'] = $a_badge->getThumbnailUrl();
			$rarity_info = $a_badge->getBadgeRarity();
			$badge['title'] = $a_badge->getTitle();
			$badge['description'] = $a_badge->getMessage();
			$badge['rarityPercent'] = $rarity_info['percentage'];
			$badge['rarityLabel'] = $rarity_info['rarity'];
			$badge['unlockDate'] = $a_badge->ownedBy($user->ID);
			$badges[] = $badge;
		}
        return $badges;
    }

}
