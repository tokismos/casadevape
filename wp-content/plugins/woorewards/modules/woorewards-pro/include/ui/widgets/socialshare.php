<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget for customer to share a content on social medias.
 * Can be used as a Widget, a Shortcode [lws_social_share]. */
class SocialShareWidget extends \LWS\WOOREWARDS\PRO\Ui\Widgets\Widget
{
	const URL_ARG = 'wrshare';
	const URL_ARG_P = 'wrshare2';
	const META_KEY = 'lws_woorewards_social_share_token_';

	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('lws_social_share', array($me, 'shortcode'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'wr_social_share', array($me, 'template'));
		\add_filter('query_vars', array($me, 'queryVars'));
		\add_action('parse_query', array($me, 'grabReferral'));

		add_action('init', function () {
			\wp_register_style('woorewards-social-share', LWS_WOOREWARDS_PRO_CSS.'/templates/social-share.css?stygen=lws_woorewards_social_share_template', array(), LWS_WOOREWARDS_PRO_VERSION);
			\wp_register_script('woorewards-social-share',LWS_WOOREWARDS_PRO_JS.'/social-share.js',array('jquery', 'lws-tools'),LWS_WOOREWARDS_PRO_VERSION, true);
		});
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_social_share',
				__("WooRewards Social share", LWS_WOOREWARDS_PRO_DOMAIN),
				array(
					'description' => __("Provide Social Share links to your customers.", LWS_WOOREWARDS_PRO_DOMAIN)
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
		if( !empty(\get_current_user_id()) || !empty(\get_option('lws_woorewards_social_display_unconnected', '')) )
		{
			echo $args['before_widget'];
			if( is_array($instance) && isset($instance['title']) && !empty($instance['title']) )
			{
				echo $args['before_title'];
				echo \apply_filters('widget_title', $instance['title'], $instance);
				echo $args['after_title'];
			}
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

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Social Share - Title", $new_instance['header']);
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Social Share - Description", $new_instance['text']);

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
			\esc_attr(_x("Share that content on Social Medias", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
		);
		// text
		$this->eFormFieldText(
			$this->get_field_id('text'),
			__("Text displayed to users", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('text'),
			\esc_attr($instance['text']),
			\esc_attr(_x("Earn loyalty points by sharing this page with your friends on social medias", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
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
			'text'  => '',
			'url'  => '',
		);
	}

	public function getOrCreateToken($userId, $social)
	{
		$token = \get_user_meta($userId, self::META_KEY.$social, true);
		if( empty($token) && ($user = \get_user_by('ID', $userId)) )
		{
			$token = \sanitize_key(\wp_hash($social.json_encode($user).rand()));
			\update_user_meta($userId, self::META_KEY.$social, $token);
		}
		return $token;
	}

	function queryVars($vars)
	{
		$vars[] = self::URL_ARG;
		$vars[] = self::URL_ARG_P;
		return $vars;
	}

	/** Keep referral in session to let visitor continues without losing referral info.
	 * read $_COOKIE['lws_referral_'.COOKIEHASH] */
	public function grabReferral(&$query)
	{
		$referral = isset($query->query[self::URL_ARG]) ? trim($query->query[self::URL_ARG]) : '';
		$hash = isset($query->query[self::URL_ARG_P]) ? trim($query->query[self::URL_ARG_P]) : '';

		if( !empty($referral) && !empty($hash) )
		{
			$key = 'lws_from_social_'.COOKIEHASH;
			$cookie = ($referral .'-'. $hash);
			if( !isset($_COOKIE[$key]) || $cookie != trim($_COOKIE[$key]) )
			{
				// find the source user
				global $wpdb;
				$metakey = self::META_KEY . '%';
				$meta = $wpdb->get_row($wpdb->prepare(
					"SELECT user_id, meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE '{$metakey}' AND meta_value=%s",
					$referral
				));
				if( $meta )
				{
					$social = substr($meta->meta_key, strlen(self::META_KEY));
					\do_action('lws_woorewards_social_backlink', $meta->user_id, $social, $hash);
				}
			}
			// copy any url arg referral to cookie
			$expires = \time()+(60*60*12);// in second since unix epoch: 12h
			\setcookie($key, $cookie, $expires, COOKIEPATH, COOKIE_DOMAIN);
		}
	}

	/** @brief shortcode [lws_sponsorship]
	 *	Display input box to set sponsored email, then a div for server answer. */
	public function shortcode($atts=array(), $content='')
	{
		$atts = \wp_parse_args($atts, $this->defaultArgs());
		if( empty($userId = \get_current_user_id()) )
		{
			if( empty(\get_option('lws_woorewards_social_display_unconnected', '')) )
				return $content;
		}

		$demo = isset($this->stygen);
		$nonce = $demo ? '' : \esc_attr(\wp_create_nonce('lws_woorewards_socialshare'));
		$hash = '';
		if( !$demo && $userId )
		{
			if( !empty($atts['url']) )
				$hash = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCustomPageHash($atts['url']);
			else
				$hash = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCurrentPageHash();
		}
		$escHash = \esc_attr($hash);

		$buttons = '';
		$socials = \lws_get_option(
			'lws_woorewards_smshare_socialmedias',
			\LWS\WOOREWARDS\PRO\Core\Socials::instance()->getSupportedNetworks()
		);
		$popup = '';
		if( !empty(\get_option('lws_woorewards_social_share_popup', '')) )
		{
			$popup = " data-popup='on'";
		}

		foreach($socials as $social)
		{
			$icon = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getIcon($social);
			$href = '#';
			$target='';
			if( !$demo )
			{
				$url = '';
				if( $userId )
				{
					if( !empty($atts['url']) )
					{
						$url = \add_query_arg(
							array(
								self::URL_ARG => $this->getOrCreateToken($userId, $social),
								self::URL_ARG_P => $hash
							),
							$atts['url']
						);
					}else{
						$url = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCurrentPageUrl(array(
							self::URL_ARG => $this->getOrCreateToken($userId, $social),
							self::URL_ARG_P => $hash
						));
					}
				}
				else
				{
					if( !empty($atts['url']) )
						$url = $atts['url'];
					else
						$url = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCurrentPageUrl();
				}
				$href = \esc_attr(\LWS\WOOREWARDS\PRO\Core\Socials::instance()->getShareLink($social, $url));
				$target='_blank';
			}
			$buttons .= "<a target='{$target}'{$popup} href='{$href}' class='lwss_selectable lws-woorewards-social-button {$icon}' data-n='{$nonce}' data-s='{$social}' data-p='{$escHash}' data-type='{$social} link'></a>";
		}

		if( empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woorewards_social_share_widget_message', __("Share that content on Social Medias", LWS_WOOREWARDS_PRO_DOMAIN));
		if( empty($atts['text']) )
			$atts['text'] = \lws_get_option('lws_woorewards_social_share_widget_text', __("Earn loyalty points by sharing this page with your friends on Social Medias", LWS_WOOREWARDS_PRO_DOMAIN));
		if( !$demo )
		{
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Social Share - Title");
			$atts['text'] = \apply_filters('wpml_translate_single_string', $atts['text'], 'Widgets', "WooRewards - Social Share - Description");
			if( !$userId )
			{
				$atts['text'] = \lws_get_option('lws_woorewards_social_text_unconnected', __("Only logged in users can earn points for sharing", LWS_WOOREWARDS_PRO_DOMAIN));
				$atts['text'] = \apply_filters('wpml_translate_single_string', $atts['text'], 'Widgets', "WooRewards - Social Share - Not connected");
			}
		}
		$this->enqueueScripts($demo);

		$content = <<<EOT
<div class='lwss_selectable lws-woorewards-social_share-widget' data-type='Main'>
	<div class='lwss_selectable lwss_modify lws-woorewards-social_share-description' data-id='lws_woorewards_social_share_widget_message' data-type='Header'>
		<span class='lwss_modify_content'>{$atts['header']}</span>
	</div>
	<div class='lwss_selectable lwss_modify lws-woorewards-social_share-text' data-id='lws_woorewards_social_share_widget_text' data-type='Message to users'>
		<span class='lwss_modify_content'>{$atts['text']}</span>
	</div>
	<div class='lwss_selectable lws-woorewards-social_share-btline' data-type='Buttons Line'>
		{$buttons}
	</div>
</div>
EOT;
		return $content;
	}

	protected function enqueueScripts($demo=false)
	{
		\wp_enqueue_style('lws-icons');
		if( !$demo )
		{
			\wp_enqueue_script('jquery');
			\wp_enqueue_script('lws-tools');
			\wp_enqueue_script('woorewards-social-share');
			// cause stygen already include it
			\wp_enqueue_style('woorewards-social-share');
		}

		\do_action('lws_woorewards_socials_scripts', $demo);
	}
}
