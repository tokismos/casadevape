<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if (!defined('ABSPATH')) {
	exit();
}

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget for customer to input email for sponsorship.
 * Can be used as a Widget, a Shortcode [lws_sponsorship] or a Guttenberg block. */
class SponsorWidget extends \LWS\WOOREWARDS\PRO\Ui\Widgets\Widget
{
	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('lws_sponsorship', array($me, 'shortcode'));
		\add_shortcode('lws_sponsorship_nonce_input', array($me, 'getNonceInput'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'wr_sponsorship', array($me, 'template'));

		add_action('init', function () {
			\wp_register_script('woorewards-sponsor', LWS_WOOREWARDS_PRO_JS.'/sponsor.js', array('jquery', 'lws-tools'), LWS_WOOREWARDS_PRO_VERSION);
			\wp_register_style('woorewards-sponsor', LWS_WOOREWARDS_PRO_CSS.'/templates/sponsor.css?stygen=lws_woorewards_sponsor_template', array(), LWS_WOOREWARDS_PRO_VERSION);
		});

		if (function_exists('register_block_type')) {
			add_action('init', array(get_class(), 'gutenberg'));
		}

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/sponsorship.php';
		$helper = new \LWS\WOOREWARDS\PRO\Core\Sponsorship();
		$helper->register();
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if ($asWidget) {
			parent::__construct(
				'lws_woorewards_sponsorship',
				__("WooRewards Sponsorship", LWS_WOOREWARDS_PRO_DOMAIN),
				array(
					'description' => __("Let your customers sponsor new customers.", LWS_WOOREWARDS_PRO_DOMAIN)
				)
			);
		}
	}

	public function template($snippet)
	{
		$this->stygen = true;
		$snippet = $this->shortcode(array(), __("Hidden block at start. Feedback to customer will appear here.", LWS_WOOREWARDS_PRO_DOMAIN));
		unset($this->stygen);
		return $snippet;
	}

	public static function gutenberg()
	{
		\wp_register_script(
			'woorewards-gutenberg-sponsor',
			LWS_WOOREWARDS_PRO_JS.'/gutenberg/sponsor.js',
			array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n', 'woorewards-gutenberg', 'jquery'),
			LWS_WOOREWARDS_PRO_VERSION
	);
		\wp_localize_script('woorewards-gutenberg-sponsor', 'lws_wr_sponsor', array(
			'stygen_href' => \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.settings', 'tab'=>'sponsorship'), \admin_url('admin.php#lws_group_targetable_sponsor_widget_style'))
		));

		\register_block_type('woorewards/sponsorship', array(
			'editor_script' => 'woorewards-gutenberg-sponsor',
			'editor_style'  => 'woorewards-sponsor',
			'script'        => 'woorewards-sponsor',
			'style'         => 'woorewards-sponsor',
		));
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		if( \get_current_user_id() || \get_option('lws_woorewards_sponsorship_allow_unlogged', '') )
		{
			$instance['unlogged'] = 'on';
			echo $args['before_widget'];
			echo $args['before_title'];
			echo \apply_filters('widget_title', empty($instance['title']) ? _x("Sponsorship", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN) : $instance['title'], $instance);
			echo $args['after_title'];
			echo $this->shortcode($instance);
			echo $args['after_widget'];
		}
	}

	/** ensure all required fields exist. */
	public function update($new_instance, $old_instance)
	{
		$new_instance = \wp_parse_args(
			array_merge($old_instance, $new_instance),
			$this->defaultArgs()
		);

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Sponsor Widget - Title", $new_instance['header']);
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Sponsor Widget - Button", $new_instance['button']);

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
			\esc_attr(_x("Sponsorship", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
		);
		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Sponsor your friend", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
		);
	}

	protected function defaultArgs()
	{
		return array(
			'title'  => '',
			'header'  => '',
			'button' => '',
			'unlogged' => \get_option('lws_woorewards_sponsorship_allow_unlogged', ''),
		);
	}

	public function getNonceInput($atts=array(), $content='')
	{
		$nonce = \esc_attr(\wp_create_nonce('lws_woorewards_sponsorship_email'));
		return "<input type='hidden' class='lws_woorewards_sponsorship_nonce' name='sponsorship_nonce' value='{$nonce}'>";
	}

