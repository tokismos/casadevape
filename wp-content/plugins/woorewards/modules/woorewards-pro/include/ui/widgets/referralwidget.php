<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget for customer to get a referral link.
 * Can be used as a Widget, a Shortcode [lws_sponsorship]. */
class ReferralWidget extends \LWS\WOOREWARDS\PRO\Ui\Widgets\Widget
{
	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('lws_referral', array($me, 'shortcode'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'wr_referral', array($me, 'template'));

		add_action('init', function(){
			\wp_register_script('woorewards-referral',LWS_WOOREWARDS_PRO_JS.'/referral.js',array('jquery'),LWS_WOOREWARDS_PRO_VERSION);
			\wp_register_style('woorewards-referral', LWS_WOOREWARDS_PRO_CSS.'/templates/referral.css?stygen=lws_woorewards_referral_template', array(), LWS_WOOREWARDS_PRO_VERSION);
		});

		\add_filter('query_vars', function($vars){
			$vars[] = 'referral';
			return $vars;
		});
		\add_action('parse_query', array($me, 'grabReferral'));
	}

	/** Keep referral in session to let visitor continues without losing referral info.
	 * read $_COOKIE['lws_referral_'.COOKIEHASH] */
	public function grabReferral(&$query)
	{
		$referral = isset($query->query['referral']) ? trim($query->query['referral']) : '';
		if( !empty($referral) )
		{
			$key = 'lws_referral_'.COOKIEHASH;

			if( !isset($_COOKIE[$key]) || $referral != trim($_COOKIE[$key]) )
			{
				\do_action('lws_woorewards_referral_followed', $referral);
			}

			// copy any url arg referral to cookie
			$expires = \time()+(60*60*12);// in second since unix epoch: 12h
			\setcookie($key, $referral, $expires, COOKIEPATH, COOKIE_DOMAIN);
		}
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_referral',
				__("WooRewards Referral", LWS_WOOREWARDS_PRO_DOMAIN),
				array(
					'description' => __("Provide referral link to your customers.", LWS_WOOREWARDS_PRO_DOMAIN)
				)
			);
		}
	}

	function template($snippet){
		$this->stygen = true;
		$snippet = $this->shortcode();
		unset($this->stygen);
		return $snippet;
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		if( !empty(\get_current_user_id()) )
		{
			echo $args['before_widget'];
			if( is_array($instance) && isset($instance['title']) && !empty($instance['title']) )
			{
				echo $args['before_title'];
				echo \apply_filters('widget_title', $instance['title'], $instance);
				echo $args['after_title'];
			}
			if( isset($instance['url']) && !empty($instance['url']) )
				$instance['url'] = \apply_filters('wpml_translate_single_string', $instance['url'], 'Widgets', "WooRewards - Referral Widget - Redirection");
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

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Referral Widget - Header", $new_instance['header']);
		if( !empty($new_instance['url']) )
			\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Referral Widget - Redirection", $new_instance['url']);

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
			is_array($instance) && isset($instance['title']) ? \esc_attr($instance['title']) : ''
		);
		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Share that referral link", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
		);
		// url
		$this->eFormFieldText(
			$this->get_field_id('url'),
			__("Shared url (Optional)", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('url'),
			\esc_attr($instance['url'])
		);
	}

	protected function defaultArgs()
	{
		return array(
			'title'  => '',
			'header'  => '',
			'url'  => '',
		);
	}

	public function getOrCreateToken($userId)
	{
		$token = \get_user_meta($userId, 'lws_woorewards_user_referral_token', true);
		if( empty($token) )
		{
			$user = \get_user_by('ID', $userId);
			if( $user )
			{
				$token = \sanitize_key(\wp_hash(json_encode($user).rand()));
				\update_user_meta($userId, 'lws_woorewards_user_referral_token', $token);
			}
		}
		return $token;
	}

	/** @brief shortcode [lws_referral]
	 *	 */
	public function shortcode($atts=array(), $content='')
	{
		$atts = \wp_parse_args($atts, $this->defaultArgs());
		if( empty($userId = \get_current_user_id()) )
			return $content;

		if( !isset($atts['header']) || empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woorewards_referral_widget_message', __("Share that referral link", LWS_WOOREWARDS_PRO_DOMAIN));
		if( !isset($this->stygen) )
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Referral Widget - Header");
		$this->enqueueScripts();

		$url = '';
		if( !isset($this->stygen) )
		{
			if( isset($atts['url']) && !empty($atts['url']) ){
				$url = \htmlentities($atts['url'] . \add_query_arg('referral', $this->getOrCreateToken($userId), false));
			}else{
				$protocol = 'http://';
				if( (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') )
					$protocol = 'https://';
				$url = \htmlentities($protocol . $_SERVER['HTTP_HOST'] . \add_query_arg('referral', $this->getOrCreateToken($userId), false));
			}
		}
		else
			$url = \htmlentities(\add_query_arg('referral', $this->getOrCreateToken($userId), \home_url()));


		$content = 	"<div class='lwss_selectable lws-woorewards-referral-widget' data-type='Main'>";
		$content .= "<div class='lwss_selectable lwss_modify lws-woorewards-referral-description' data-id='lws_woorewards_referral_widget_message' data-type='Header'>";
		$content .= "<span class='lwss_modify_content'>{$atts['header']}</span>";
		$content .= "</div>";
		$content .= "<div class='lwss_selectable lws-woorewards-referral-field-copy lws_referral_value_copy' data-type='Referral link'>";
		$content .= "<div class='lwss_selectable lws-woorewards-referral-field-copy-text content' tabindex='0' data-type='Link'>{$url}</div>";
		$content .= "<div class='lwss_selectable lws-woorewards-referral-field-copy-icon lws-icon-copy1 copy' data-type='Copy button'></div>";
		$content .= "</div></div>";
		return $content;

	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('lws-icons');
		if( !isset($this->stygen) )
		{
			\wp_enqueue_script('woorewards-referral');
			\wp_enqueue_style('woorewards-referral');
		}
	}

}

?>