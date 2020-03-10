<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if (!defined('ABSPATH')) {
	exit();
}

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget to display achievements
 * Can be used as a Widget or a Shortcode [lws_achievements]. */
class AchievementsWidget extends \LWS\WOOREWARDS\PRO\Ui\Widgets\Widget
{
	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('lws_achievements', array($me, 'shortcode'));
		\add_filter('lws_adminpanel_stygen_content_get_'.'achievements_template', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array( $me , 'scripts' ), 100); // priority: hope to be after theme enqueue without using dependency
	}

	public function scripts()
	{
		\wp_enqueue_style('woorewards-achievements-widget', LWS_WOOREWARDS_PRO_CSS.'/templates/achievements.css?stygen=lws_woorewards_achievements_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if ($asWidget) {
			parent::__construct(
				'lws_woorewards_achievements',
				__("WooRewards Achievements", LWS_WOOREWARDS_PRO_DOMAIN),
				array(
					'description' => __("Display Achievements", LWS_WOOREWARDS_PRO_DOMAIN)
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

			\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Achievements - Title", $new_instance['header']);

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
			\esc_attr(_x("Achievements", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
		);

		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Here is a list of all achievements available on this website", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
		);

		// behavior
		$this->eFormFieldSelect(
			$this->get_field_id('display'),
			__("Filter achievements", LWS_WOOREWARDS_PRO_DOMAIN),
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
		echo \apply_filters('widget_title', empty($instance['title']) ? _x("Achievements List", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN) : $instance['title'], $instance);
		echo $args['after_title'];
		echo $this->shortcode($instance, '');
		echo $args['after_widget'];
	}

	public function template($snippet)
	{
			$this->stygen = true;
		$atts = $this->defaultArgs();
		$achievements = array(
			array(
				'badge_thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/cat.png',
				'badge_title' => 'The Cat',
				'badge_description' => "Look at me. You know I'm cute even when I break your furniture",
				'ach_title' => 'Pet the cat',
				'action' => 'Product Review',
				'occurences' => '5',
				'done' => '3',
			),
			array(
				'badge_thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/horse.png',
				'badge_title' => 'The White Horse',
				'badge_description' => "Arya Stark : I'm out of this s***",
				'ach_title' => 'Flee the city',
				'action' => 'Sponsor a friend',
				'occurences' => '5',
				'done' => '1',
			),
			array(
				'badge_thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/chthulu.png',
				'badge_title' => 'Chtulhu rules',
				'badge_description' => "You unleashed the power of Chthulu over the world",
				'ach_title' => 'Invoke Chthulu',
				'action' => 'Place an order (amount greater than 10.00)',
				'occurences' => '10',
				'done' => '15',
			),
		);
		$content = $this->shortcode($atts, $achievements);
		unset($this->stygen);
		return $content;
	}

	public function shortcode($atts=array(), $achievements='')
	{
		$atts = \wp_parse_args($atts, $this->defaultArgs());
		if($achievements=='')
			$achievements = $this->getAchievements($atts);
		return $this->getContent($atts, $achievements);
	}

	public function getContent($atts= array(), $achievements='')
	{
		$labels = array(
			'baward' 	=> __("achievement awarded", LWS_WOOREWARDS_PRO_DOMAIN),
			'aunlocked'	=> __("Achievement unlocked !", LWS_WOOREWARDS_PRO_DOMAIN),
			'aprogress' => __("Current progress", LWS_WOOREWARDS_PRO_DOMAIN),
		);


		if (empty($atts['header'])) {
			$atts['header'] = \lws_get_option('lws_woorewards_achievements_widget_message', _x("Here is the list of achievements available on this website", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN));
		}
		if( !isset($this->stygen) )
					$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Achievements - Title");

		$acontent = "<div class='lwss_selectable lws-achievements-container' data-type='Achievements Container'>";
		foreach( $achievements as $achievement )
		{
			if($achievement['done']>=$achievement['occurences']){
				$width = '100';
				$extraclass = 'success';
				$owned = $labels['aunlocked'];
			}else{
				$width = intval($achievement['done'])*100/$achievement['occurences'];
				$extraclass = '';
				$owned = $labels['aprogress'].' : '.$achievement['done'].'/'.$achievement['occurences'];
			}

			$acontent.= "<div class='lwss_selectable lws-achievement-container $extraclass' data-type='Achievement Box'>";
			$acontent.= "<div class='lwss_selectable lws-achievement-top' data-type='Top part'>";
			$acontent.= "<div class='lwss_selectable lws-achievement-imgcol' data-type='Thumbnail'><img class='lws-achievement-img' src='{$achievement['badge_thumbnail']}'/></div>";
			$acontent.= "<div class='lwss_selectable lws-achievement-contentcol' data-type='Achievement Content'>";
			$acontent.= "<div class='lwss_selectable lws-achievement-title' data-type='Achivement title'>{$achievement['ach_title']}</div>";
			$acontent.= "<div class='lwss_selectable lws-achievement-achievement-line' data-type='Badge Data'>";
			$acontent.= "<div class='lwss_selectable lws-achievement-achievement-title' data-type='Badge title'>{$labels['baward']} : {$achievement['badge_title']}</div>";
			$acontent.= "<div class='lwss_selectable lws-achievement-achievement-desc' data-type='Badge description'>{$achievement['badge_description']}</div>";
			$acontent.= "</div></div></div>";
			$acontent.= "<div class='lwss_selectable lws-achievement-bottom' data-type='Bottom part'>";
			$acontent.= "<div class='lwss_selectable lws-achievement-action-line' data-type='Text Line'>{$achievement['action']}</div>";
			$acontent.= "<div class='lwss_selectable lws-achievement-progress-line' data-type='Progress Line'>";
			$acontent.= "<div class='lwss_selectable lws-achievement-progress-leftval' data-type='Left Value'>0</div>";
			$acontent.= "<div class='lwss_selectable lws-achievement-progress-bar' data-type='Progress bar Background'><div class='lwss_selectable lws-achievement-progressed-bar' style='width:{$width}%' data-type='Progress bar foreground'></div></div>";
			$acontent.= "<div class='lwss_selectable lws-achievement-progress-rightval' data-type='Right value'>{$achievement['occurences']}</div>";
			$acontent.= "</div>";
			$acontent.= "<div class='lwss_selectable lws-achievement-action-progtext $extraclass' data-type='Progress Text'>{$owned}</div>";
			$acontent.= "</div>";
			$acontent.= "</div>";
		}
		$acontent .= "</div>";

		$content = <<<EOT
			<div class='lwss_selectable lws-woorewards-achievements-cont' data-type='Main Container'>
				<div class='lwss_selectable lwss_modify lws-wr-achievements-header' data-id='lws_woorewards_achievements_widget_message' data-type='Header'>
					<span class='lwss_modify_content'>{$atts['header']}</span>
				</div>
				$acontent
			</div>
EOT;

		return $content;
	}

	private function getAchievements($atts=array())
	{
		$achievements = array();
		$userId = \get_current_user_id();
		$all_achievements = \LWS_WooRewards_Pro::getLoadedAchievements();
		foreach( $all_achievements->asArray() as $one_achievement )
		{
			$badge = $one_achievement->getBadge();
			$achievement['badge_thumbnail'] = $badge->getThumbnailUrl();
			$achievement['badge_title'] = $badge->getTitle();
			$achievement['badge_description'] = $badge->getMessage();
			$achievement['ach_title'] = $one_achievement->getOption('display_title');
			$achievement['action'] = $one_achievement->getEvents()->first()->getDescription();
			$achievement['occurences'] = $one_achievement->getTheReward()->getCost();
			$achievement['done'] = $one_achievement->getPoints($userId);
			if( ($atts['display']=='owned' && $achievement['done']>=$achievement['occurences']) || $atts['display']=='all')
				$achievements[] = $achievement;
		}
		return $achievements;
	}

}