	/** @brief shortcode [lws_sponsorship]
	 *	Display input box to set sponsored email, then a div for server answer. */
	public function shortcode($atts=array(), $content='')
	{
		$allowunlog = \lws_get_option('lws_woorewards_sponsorship_allow_unlogged', false);
		$atts = \shortcode_atts($this->defaultArgs(), $atts, 'lws_sponsorship');
		if( empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woorewards_sponsor_widget_title', __("Sponsor your friend(s)", LWS_WOOREWARDS_PRO_DOMAIN));
		if( empty($atts['button']) )
			$atts['button'] = \lws_get_option('lws_woorewards_sponsor_widget_submit', __("Submit", LWS_WOOREWARDS_PRO_DOMAIN));
		$ph = \esc_attr(\lws_get_option('lws_woorewards_sponsor_widget_placeholder', __("my.friend@example.com, my.other.friend@example.com", LWS_WOOREWARDS_PRO_DOMAIN)));
		$phs = \esc_attr(\lws_get_option('lws_woorewards_sponsor_widget_sponsor', __("Your email address", LWS_WOOREWARDS_PRO_DOMAIN)));

		if( !isset($this->stygen) ) // not demo
		{
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Sponsor Widget - Title");
			$atts['button'] = \apply_filters('wpml_translate_single_string', $atts['button'], 'Widgets', "WooRewards - Sponsor Widget - Button");
			$phs = \apply_filters('wpml_translate_single_string', $phs, 'Widgets', "WooRewards - Sponsor Widget - Sponsor placeholder");
			$ph = \apply_filters('wpml_translate_single_string', $ph, 'Widgets', "WooRewards - Sponsor Widget - Sponsored Placeholder");
		}

		if (!isset($this->stygen)) {
			$this->enqueueScrpits();
		}
		$hidden = '';
		$form = "<div class='lwss_selectable lws_woorewards_sponsorship_widget' data-type='Main'>";


		$form .= "<p class='lwss_selectable lwss_modify lws_woorewards_sponsorship_description' data-id='lws_woorewards_sponsor_widget_title' data-type='Header'>";
		$form .= "<span class='lwss_modify_content'>{$atts['header']}</span>";
		$form .= "</p><div class='lwss_selectable lws_woorewards_sponsorship_form' data-type='Form'>";
		if( empty($user = \wp_get_current_user()) || empty($user->ID) )
		{
			if( $atts['unlogged'] )
			{
				$form .= "<div  class='lwss_selectable lws_woorewards_sponsorship_input' data-type='Input'>";
				$form .= "<input class='lwss_selectable lwss_modify lws_woorewards_sponsorship_host_field' data-type='Field' data-id='lws_woorewards_sponsor_widget_sponsor' name='sponsor_email' type='email' placeholder='$phs' /></div>";
			}else{
				$form .= "<p>".\lws_get_option('lws_wooreward_sponsorship_nouser', __("Please log in if you want to sponsor your friends", LWS_WOOREWARDS_PRO_DOMAIN))."</p>";
			}
		}
		if( ($user && $user->ID) || $atts['unlogged'] )
		{
			$form .= $this->getNonceInput();
			$form .= "<div  class='lwss_selectable lws_woorewards_sponsorship_input' data-type='Input'>";
			$form .= "<input class='lwss_selectable lwss_modify lws_woorewards_sponsorship_field' data-type='Field' data-id='lws_woorewards_sponsor_widget_placeholder' name='sponsored_email' type='email' placeholder='$ph' /></div>";
			$form .= "<div class='lwss_selectable lwss_modify lws_woorewards_sponsorship_submit' data-id='lws_woorewards_sponsor_widget_submit' data-type='Submit'>";
			$form .= "<span class='lwss_modify_content'>{$atts['button']}</span></div></div>";
		}
		$hidden = !isset($this->stygen) ? " style='display:none;'" : '';
		$form .= "<p class='lwss_selectable lws_woorewards_sponsorship_feedback' data-type='Feedback'$hidden>{$content}</p></div>";
		return $form;
	}

	protected function enqueueScrpits()
	{
		if (!isset($this->stygen)) {
			\wp_enqueue_script('woorewards-sponsor');
		}
		\wp_enqueue_style('woorewards-sponsor');
	}
}
