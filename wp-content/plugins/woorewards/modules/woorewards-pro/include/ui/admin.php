<?php
namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointspoolfilter.php';

/** Create the backend menu and settings pages. */
class Admin
{
	const POOL_OPTION_PREFIX = 'lws-wr-pool-option-';

	public function __construct()
	{
		$this->poolPrefix = 'wr_upool_';
		\LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsPoolFilter::install();

		\add_action('admin_enqueue_scripts', array($this, 'scripts'));

		\add_filter('lws_woorewards_ui_loyalty_tab_get', array($this, 'getLoyaltyTab'));
		\add_filter('lws_adminpanel_make_page_' . LWS_WOOREWARDS_PAGE.'.loyalty', array($this, 'addBackButton'));
		\add_filter('lws_adminpanel_make_page_' . LWS_WOOREWARDS_PAGE.'.settings', array($this, 'addSettings'));
		\add_filter('lws_adminpanel_make_page_' . LWS_WOOREWARDS_PAGE.'.settings', array($this, 'addWidgets'));
		\add_filter('lws_adminpanel_make_page_' . LWS_WOOREWARDS_PAGE.'.settings', array($this, 'addWooCommerce'), 100); // priority: menu sort
		\add_filter('lws_adminpanel_make_page_' . LWS_WOOREWARDS_PAGE.'.settings', array($this, 'addSponsorship'), 101); // priority: menu sort
		\add_filter('lws_adminpanel_make_page_' . LWS_WOOREWARDS_PAGE.'.settings', array($this, 'addAPI'), 102);

		if( !empty(\get_option('lws_woorewards_manage_badge_enable', 'on')) )
			\add_filter('lws_adminpanel_pages_'.LWS_WOOREWARDS_PAGE, array($this, 'addAchievementPage'));

		\add_filter('lws_woorewards_admin_pool_general_settings', array($this, 'poolGeneralSettings'), 15, 2); // priority to set after

		\add_filter('lws_woorewards_ui_userspoints_filters', array($this, 'userspointsFilters'));

		// grab woocommerce styles if any to appends them on stygen
		\add_filter('woocommerce_enqueue_styles', array($this, 'grabWCStyles'),0);
	}

	public function scripts($hook)
	{
		if( false !== strpos($hook, LWS_WOOREWARDS_PAGE) )
		{
			\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));

			\wp_enqueue_script('lws-radio');
			\wp_enqueue_style('lws-radio');

			$tab = isset($_GET['tab']) ? $_GET['tab'] : '';
			if( strpos($hook, 'loyalty') !== false )
			{
				foreach( ($deps = array('jquery', 'lws-base64')) as $dep )
					\wp_enqueue_script($dep);
				\wp_enqueue_script('lws-wre-pro-poolssettings', LWS_WOOREWARDS_PRO_JS . '/poolssettings.js', $deps, LWS_WOOREWARDS_PRO_VERSION, true);
				\wp_enqueue_style('lws-wre-pro-poolssettings', LWS_WOOREWARDS_PRO_CSS . '/poolssettings.css', array(), LWS_WOOREWARDS_PRO_VERSION);
				\wp_enqueue_style('lws-wre-pro-style', LWS_WOOREWARDS_PRO_CSS . '/style.css', array(), LWS_WOOREWARDS_PRO_VERSION);
			}
			else if( false !== strpos($hook, 'settings') && strpos($tab, 'woocommerce') !== false )
			{
				if( \class_exists('\WC_Frontend_Scripts') )
				{
					\WC_Frontend_Scripts::get_styles();
					if( isset($this->wcStyles) )
					{
						foreach($this->wcStyles as $style => $detail)
						{
							\wp_enqueue_style($style, $detail['src'], $detail['deps'], $detail['version'], $detail['media'], $detail['has_rtl']);
						}
					}
				}
			}
			else
			{
				\wp_enqueue_style('lws-wre-userspointsfilters', LWS_WOOREWARDS_PRO_CSS . '/userspointsfilters.css', array(), LWS_WOOREWARDS_PRO_VERSION);
			}

			\wp_enqueue_style('lws-wre-pool-content-edit', LWS_WOOREWARDS_PRO_CSS . '/poolcontentedit.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		}
	}

	function grabWCStyles($scripts)
	{
		if( !isset($this->wcStyles) )
			$this->wcStyles = $scripts;
		return $scripts;
	}

	function addBackButton($page)
	{
		$tabId = 'wr_loyalty';
		$options = array('poolfilter', 'lws-wr-pools-limit-count', 'lws-wr-pools-limit-page');
		if( isset($_REQUEST['tab']) && false !== strpos($_REQUEST['tab'], $tabId) )
		{
			$expected = $this->guessCurrentPool($tabId);
			if( !empty($expected) )
			{
				$attrs = array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty', 'tab'=>'');
				foreach( $options as $key )
				{
					if( isset($_COOKIE[$key.COOKIEHASH]) )
						$attrs[$key] = $_COOKIE[$key.COOKIEHASH];
				}
				$url = \esc_attr(\add_query_arg($attrs, \admin_url('admin.php')));
				$label = __("Back", LWS_WOOREWARDS_PRO_DOMAIN);
				$page['subtext'] = <<<EOT
<div class='lws-backbutton'>
	<a class='lws-backbutton-link' href='{$url}'>
		<div class='lws-backbutton-icon lws-icon-arrow-left'></div>
		<div class='lws-backbutton-text'>{$label}</div>
	</a>
</div>
EOT;
			}
			else
			{
				foreach( $options as $key )
				{
					if( isset($_GET[$key]) )
						\setcookie($key.COOKIEHASH, $_GET[$key], 0, COOKIEPATH, COOKIE_DOMAIN);
					else
						\setcookie($key.COOKIEHASH, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
				}
			}
		}
		return $page;
	}

	function addAchievementPage($pages)
	{
		$pageId = LWS_WOOREWARDS_PAGE.'.achievement';
		$title = __("Achievements", LWS_WOOREWARDS_PRO_DOMAIN);

		$filters = array();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/achievements.php';
		$editlist = \lws_editlist(
			\LWS\WOOREWARDS\PRO\Ui\Editlists\Achievements::SLUG,
			\LWS\WOOREWARDS\PRO\Ui\Editlists\Achievements::ROW_ID,
			new \LWS\WOOREWARDS\PRO\Ui\Editlists\Achievements(),
			\LWS\Adminpanel\EditList::MDA,
			$filters
		);

		$achievement = array(
			'id'       => $pageId,
			'title'    => __("WooRewards", LWS_WOOREWARDS_PRO_DOMAIN),
			'subtitle' => $title,
			'rights'   => 'manage_rewards',
			'tabs'     => array(
				'achievements' => array(
					'id'     => 'achievements',
					'title'  => $title,
					'groups' => array(
						'list' => array(
							'id'    => 'list',
							'title' => $title,
							'editlist' => $editlist,
							'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/badges-and-achievements/achievements/'),
							'function' => function(){
								\wp_enqueue_script('lws-wr-achievements', LWS_WOOREWARDS_PRO_JS.'/achievements.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION, true);
								\wp_enqueue_style('lws-wr-achievements', LWS_WOOREWARDS_PRO_CSS.'/achievements.css', array(), LWS_WOOREWARDS_PRO_VERSION);
							}
						)
					)
				)
			)
		);

		$pages = array_slice($pages, 0, 2) + array($achievement) + array_slice($pages, 2);
		return $pages;
	}

	function addSponsorship($page)
	{
		$page['tabs']['sponsorship'] = array(
			'title' => __("Sponsorship", LWS_WOOREWARDS_PRO_DOMAIN),
			'id' => 'sponsorship'
		);

		$page['tabs']['sponsorship']['groups']['sponsorship'] = array(
			'id' => 'sponsorship',
			'title' => __("Sponsorship Settings", LWS_WOOREWARDS_PRO_DOMAIN),
			'text' => sprintf(__("You can add a sponsorship form on a page with the %s shortcode, a gutenberg block or use the <b>WooRewards Sponsorship</b> widget.", LWS_WOOREWARDS_PRO_DOMAIN), "<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[lws_sponsorship]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>"),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/earning-methods/sponsorship/'),
			'fields' => array(
				'enable' => array(
					'id'    => 'lws_woorewards_event_enabled_sponsorship',
					'title' => __("Enable sponsorships", LWS_WOOREWARDS_PRO_DOMAIN),
					'type'  => 'box',
					'extra' => array('default' => 'on')
				),
				'allow' => array(
					'id'    => 'lws_woorewards_sponsorship_allow_unlogged',
					'title' => __("Allow unlogged users to sponsor", LWS_WOOREWARDS_PRO_DOMAIN),
					'type'  => 'box',
					'extra' => array(
						'advanced' => 'true',
						'default' => false,
						'help' => __("If you enable the following feature, unlogged users will have to enter their email address in addition to sponsored addresses.", LWS_WOOREWARDS_PRO_DOMAIN)
						)
				),
				'redirect' => array(
					'id'    => 'lws_woorewards_sponsorhip_user_notfound',
					'title' => __("Redirection if user not found", LWS_WOOREWARDS_PRO_DOMAIN),
					'type'  => 'lacselect',
					'extra' => array(
						'advanced' => 'true',
						'predefined' => 'page',
						'tooltips' => __("If an unlogged user tries to sponsor some friends, the system will try to find the appropriate user.", LWS_WOOREWARDS_PRO_DOMAIN)
							. '<br/>' . __("If no user is found, the user will be redirected to the page specified here, inviting them to register. If nothing is specified, they will stay on the same page", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
				'max'    => array(
					'id' => 'lws_wooreward_max_sponsorship_count',
					'title' => __("Max sponsorships per customer", LWS_WOOREWARDS_PRO_DOMAIN),
					'type' => 'text',
					'extra' => array(
						'pattern' => '\d+',
						'default' => '0',
						'help' => __("Set the maximum sponsorships allowed for users. No restriction on empty value or zero (0).", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
				'unconnected'    => array(
					'id' => 'lws_wooreward_sponsorship_nouser',
					'title' => __("Text displayed if user not connected", LWS_WOOREWARDS_PRO_DOMAIN),
					'type' => 'text',
					'extra' => array(
						'size' => '50',
						'placeholder' => __("Please log in if you want to sponsor your friends", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
				'success'    => array(
					'id' => 'lws_wooreward_sponsorship_success',
					'title' => __("Text displayed on success", LWS_WOOREWARDS_PRO_DOMAIN),
					'type' => 'text',
					'extra' => array(
						'size' => '50',
						'placeholder' => __("A mail has been sent to your friend about us.", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				)
			)
		);

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/sponsoredreward.php';
		$page['tabs']['sponsorship']['groups']['sponsored'] = array(
			'id' => 'sponsored',
			'title' => __("Sponsored Reward", LWS_WOOREWARDS_PRO_DOMAIN),
			'text' => __("Define the reward granted to the sponsored customer. Only works on new customers or customers who have never ordered.", LWS_WOOREWARDS_PRO_DOMAIN),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/earning-methods/sponsorship/'),
			'editlist' => \lws_editlist(
				'Sponsored',
				\LWS\WOOREWARDS\PRO\Ui\Editlists\SponsoredReward::ROW_ID,
				new \LWS\WOOREWARDS\PRO\Ui\Editlists\SponsoredReward(),
				\LWS\Adminpanel\EditList::MDA
			)->setPageDisplay(false)->setCssClass('sponsoredreward'),
			'function' => function(){
				\wp_enqueue_script('lws-wre-pro-sponsoredreward', LWS_WOOREWARDS_PRO_JS . '/sponsoredreward.js', array('lws-adminpanel-editlist'), LWS_WOOREWARDS_PRO_VERSION, true);
				\wp_enqueue_style('lws-wre-pro-sponsoredreward', LWS_WOOREWARDS_PRO_CSS . '/sponsoredreward.css', array('lws-editlist'), LWS_WOOREWARDS_PRO_VERSION);
			}
		);

		$page['tabs']['sponsorship']['groups']['style'] = array(
			'id' => 'sponsor_widget_style',
			'title' => __("Widget", LWS_WOOREWARDS_PRO_DOMAIN),
			'text' => __("In this Widget, customers can sponsor their friends.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>".
			sprintf(__("If you want, you can use the following shortcode instead : %s.", LWS_WOOREWARDS_PRO_DOMAIN),"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[lws_sponsorship]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>"),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/widgets-and-shortcodes/sponsorship/'),
			'fields' => array(
				array(
					'id' => 'lws_woorewards_sponsor_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'wr_sponsorship',
						'html'=> false,
						'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/sponsor.css',
						'subids' => array(
							'lws_woorewards_sponsor_widget_title'=>"WooRewards - Sponsor Widget - Title",
							'lws_woorewards_sponsor_widget_submit'=>"WooRewards - Sponsor Widget - Button",
							'lws_woorewards_sponsor_widget_placeholder'=>"WooRewards - Sponsor Widget - Placeholder",
						)
					)
				)
			)
		);

		return $page;
	}


	/* API */
	function addAPI($page)
	{
		$page['tabs']['api'] = array(
			'title' => __("API", LWS_WOOREWARDS_PRO_DOMAIN),
			'id' => 'api'
		);

		$restPrefix = \trailingslashit(\get_rest_url()) . \LWS\WOOREWARDS\PRO\Core\Rest::getNamespace();
		$restPrefix = "<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>{$restPrefix}</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>";
		$page['tabs']['api']['groups'] = array(
			'api' => array(
				'id'     => 'api',
				'title'  => __("REST API", LWS_WOOREWARDS_PRO_DOMAIN),
				'text'   => sprintf(__("Define WooRewards REST API settings. API endpoint will be %s", LWS_WOOREWARDS_PRO_DOMAIN), $restPrefix),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/rest-api/'),
				'fields' => array(
					'enabled' => array(
						'id'    => 'lws_woorewards_rest_api_enabled',
						'title' => __("Enable REST API", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'box',
					),
					'wc_auth' => array(
						'id'    => 'lws_woorewards_rest_api_wc_auth',
						'title' => __("Allows authentification by WooCommerce REST API", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'box',
						'extra' => array(
							'default' => 'on',
						)
					),
				)
			),
			'users' => array(
				'id'     => 'users',
				'title'  => __("User Permissions", LWS_WOOREWARDS_PRO_DOMAIN),
				'text'   => __("Define the website users that can access the different features of the API", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/rest-api/'),
				'fields' => array(
					'info' => array(
						'id'    => 'lws_woorewards_rest_api_user_info',
						'title' => __("Users allowed to read general information", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'lacchecklist',
						'extra' => array(
							'predefined' => 'user',
							'tooltips' => __("The checked users can get loyalty system list.", LWS_WOOREWARDS_PRO_DOMAIN),
						)
					),
					'read' => array(
						'id'    => 'lws_woorewards_rest_api_user_read',
						'title' => __("Users allowed to read user information", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'lacchecklist',
						'extra' => array(
							'predefined' => 'user',
							'tooltips' => __("The checked users can get other users point amounts and history.", LWS_WOOREWARDS_PRO_DOMAIN),
						)
					),
					'write' => array(
						'id'    => 'lws_woorewards_rest_api_user_write',
						'title' => __("Users allowed to change user information", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'lacchecklist',
						'extra' => array(
							'predefined' => 'user',
							'tooltips' => __("The checked users can add points to other users.", LWS_WOOREWARDS_PRO_DOMAIN),
						)
					),
				)
			),
		);

		return $page;
	}

	public function addWooCommerce($page)
	{
		// add tab as second
		$page['tabs'] = array_merge(
			array(
				'settings' => array(),
				'woocommerce' => array(
					'title' => __("WooCommerce", LWS_WOOREWARDS_PRO_DOMAIN),
					'id' => 'woocommerce'
				)
			),
			$page['tabs']
		);

		$page['tabs']['woocommerce']['groups'] = array(
			'woocommerce' => array(
				'id' => 'woocommerce',
				'title' => __("WooCommerce Settings", LWS_WOOREWARDS_PRO_DOMAIN),
				'text' => __("Configure features added to WooCommerce.", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/earning-methods/anniversarys/'),
				'fields' => array(
					'birthday_checkout' => array(
						'id' => 'lws_woorewards_registration_birthday_field',
						'title' => __("Display a birthday field in the 'checkout' page when the user register at the same time.", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'box'
					),
					'birthday_register' => array(
						'id' => 'lws_woorewards_myaccount_register_birthday_field',
						'title' => __("Display a birthday field in the 'my account register' page.", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'box'
					),
					'birthday_detail' => array(
						'id' => 'lws_woorewards_myaccount_detail_birthday_field',
						'title' => __("Display a birthday field in the 'my account -> details' page.", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'box'
					)
				)
			),
			'myaccountlrview' => array(
				'id' => 'myaccountlarview',
				'title' => __("My Account - Loyalty", LWS_WOOREWARDS_PRO_DOMAIN),
				'text' => __("Show to the customer all loyalty and rewards information in a dedicated 'Loyalty and Rewards' Tab inside WooCommerce's My Account.", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/woocommerce-integration/my-account-loyalty/'),
				'fields' => array(
					'endpont_loyalty' => array(
						'id' => 'lws_woorewards_wc_my_account_endpont_loyalty',
						'title' => __("Display the Loyalty and Rewards tab.", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'box',
						'extra' => array('default' => 'on'),
					),
					array(
						'id' => 'lws_woorewards_wc_my_account_lar_label',
						'title' => __("Loyalty and Rewards Tab Title"),
						'type' => 'text',
						'extra' => array(
							'size' => '50',
							'placeholder' => __('Loyalty and Rewards', LWS_WOOREWARDS_PRO_DOMAIN)
						)
					),
					'expanded_display' => array(
						'id' => 'lws_woorewards_wc_myaccount_expanded',
						'title' => __("Expanded display", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'box',
						'extra' => array(
							'help' => __("Disables the accordion feature on the endpoint and expands all sections", LWS_WOOREWARDS_PRO_DOMAIN),
						),
					),
					array(
						'id' => 'lws_woorewards_wc_my_account_endpoint_history',
						'title' => __("Show Points History to customers.", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'box',
						'extra' => array('default' => 'on'),
					),
					array(
						'id' => 'lws_wre_myaccount_lar_view',
						'type' => 'themer',
						'extra' => array(
							'template' => 'wc_loyalty_and_rewards',
							'css' => LWS_WOOREWARDS_PRO_URL . '/css/loyalty-and-rewards.css',
							'prefix' => '--wr-lar-'
						)
					)
				)
			),
			'myaccountbadgesview' => array(
				'id' => 'myaccountbadgesview',
				'title' => __("My Account - Badges", LWS_WOOREWARDS_PRO_DOMAIN),
				'text' => __("Show to the customer all badges he owns in a 'Badges' Tab inside WooCommerce's My Account.", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/woocommerce-integration/my-account-badges/'),
				'fields' => array(
					'endpoint_badges' => array(
						'id' => 'lws_woorewards_wc_my_account_endpoint_badges',
						'title' => __("Display the Badges tab.", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'box',
						'extra' => array('default' => 'on'),
					),
					array(
						'id' => 'lws_woorewards_wc_my_account_badges_label',
						'title' => __("Badges Tab Title"),
						'type' => 'text',
						'extra' => array(
							'size' => '50',
							'placeholder' => __('My Badges', LWS_WOOREWARDS_PRO_DOMAIN)
						)
					),
					array(
						'id' => 'lws_wre_myaccount_badges_view',
						'type' => 'themer',
						'extra' => array(
							'template' => 'wc_badges_endpoint',
							'css' => LWS_WOOREWARDS_PRO_URL . '/css/badges-endpoint.css',
							'prefix' => '--wr-badges-'
						)
					)
				)
			),
			'myaccountachievementsview' => array(
				'id' => 'myaccountachievementsview',
				'title' => __("My Account - Achievements", LWS_WOOREWARDS_PRO_DOMAIN),
				'text' => __("Show to the customer all possible achievements in a 'Achievements' Tab inside WooCommerce's My Account.", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/woocommerce-integration/my-account-achievements/'),
				'fields' => array(
					'endpoint_achievements' => array(
						'id' => 'lws_woorewards_wc_my_account_endpoint_achievements',
						'title' => __("Display the Achievements tab.", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'box',
						'extra' => array('default' => 'on'),
					),
					array(
						'id' => 'lws_woorewards_wc_my_account_achievements_label',
						'title' => __("Achievements Tab Title"),
						'type' => 'text',
						'extra' => array(
							'size' => '50',
							'placeholder' => __('Achievements', LWS_WOOREWARDS_PRO_DOMAIN)
						)
					),
					array(
						'id' => 'lws_wre_myaccount_achievements_view',
						'type' => 'themer',
						'extra' => array(
							'template' => 'wc_achievements_endpoint',
							'css' => LWS_WOOREWARDS_PRO_URL . '/css/achievements-endpoint.css',
							'prefix' => '--wr-achievements-'
						)
					)
				)
			),
			'cartcouponsview' => array(
				'id' => 'cartcouponsview',
				'title' => __("Cart Coupons.", LWS_WOOREWARDS_PRO_DOMAIN),
				'text' => __("Show to the customer his available coupons. That block stay hidden if customer doesn't have coupons.", LWS_WOOREWARDS_PRO_DOMAIN).'<br/>'
				.sprintf(__("If you want, you can use the following shortcode instead : %s.", LWS_WOOREWARDS_PRO_DOMAIN),"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[wr_cart_coupons_view]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>"),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/woocommerce-integration/cart-coupons/'),
				'fields' => array(
					array(
						'id'    => 'lws_woorewards_apply_coupon_by_reload',
						'title' => __("Reload page to apply coupon", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'box',
						'extra' => array(
							'tooltips' => __("Using a custom cart widget can prevent the default javascript behavior. In that case, check that option to force a page reload when customer apply a coupon.", LWS_WOOREWARDS_PRO_DOMAIN),
						),
					),
					array(
						'id' => 'lws_woorewards_cart_collaterals_coupons', // legacy id: coupon view position
						'title' => __("Location", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'select',
						'extra' => array(
							'default' => 'not_displayed',
							'notnull' => true,
							'options' => array(
								'not_displayed' => __("Not displayed at all", LWS_WOOREWARDS_PRO_DOMAIN),
								'middle_of_cart' => __("Between products and totals", LWS_WOOREWARDS_PRO_DOMAIN),
								'cart_collaterals' => __("Left of cart totals", LWS_WOOREWARDS_PRO_DOMAIN),
								'on' => __("Bottom of the cart page", LWS_WOOREWARDS_PRO_DOMAIN),
							)
						)
					),
					array(
						'id' => 'lws_wre_cart_coupons_view',
						'type' => 'stygen',
						'extra' => array(
							'purpose' => 'filter',
							'template' => 'cartcouponsview',
							'html' => false,
							'css' => LWS_WOOREWARDS_PRO_URL . '/css/templates/cartcouponsview.css',
							'subids' => array(
								'lws_woorewards_title_cart_coupons_view'=>"WooRewards - Coupons - Title",
								'lws_woorewards_cart_coupons_button'=>"WooRewards - Coupons - Button",
							)
						)
					),
				)
			),
			'cartpointspreview' => array(
				'id' => 'cartpointspreview',
				'title' => __("Cart Page Preview", LWS_WOOREWARDS_PRO_DOMAIN),
				'text' => __("Show points that a customer could earn with his current cart. That block stay hidden if customer does not earn points.", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/woocommerce-integration/cart-points-preview/'),
				'fields' => array(
					array(
						'id' => 'lws_woorewards_cart_potential_position',
						'title' => __("Location", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'select',
						'extra' => array(
							'default' => 'not_displayed',
							'notnull' => true,
							'options' => array(
								'not_displayed' => __("Not displayed at all", LWS_WOOREWARDS_PRO_DOMAIN),
								'middle_of_cart' => __("Between products and totals", LWS_WOOREWARDS_PRO_DOMAIN),
								'cart_collaterals' => __("Left of cart totals", LWS_WOOREWARDS_PRO_DOMAIN),
								'bottom_of_cart' => __("Bottom of the cart page", LWS_WOOREWARDS_PRO_DOMAIN),
							)
						)
					),
					array(
						'id' => 'lws_woorewards_cart_potential_pool',
						'title' => __("Loyalty systems", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'lacchecklist',
						'extra' => array(
							'ajax' => 'lws_woorewards_pool_list',
							'tooltips' => __("If you select several systems, they will be displayed separately, one after the other.", LWS_WOOREWARDS_PRO_DOMAIN),
						)
					),
					array(
						'id' => 'lws_wre_cart_points_preview',
						'type' => 'stygen',
						'extra' => array(
							'purpose' => 'filter',
							'template' => 'cartpointspreview',
							'html' => false,
							'css' => LWS_WOOREWARDS_PRO_URL . '/css/templates/cartpointspreview.css',
							'subids' => array(
								'lws_woorewards_title_cpp'=>"WooRewards - Cart Point Preview - Title",
							)
						)
					)
				)
			),
			'productpointspreview' => array(
				'id' => 'productpointspreview',
				'title' => __("Product Page Preview", LWS_WOOREWARDS_PRO_DOMAIN),
				'text' => __("Show points that a customer could earn purchasing a given product. That block stay hidden if the product produces no points.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>"
				.sprintf(__("If you want, you can use the following shortcode instead : %s.", LWS_WOOREWARDS_PRO_DOMAIN),"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[wr_product_points_preview]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>"),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/woocommerce-integration/products-point-preview/'),
				'fields' => array(
					array(
						'id' => 'lws_woorewards_product_potential_position',
						'title' => __("Location", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'select',
						'extra' => array(
							'default' => 'not_displayed',
							'notnull' => true,
							'options' => array(
								'not_displayed' => __("Not displayed at all", LWS_WOOREWARDS_PRO_DOMAIN),
								'before_summary' => __("Before product summary", LWS_WOOREWARDS_PRO_DOMAIN),
								'inside_summary' => __("Inside product summary", LWS_WOOREWARDS_PRO_DOMAIN),
								'after_summary' => __("After product summary", LWS_WOOREWARDS_PRO_DOMAIN),
							)
						)
					),
					array(
						'id' => 'lws_woorewards_product_potential_pool',
						'title' => __("Loyalty systems", LWS_WOOREWARDS_PRO_DOMAIN),
						'type' => 'lacchecklist',
						'extra' => array(
							'ajax' => 'lws_woorewards_pool_list',
							'tooltips' => __("If you select several systems, they will be displayed separately, one after the other.", LWS_WOOREWARDS_PRO_DOMAIN),
						)
					),
					array(
						'id' => 'lws_wre_product_points_preview',
						'type' => 'stygen',
						'extra' => array(
							'purpose' => 'filter',
							'template' => 'productpointspreview',
							'html' => false,
							'css' => LWS_WOOREWARDS_PRO_URL . '/css/templates/productpointspreview.css',
							'subids' => array(
								'lws_woorewards_label_ppp'=>"WooRewards - Product Points Preview - Title",
							)
						)
					)
				)
			)
		);

		return $page;
	}

	function addSettings($page)
	{
		$page['tabs']['settings']['groups']['settings']['fields']['badge-enable'] = array(
			'id'    => 'lws_woorewards_manage_badge_enable',
			'title' => __("Enable badge/achievement management", LWS_WOOREWARDS_PRO_DOMAIN),
			'type'  => 'box',
			'extra' => array(
				'default' => 'on',
				'help' => __("Enable/disable badge menu, rewards, earning methods and achievement system.", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		);

		$page['tabs']['settings']['groups']['pages'] = array(
			'id'     => 'pages',
			'title'  => __("Pages", LWS_WOOREWARDS_PRO_DOMAIN),
			'text'   => __("Define pages overrided to display specific content.", LWS_WOOREWARDS_PRO_DOMAIN),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/other-settings/general-settings/'),
			'fields' => array(
				'loyalty' => array(
					'id'    => 'lws_woorewards_endpoint_content_for_page_lws_woorewards',
					'title' => __("Loyalty and Rewards summary page", LWS_WOOREWARDS_PRO_DOMAIN),
					'type'  => 'lacselect',
					'extra' => array(
						'predefined' => 'page',
						'tooltips' => __("List customer's rewards and available loyalty systems.", LWS_WOOREWARDS_PRO_DOMAIN)
							. (!empty($pageUrl = \get_permalink(\get_option('lws_woorewards_endpoint_content_for_page_lws_woorewards'))) ? sprintf(" <a href='%s' target='_blank'>(%s)</a>", \esc_attr($pageUrl), __("view", LWS_WOOREWARDS_PRO_DOMAIN)) : '')
							. '<br/>' . __("If WooCommerce is activated, that content is already added as a <i>Loyalty and Rewards</i> tab in the customer my-account frontend page.", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				)
			)
		);


		$page['tabs']['settings']['groups']['claim'] = array(
			'id'     => 'claim',
			'title'  => __("Reward Popup", LWS_WOOREWARDS_PRO_DOMAIN),
			'text'   => __("Defines the popup options when a user unlocks a reward.", LWS_WOOREWARDS_PRO_DOMAIN),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/other-settings/general-settings/'),
			'fields' => array(
				'claim' => array(
					'id'    => 'lws_woorewards_reward_claim_page',
					'title' => __("Redirection page after a reward is unlocked", LWS_WOOREWARDS_PRO_DOMAIN),
					'type'  => 'lacselect',
					'extra' => array(
						'predefined' => 'page',
						'tooltips' => __("When a customer clicks a reward redeem button, he will be redirected to that page.", LWS_WOOREWARDS_PRO_DOMAIN)
							. '<br/>' . __("If WooCommerce is activated, the default is the <b>Loyalty and Rewards</b> tab in the customer my-account frontend page. Otherwise, it is your home page", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
				array(
					'id'    => 'lws_wr_rewardclaim_notice_with_rest',
					'title' => __("Show remaining available rewards after a reward is unlocked", LWS_WOOREWARDS_PRO_DOMAIN),
					'type'  => 'box',
					'extra' => array(
						'default' => 'on',
						'tooltips' => __("When a customer clicks a reward redeem button, he will be redirected to a page with an unlock feedback.", LWS_WOOREWARDS_PRO_DOMAIN)
							. '<br/>' . __("That popup includes the rest of available rewards.", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
				'popup' => array(
					'id' => 'lws_woorewards_lws_reward_claim',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'lws_reward_claim',
						'html'=> false,
						'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/rewardclaim.css',
						'subids' => array(
							'lws_woorewards_wc_reward_claim_title'=>"WooRewards - Reward Claim Popup - Title",
							'lws_woorewards_wc_reward_claim_header'=>"WooRewards - Reward Claim Popup - Header",
							'lws_woorewards_wc_reward_claim_stitle'=>"WooRewards - Reward Claim Popup - Subtitle",
						),
						'help' =>  __("This popup will show when customers unlock a new reward.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>"
						. __("It can show only the reward unlocked or also the rewards that can still be unlocked .", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
			)
		);

		return $page;
	}

	function addWidgets($page)
	{
		$tab = 'sty_widgets';
		$showpoints = $page['tabs'][$tab]['groups']['showpoints'];
		$page['tabs'][$tab]['groups'] = array();

		$page['tabs'][$tab]['groups']['loyalty'] = array(
			'id' => 'loyalty',
			'title' => __("Loyalty Systems", LWS_WOOREWARDS_PRO_DOMAIN),
			'text' => __("You'll find here all the shortcodes and widgets relative to loyalty systems.", LWS_WOOREWARDS_PRO_DOMAIN),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/widgets-and-shortcodes/'),
			'fields' => array(
				'spunconnected' => $showpoints['fields']['spunconnected'],
				'showpoints' => $showpoints['fields']['showpoints'],
				'clunconnected'    => array(
					'id' => 'lws_wooreward_wc_coupons_nouser',
					'title' => __("Text displayed if user not connected", LWS_WOOREWARDS_PRO_DOMAIN),
					'type' => 'text',
					'extra' => array(
						'size' => '50',
						'placeholder' => __("Please log in to see the coupons you have", LWS_WOOREWARDS_PRO_DOMAIN),
						'help' =>  __("In this Widget, customers can see the WooCommerce coupons they own.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>"
						.sprintf(__("If you want, you can use the following shortcode instead : %s.", LWS_WOOREWARDS_PRO_DOMAIN),"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[wr_shop_coupons]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>")
					)
				),
				'ifemptycoupon' => array(
					'id' => 'lws_wooreward_wc_coupons_empty',
					'title' => __("Text displayed if no coupon available", LWS_WOOREWARDS_PRO_DOMAIN),
					'type' => 'text',
					'extra' => array(
						'size' => '50',
						'placeholder' => __("No coupon available", LWS_WOOREWARDS_PRO_DOMAIN),
					)
				),
				'couponslist' => array(
					'id' => 'lws_woorewards_wc_coupons_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'wc_shop_coupon',
						'html'=> false,
						'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/coupons.css',
						'subids' => array('lws_woorewards_wc_coupons_template_head'=>"WooRewards - Coupons Widget - Header")
					)
				),
				'stdusegrid' => array(
					'id'    => 'lws_woorewards_rewards_use_grid',
					'title' => __("Use grid display instead of table display", LWS_WOOREWARDS_PRO_DOMAIN),
					'type'  => 'box',
					'extra' => array(
						'default' => 'on',
						'help' => __("Until WooRewards 3.4, this widget used html tables to display rewards.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>"
						.__("If you've set up the widget before that version, checking that box will force you to style it again", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
				'stdrewards' => array(
					'id' => 'lws_woorewards_rewards_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'rewards_template',
						'html' => false,
						'css' => LWS_WOOREWARDS_PRO_URL.'/css/templates/'.(empty(\get_option('lws_woorewards_rewards_use_grid', 'on')) ? 'rewards.css' : 'gridrewards.css'),
						'help' => __("In this Widget, customers can see the Rewards they can unlock in a Standard Loyalty System.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>"
						. __("If you want to use a shortcode, you can find it in the Loyalty system edition.", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
				'levrewards' => array(
					'id' => 'lws_woorewards_loyalties_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'loyalties_template',
						'html'=> false,
						'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/loyalties.css',
						'help' => __("In this Widget, customers can see the Rewards they can win in a Levelling Loyalty System.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>"
						. __("If you want to use a shortcode, you can find it in the Loyalty system edition.", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
				'events' => array(
					'id' => 'lws_woorewards_events_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'events_template',
						'html'=> false,
						'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/events.css',
						'subids' => array(
							'lws_woorewards_events_widget_message'=>"WooRewards - Earning methods - Header",
							'lws_woorewards_events_widget_text'=>"WooRewards - Earning methods - Description",
						),
						'help' => __("In this Widget, customers can see what they need to do in order to earn points", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>"
						. __("If you want to use a shortcode, you can find it in the Loyalty system edition.", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
			)
		);

		$page['tabs'][$tab]['groups']['loyalty']['fields']['showpoints']['extra']['help'] = $showpoints['text'];

		$page['tabs'][$tab]['groups']['badach'] = array(
			'id' => 'badach',
			'title' => __("Badges & Achievements", LWS_WOOREWARDS_PRO_DOMAIN),
			'text' => __("You'll find here all the shortcodes and widgets relative to badges and achievements.", LWS_WOOREWARDS_PRO_DOMAIN),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/widgets-and-shortcodes/'),
			'fields' => array(
				'badges' => array(
					'id' => 'lws_woorewards_badges_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'badges_template',
						'html'=> false,
						'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/badges.css',
						'subids' => array(
							'lws_woorewards_badges_widget_message'=>"WooRewards - Badges - Title",
						),
						'help' => __("In this Widget, customers can see the badges available and the ones they own", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>".
						sprintf(__("If you want, you can use the following shortcode instead : %s.", LWS_WOOREWARDS_PRO_DOMAIN),"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[lws_badges]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>")
					)
				),
				'achievements' => array(
					'id' => 'lws_woorewards_achievements_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'achievements_template',
						'html'=> false,
						'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/achievements.css',
						'subids' => array(
							'lws_woorewards_achievements_widget_message'=>"WooRewards - Achievements - Title",
						),
						'help' => __("In this Widget, customers can see the achievements and their progress", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>".
						sprintf(__("If you want, you can use the following shortcode instead : %s.", LWS_WOOREWARDS_PRO_DOMAIN),"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[lws_achievements]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>")
					)
				),

			)
		);

		$page['tabs'][$tab]['groups']['referral'] = array(
			'id' => 'referral',
			'title' => __("Referral", LWS_WOOREWARDS_PRO_DOMAIN),
			'text' => __("In this Widget, customers get referral link they can share.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>".
			sprintf(__("If you want, you can use the following shortcode instead : %s.", LWS_WOOREWARDS_PRO_DOMAIN),"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[lws_referral]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>"),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/widgets-and-shortcodes/referral/'),
			'fields' => array(
				array(
					'id' => 'lws_woorewards_referral_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'wr_referral',
						'html'=> false,
						'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/referral.css',
						'subids' => array(
							'lws_woorewards_referral_widget_message'=>"WooRewards - Referral Widget - Header",
						)
					)
				)
			)
		);

		$page['tabs'][$tab]['groups']['social'] = array(
			'id' => 'social_share',
			'title' => __("Social Media", LWS_WOOREWARDS_PRO_DOMAIN),
			'text' => __("With this Widget, customers can share a page link on social media.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>".
			sprintf(__("If you want, you can use the following shortcode instead : %s.", LWS_WOOREWARDS_PRO_DOMAIN),"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[lws_social_share]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>")."<br/>".
			__("Use the url option to specify a custom shared url [lws_social_share url='myurl']", LWS_WOOREWARDS_PRO_DOMAIN),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/widgets-and-shortcodes/social-medias-sharing/'),
			'fields' => array(
				array(
					'id' => 'lws_woorewards_smshare_socialmedias',
					'title' => __("Social Media", LWS_WOOREWARDS_PRO_DOMAIN),
					'type' => 'lacchecklist',
					'extra' => array(
						'source'  => \LWS\WOOREWARDS\PRO\Core\Socials::instance()->asDataSource(),
						'default' => \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getSupportedNetworks(),
						'help' => __("Select the social medias for which you want share buttons. Save your settings to see them appear on the styling tool", LWS_WOOREWARDS_PRO_DOMAIN),
					)
				),
				'smdispunc' => array(
					'id'    => 'lws_woorewards_social_display_unconnected',
					'title' => __("Display Buttons if users not connected", LWS_WOOREWARDS_PRO_DOMAIN),
					'type'  => 'box',
					'extra' => array(
						'default' => ''
					)
				),
				'smdisconnected'    => array(
					'id' => 'lws_woorewards_social_text_unconnected',
					'title' => __("Text displayed if user not connected", LWS_WOOREWARDS_PRO_DOMAIN),
					'type' => 'text',
					'extra' => array(
						'size' => '50',
						'placeholder' => __("Only logged in users can earn points for sharing", LWS_WOOREWARDS_PRO_DOMAIN),
						'wpml' => "WooRewards - Social Share - Not connected",
					)
				),
				'smpopup' => array(
					'id'    => 'lws_woorewards_social_share_popup',
					'title' => __("Open share dialog as popup", LWS_WOOREWARDS_PRO_DOMAIN),
					'type'  => 'box',
					'extra' => array(
						'default' => '',
						'tooltips' => __("Default behavior opens share dialog in a new tab.", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				),
				array(
					'id' => 'lws_woorewards_social_share_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'wr_social_share',
						'html'=> false,
						'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/social-share.css',
						'subids' => array(
							'lws_woorewards_social_share_widget_message'=>"WooRewards - Social Share - Title",
							'lws_woorewards_social_share_widget_text'=>"WooRewards - Social Share - Description",
						)
					)
				)
			)
		);

		return $page;
	}

	function getLoyaltyTab($tab=false)
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/pools.php';
		$tabId = 'wr_loyalty';
		$title = __("Loyalty Systems", LWS_WOOREWARDS_PRO_DOMAIN);

		$links = array('' => array('poolfilter' => ''));
		$labels = array('' => _x("All", "Loyalty system filter", LWS_WOOREWARDS_PRO_DOMAIN));
		foreach( \LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::statusList() as $k => $status )
		{
			$links[$k] = array('poolfilter' => $k);
			$labels[$k] = $status;
		}
		$filter = new \LWS\Adminpanel\EditList\FilterSimpleLinks($links, array(), false, $labels);

		$loyalty = array(
			'title'  => $title,
			'rights'   => 'manage_rewards',
			'id'       => LWS_WOOREWARDS_PAGE.'.loyalty',
			'tabs'   => array(
				$tabId  => array(
					'title'  => $title,
					'id'     => $tabId,
					'hidden' => true,
					'tabs'    => array(
						array(
							'title'  => $title,
							'id'     => 'systems',
							'hidden' => true,
							'groups' => array(
								'systems' => array(
									'id'       => 'systems',
									'title'    => __("Loyalty Systems", LWS_WOOREWARDS_PRO_DOMAIN),
									'text'     => __("Manage several loyalty systems working side by side.", LWS_WOOREWARDS_PRO_DOMAIN)
									."<br/>".__("When adding a new system, you'll have some choices to make:", LWS_WOOREWARDS_PRO_DOMAIN)
									."<br/><ul><li>".__("<b>Title</b> : Your loyalty system's title.", LWS_WOOREWARDS_PRO_DOMAIN)."</li>"
									."<li>".__("<b>Behavior</b> : Defines how loyalty points will behave :", LWS_WOOREWARDS_PRO_DOMAIN)."</li><ul>"
									."<li>".__("<b>Standard</b> : Points are consumed everytime a reward is generated", LWS_WOOREWARDS_PRO_DOMAIN)."</li>"
									."<li>".__("<b>Levelling</b> : Points are never consumed. Customers earn points to reach different levels", LWS_WOOREWARDS_PRO_DOMAIN)."</li></ul>"
									."<li>".__("<b>Type</b> (Permanent | Event) : Defines if your loyalty system is permanent or limited in time.", LWS_WOOREWARDS_PRO_DOMAIN)."</li>"
									."<li>".__("<b>Points pool</b> (Own pool | Shared pool) : Defines if the loyalty system uses its own points or if it shares points with another loyalty system.", LWS_WOOREWARDS_PRO_DOMAIN)."</li></ul>"
									,
									'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/loyalty-systems/'),
									'editlist' => \lws_editlist(
										\LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::SLUG,
										\LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::ROW_ID,
										new \LWS\WOOREWARDS\PRO\Ui\Editlists\Pools(),
										\LWS\Adminpanel\EditList::ALL,
										$filter
									)
								)
							)
						)
					)
				)
			)
		);

		$expected = $this->guessCurrentPool($tabId);
		if( !empty($expected) )
		{
			// build only the required pool page
			$pool = \LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::getCollection()->find($expected);
			if( !empty($pool) )
			{
				$subtab = $this->poolPrefix . $pool->getName();
				$loyalty['tabs'][$tabId]['tabs'][$subtab] = array(
					'title'  => $pool->getOption('display_title'),
					'id'     => $subtab,
					'hidden' => true,
					'groups' => $this->getLoyaltyGroups($pool),
					'delayedFunction' => function()use($pool){
						echo "<div style='width:50%;'>";
						\do_action('wpml_show_package_language_ui', $pool->getPackageWPML());
						echo "</div>";
					}
				);
			}
		}

		return $loyalty;
	}

	/** @return pool name or false. */
	protected function guessCurrentPool($tabId)
	{
		if( !isset($this->guess) )
		{
			$this->guess = false;
			$tab = isset($_REQUEST['tab']) ? trim($_REQUEST['tab']) : '';
			$tabPrefix = $tabId .'.'. $this->poolPrefix;
			if( strpos($tab, $tabPrefix) === 0 )
			{
				$this->guess = substr($tab, strlen($tabPrefix));
			}
			else if( defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] == 'lws_adminpanel_editlist' )
			{
				$editlist = isset($_REQUEST['id']) ? trim($_REQUEST['id']) : '';
				foreach( array('UnlockableList-', 'EventList-') as $prefix )
				{
					if( 0 === strpos($editlist, $prefix) )
					{
						$this->guess = intval(substr($editlist, strlen($prefix)));
						break;
					}
				}
			}
		}
		return $this->guess;
	}

	protected function getLoyaltyGroups($pool)
	{
		if( empty($pool) )
		{
			return array('error' => array(
				'title' => __("Loading failure", LWS_WOOREWARDS_PRO_DOMAIN),
				'text'  => __("Seems the loyalty system does not exists. Try re-activate this plugin. If that problem persists, contact your administrator.", LWS_WOOREWARDS_PRO_DOMAIN)
			));
		}

		$spendingFields = array(
			array(
				'id' => 'lws_wr_levels_title',
				'title' => '',
				'type' => 'hidden',
				'extra' => array('value' => __("Levels management", LWS_WOOREWARDS_PRO_DOMAIN))
			),
			array(
				'id' => 'lws_wr_levels_help',
				'title' => '',
				'type' => 'hidden',
				'extra' => array('value' => __("Here you can manage the levels of your customers", LWS_WOOREWARDS_PRO_DOMAIN))
			)
		);

		$shortcodeevents = sprintf(
			"<br/>%s <span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[wr_events pool='%s']</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>",
			__("Show these methods to your customers with the following shortcode:", LWS_WOOREWARDS_PRO_DOMAIN),
			\esc_attr($pool->getName())
		);

		$shortcoderewards = sprintf(
			"<br/>%s <span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[wr_show_rewards pool='%s']</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>",
			__("Show the rewards to your customers with the following shortcode:", LWS_WOOREWARDS_PRO_DOMAIN),
			\esc_attr($pool->getName())
		);

		$group = array(
			'general'    => array(
				'id'       => 'wr_loyalty_general',
				'title'    => __("General Settings", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/loyalty-systems/'),
				'fields'   => \apply_filters('lws_woorewards_admin_pool_general_settings', array(), $pool)
			),
			'pts_disp'   => array(
				'id'       => 'wr_pts_disp',
				'title'    => __("Points display", LWS_WOOREWARDS_PRO_DOMAIN),
				'text' 	   => __("You can change how points are displayed to customers. You can either set a text or an image", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>".
				__("<strong>Warning :</strong> If you use multiple languages, labels won't be translated with po/mo files. However, it's possible with WPML.", LWS_WOOREWARDS_PRO_DOMAIN),
				'fields'   => array(
					'point_name' => array(
						'id'    => self::POOL_OPTION_PREFIX . 'point_name_singular',
						'title' => __("Point display name", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'text',
						'extra' => array(
							'value' => $pool->getOption('point_name_singular'),
							'placeholder' => __("Point", LWS_WOOREWARDS_PRO_DOMAIN),//\LWS_WooRewards::getPointSymbol(1),
							'tooltips' => __("Point unit shown to the user.", LWS_WOOREWARDS_PRO_DOMAIN),
						)
					),
					'points_name' => array(
						'id'    => self::POOL_OPTION_PREFIX . 'point_name_plural',
						'title' => __("Points display name (plural)", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'text',
						'extra' => array(
							'value' => $pool->getOption('point_name_plural'),
							'placeholder' => __("Points", LWS_WOOREWARDS_PRO_DOMAIN),//\LWS_WooRewards::getPointSymbol(2),
							'tooltips' => __("(Optional) The singular form is used if plural is not set.", LWS_WOOREWARDS_PRO_DOMAIN),
							'advanced' => true,
						)
					),
					'point_sym' => array(
						'id'    => self::POOL_OPTION_PREFIX . 'symbol',
						'title' => __("Point symbol", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'media',
						'extra' => array(
							'value' => $pool->getOption('symbol'),
							'tooltips' => __("If you set an image, it will replace the above labels.", LWS_WOOREWARDS_PRO_DOMAIN),
						)
					),
					'point_format' => array(
						'id'    => self::POOL_OPTION_PREFIX . 'point_format',
						'title' => __("Point name position", LWS_WOOREWARDS_PRO_DOMAIN),
						'type'  => 'lacselect',
						'extra' => array(
							'value' => $pool->getOption('point_format'),
							'mode' => 'select',
							'source' => array(
								array('value' => '%1$s %2$s', 'label' => _x("Right", 'Point name position', LWS_WOOREWARDS_PRO_DOMAIN)),
								array('value' => '%2$s %1$s', 'label' => _x("Left", 'Point name position', LWS_WOOREWARDS_PRO_DOMAIN)),
							),
							'advanced' => true,
						)
					),
				),
			),
			'earning'    => array(
				'id'       => 'wr_loyalty_earning',
				'title'    => __("Earning points", LWS_WOOREWARDS_PRO_DOMAIN),
				'text'     => __("Here you can manage how your customers earn loyalty points", LWS_WOOREWARDS_PRO_DOMAIN).$shortcodeevents,
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/earning-methods/'),
				'editlist' => \lws_editlist(
					'EventList-'.$pool->getId(),
					\LWS\WOOREWARDS\Ui\Editlists\EventList::ROW_ID,
					new \LWS\WOOREWARDS\Ui\Editlists\EventList($pool),
					\LWS\Adminpanel\EditList::MDA
				)->setPageDisplay(false)->setCssClass('eventlist'),
			),
			'spending'   => array(
				'id'       => 'lws_wr_spending_system',
				'title'    => __("Rewards", LWS_WOOREWARDS_PRO_DOMAIN),
				'text'     => __("Here you can manage the rewards for your customers", LWS_WOOREWARDS_PRO_DOMAIN).$shortcoderewards,
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/rewards/'),
				'editlist' => \lws_editlist(
					'UnlockableList-'.$pool->getId(),
					\LWS\WOOREWARDS\Ui\Editlists\UnlockableList::ROW_ID,
					new \LWS\WOOREWARDS\Ui\Editlists\UnlockableList($pool),
					\LWS\Adminpanel\EditList::MDA
				)->setPageDisplay(false)->setGroupBy($this->getGroupByLevelSettings($pool))->setCssClass('unlockablelist'),
				'fields' => $spendingFields
			)
		);

		$group['earning']['fields']['lifetime'] = array(
			'id'    => self::POOL_OPTION_PREFIX . 'point_timeout',
			'title' => __("Points expiration for inactivity", LWS_WOOREWARDS_PRO_DOMAIN),
			'type'  => 'woorewards_duration',
			'extra' => array(
				'value' => $pool->getOption('point_timeout')->toString(),
				'advanced' => true,
				'help' => __("Defines if customers loose their points after an inactivity period", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		);

		if( $pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING )
		{
			$group['spending']['fields']['force_choice'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'force_choice',
				'title' => __("Force manual redeem", LWS_WOOREWARDS_PRO_DOMAIN),
				'type'  => 'box',
				'extra' => array(
					'checked' => $pool->getOption('force_choice'),
					'default' => false,
					'advanced' => true,
					'tooltips' => __("Default, if reward is single, it is automatically redeemed as soon as customer reach the required point amount. Check that to force the user to redeem manually.", LWS_WOOREWARDS_PRO_DOMAIN)
				)
			);
		}
		else
		{
			$group['earning']['fields']['confiscation'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'confiscation',
				'title' => __("Lose rewards with points expiration", LWS_WOOREWARDS_PRO_DOMAIN),
				'type'  => 'box',
				'extra' => array(
					'checked' => $pool->getOption('confiscation'),
					'advanced' => true,
					'tooltips' => __("After a points loss due to inactivity, the customer will have to earn again all the rewards. Ignored if points expiration delay not set.", LWS_WOOREWARDS_PRO_DOMAIN)
				)
			);

			$group['spending']['fields']['clamp_level'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'clamp_level',
				'title' => __("One level at a time", LWS_WOOREWARDS_PRO_DOMAIN),
				'type'  => 'box',
				'extra' => array(
					'checked' => $pool->getOption('clamp_level'),
					'default' => false,
					'advanced' => true,
					'tooltips' => __("If checked, customers can't earn more points than the points needed to reach the next level in one time.", LWS_WOOREWARDS_PRO_DOMAIN)
				)
			);
		}

		return $group;
	}

	protected function getGroupByLevelSettings($pool)
	{
		$groupBy = array(
			'key'       => 'cost',
			'activated' => ($pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING),
			'add'       => __("Add level", LWS_WOOREWARDS_PRO_DOMAIN)
		);

		$groupBy['head'] = "<div class='lws-wr-levelling-node-head'>";
		$groupBy['head'] .= "<div class='lws-wr-levelling-node-item grouped_title'>";
		$groupBy['head'] .= "<div class='lws-wr-levelling-node-value'><span data-name='grouped_title'>".__("Untitled", LWS_WOOREWARDS_PRO_DOMAIN)."</span></div>";
		$groupBy['head'] .= "<div class='lws-wr-levelling-node-label'>"._x("Level Title", "Level Threshold Title edit", LWS_WOOREWARDS_PRO_DOMAIN)."</div>";
		$groupBy['head'] .= "</div>";
		$groupBy['head'] .= "<div class='lws-wr-levelling-node-item cost'>";
		$groupBy['head'] .= "<div class='lws-wr-levelling-node-value'><span data-name='cost'>1</span></div>";
		$groupBy['head'] .= "<div class='lws-wr-levelling-node-label'>"._x("Points Threshold", "edit", LWS_WOOREWARDS_PRO_DOMAIN)."</div>";
		$groupBy['head'] .= "</div>";
		$groupBy['head'] .= "</div>";

		$groupBy['form'] = "<div class='lws-wr-levelling-node-form'>";
		$groupBy['form'] .= "<div class='lws-wr-levelling-node-item grouped_title'>";
		$regexTitle = \esc_attr(__("Title is required.", LWS_WOOREWARDS_PRO_DOMAIN));
		$groupBy['form'] .= "<div class='lws-wr-levelling-node-value'><input type='text' class='lws-input lws-wr-title-input' name='grouped_title' data-pattern='[^\\s]+' data-pattern-title='$regexTitle'/></div>";
		$groupBy['form'] .= "<div class='lws-wr-levelling-node-label'>"._x("Level Title", "Level Threshold Title edit", LWS_WOOREWARDS_PRO_DOMAIN)."</div>";
		$groupBy['form'] .= "</div>";
		$groupBy['form'] .= "<div class='lws-wr-levelling-node-item cost'>";
		$regexTitle = \esc_attr(__("Cost must be a number greater than zero.", LWS_WOOREWARDS_PRO_DOMAIN));
		$groupBy['form'] .= "<div class='lws-wr-levelling-node-value'><input name='cost' class='lws-input lws-wr-cost-input' data-pattern='^\\d*[1-9]\\d*$' data-pattern-title='$regexTitle'/></div>";
		$groupBy['form'] .= "<div class='lws-wr-levelling-node-label'>"._x("Points Threshold", "edit", LWS_WOOREWARDS_PRO_DOMAIN)."</div>";
		$groupBy['form'] .= "</div>";
		$groupBy['form'] .= "</div>";


		return $groupBy;
	}

	function poolGeneralSettings($fields, \LWS\WOOREWARDS\Core\Pool $pool)
	{
		if( empty($pool->getId()) )
		{
			$fields['type'] = array(
				'id'    => self::POOL_OPTION_PREFIX.'type',
				'type'  => 'select',
				'title' => __("Behavior", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra' => array(
					'id'      => 'lws-wr-pool-option-type',
					'value'   => $pool->getOption('type'),
					'notnull' => true,
					'options' => array(
						'standard'  => _x("Standard", "Pool Type/behavior", LWS_WOOREWARDS_PRO_DOMAIN),
						'levelling' => _x("Levelling", "Pool Type/behavior", LWS_WOOREWARDS_PRO_DOMAIN)
					),
					'help' => '<ul><li>'.__("<i>Standard behavior</i>: customers can spend points to buy rewards", LWS_WOOREWARDS_PRO_DOMAIN)
						. '</li><li>'.__("<i>Levelling behavior</i>: rewards are automatically granted to customers since they have enough points (points are never spent).", LWS_WOOREWARDS_PRO_DOMAIN)
						. '</li></ul>'
				)
			);
		}

		$fields['roles'] = array(
			'id'    => self::POOL_OPTION_PREFIX . 'roles',
			'title' => __("Restricted access", LWS_WOOREWARDS_PRO_DOMAIN),
			'type'  => 'lacchecklist',
			'extra' => array(
				'value' => $pool->getOption('roles'),
				'ajax' => 'lws_adminpanel_get_roles',
				'advanced' => true,
				'tooltips' => __("If set, only users with at least one of the selected roles can enjoy that loyalty system. By default, a loylaty system is available for anyone.", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		);

		if( $pool->isDeletable() && $pool->getOption('happening') )
		{
			$date = $pool->getOption('period_start');
			$fields['period_start'] = array(
				'id'    => self::POOL_OPTION_PREFIX.'period_start',
				'type'  => 'input',
				'title' => __("Start Date", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra' => array(
					'id'      => 'lws-wr-pool-option-period-begin',
					'type'  => 'date',
					'value' => empty($date) ? '' : $date->format('Y-m-d'),
					'help'  => __("Before that date, the loyalty system is disabled but customer can see it.", LWS_WOOREWARDS_PRO_DOMAIN)
				)
			);

			if( $pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING )
			{
				$date = $pool->getOption('period_mid');
				$fields['period_mid'] = array(
					'id'    => self::POOL_OPTION_PREFIX.'period_mid',
					'type'  => 'input',
					'title' => __("Point earning end", LWS_WOOREWARDS_PRO_DOMAIN),
					'extra' => array(
						'id'      => 'lws-wr-pool-option-period-mid',
						'type'  => 'date',
						'value' => empty($date) ? '' : $date->format('Y-m-d'),
						'help'  => __("After that date, customers can no longer earn points. But they still can spend them for rewards.", LWS_WOOREWARDS_PRO_DOMAIN)
					)
				);
			}

			$date = $pool->getOption('period_end');
			$fields['period_end'] = array(
				'id'    => self::POOL_OPTION_PREFIX.'period_end',
				'type'  => 'input',
				'title' => __("End Date", LWS_WOOREWARDS_PRO_DOMAIN),
				'extra' => array(
					'id'      => 'lws-wr-pool-option-period-end',
					'type'  => 'date',
					'value' => empty($date) ? '' : $date->format('Y-m-d'),
					'help'  => __("After that date, the loyalty system will be disabled but customer can see it. Customers keep their remaining points but cannot use them anymore.", LWS_WOOREWARDS_PRO_DOMAIN)
				)
			);
		}

		return $fields;
	}

	function userspointsFilters($filters)
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsrangefilter.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsactivityfilter.php';

		$filters = array_merge(array(
			'range' => new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsRangeFilter('range'),
			'activity' => new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsActivityFilter('activity')
		), $filters );

		if( !empty(\get_option('lws_woorewards_manage_badge_enable', 'on')) )
		{
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbadgefilter.php';
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbadgeassignbulkaction.php';
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbadgeremovebulkaction.php';

			$filters = array_merge(array(
				'badge' => new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBadgeFilter('badge'),
			), $filters );

			$filters['badge_add'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBadgeAssignBulkAction('badge_add');
			$filters['badge_rem'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBadgeRemoveBulkAction('badge_rem');
		}

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbulkaction.php';
		$filters['points_add'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBulkAction('points_add');
		return $filters;
	}

}
?>
