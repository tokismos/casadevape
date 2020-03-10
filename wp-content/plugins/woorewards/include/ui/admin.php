<?php
namespace LWS\WOOREWARDS\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Create the backend menu and settings pages. */
class Admin
{
	public function __construct()
	{
		/** @param array, the fields settings array. @param Pool */
		\add_filter('lws_woorewards_admin_pool_general_settings', array($this, 'getPoolGeneralSettings'), 10, 2);

		lws_register_pages($this->getPages());
		\add_action('admin_enqueue_scripts', array($this , 'scripts'));

		// replace usual notice by a badge teaser
		if( !defined('LWS_WOOREWARDS_ACTIVATED') || !LWS_WOOREWARDS_ACTIVATED )
			\add_filter('pre_set_transient_settings_errors', array($this, 'noticeSettingsSaved'));
	}

	public function scripts($hook)
	{
		// Force the menu icon with lws-icons font
		\wp_enqueue_style('wr-menu-icon', LWS_WOOREWARDS_CSS . '/menu-icon.css', array(), LWS_WOOREWARDS_VERSION);

		if( false !== ($ppos = strpos($hook, LWS_WOOREWARDS_PAGE)) )
		{
			$page = substr($hook, $ppos);
			$tab = isset($_GET['tab']) ? $_GET['tab'] : '';

			if( !defined('LWS_WOOREWARDS_ACTIVATED') || !LWS_WOOREWARDS_ACTIVATED )
			{
				// let badge teaser replace the notice
				\wp_enqueue_style('lws-wre-notice', LWS_WOOREWARDS_CSS . '/notice.css', array(), LWS_WOOREWARDS_VERSION);
			}

			if( ($page == LWS_WOOREWARDS_PAGE || false !== strpos($page, 'customers')) && (empty($tab) || strpos($tab, 'wr_customers') !== false) )
			{
				// labels displayed in points history
				$labels = array(
					'hist' => __("Points History", LWS_WOOREWARDS_DOMAIN),
					'desc' => __("Description", LWS_WOOREWARDS_DOMAIN),
					'date' => __("Date", LWS_WOOREWARDS_DOMAIN),
					'points' => __("Points", LWS_WOOREWARDS_DOMAIN),
					'total' => __("Total", LWS_WOOREWARDS_DOMAIN),
				);
				// enqueue editlist column folding script
				foreach( ($deps = array('jquery', 'lws-tools')) as $dep )
					\wp_enqueue_script($dep);

				\wp_register_script('lws-wre-userspoints', LWS_WOOREWARDS_JS . '/userspoints.js', $deps, LWS_WOOREWARDS_VERSION, true);
				\wp_localize_script('lws-wre-userspoints', 'lws_wr_userspoints_labels', $labels);
				\wp_enqueue_script('lws-wre-userspoints');
				\wp_enqueue_style('lws-wre-userspoints', LWS_WOOREWARDS_CSS . '/userspoints.css', array(), LWS_WOOREWARDS_VERSION);

				\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));
				\do_action('lws_woorewards_ui_userspoints_enqueue_scripts', $hook, $tab);
			}
			else if( false !== strpos($page, 'loyalty') )
			{
				foreach( ($deps = array('jquery')) as $dep )
					\wp_enqueue_script($dep);
				\wp_enqueue_script('lws-wre-poolsettings', LWS_WOOREWARDS_JS . '/poolsettings.js', $deps, LWS_WOOREWARDS_VERSION, true);
				\wp_enqueue_style('lws-wre-poolsettings', LWS_WOOREWARDS_CSS . '/poolsettings.css', array(), LWS_WOOREWARDS_VERSION);

				\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));
				\wp_enqueue_script('lws-checkbox');
				\wp_enqueue_style('lws-checkbox');
				\wp_enqueue_script('lws-switch');
				\wp_enqueue_style('lws-switch');
				\wp_enqueue_script('lws-spinner');
				\wp_enqueue_style('lws-spinner');
			}

			\wp_enqueue_script('lws-wre-coupon-edit', LWS_WOOREWARDS_JS . '/couponedit.js', array('jquery'), LWS_WOOREWARDS_VERSION, true);
		}
	}

	/** Push an achievement teaser instead our usual notice at setting save. */
	public function noticeSettingsSaved($value)
	{
		if( !empty($value) && isset($_POST['option_page']) && false !== strpos($_POST['option_page'], LWS_WOOREWARDS_PAGE) )
		{
			$val = \current($value);
			if( isset($val['type']) && $val['type'] == 'updated' && isset($val['code']) && $val['code'] == 'settings_updated' )
			{
				$teasers = array(
					__("Add fun and achievements for your customers with the <a>Pro Version</a>", LWS_WOOREWARDS_DOMAIN),
					__("Try the <a>Pro Version</a> for free for 30 days", LWS_WOOREWARDS_DOMAIN),
					__("The <a>Pro Version</a> adds Events and Levelling systems. Try <a>it</a>", LWS_WOOREWARDS_DOMAIN)
				);
				\LWS_WooRewards::achievement(array(
					'title'   => __("Your settings have been saved.", LWS_WOOREWARDS_DOMAIN),
					'message' => str_replace(
						'<a>',
						"<a href='https://plugins.longwatchstudio.com/product/woorewards/' target='_blank'>",
						$teasers[rand(0, count($teasers)-1)]
					)
				));
			}
		}
		return $value;
	}

	protected function getPages()
	{
		$pages = $this->getMainMenu();

		$first = array_keys($pages)[0];
		$pages[$first] = array_merge($pages[$first], array(
			'id'        => LWS_WOOREWARDS_PAGE,
			'dashicons' => '',
			'index'     => 57,
		));

		$title = __("WooRewards", LWS_WOOREWARDS_DOMAIN);
		foreach( $pages as &$p )
		{
			$p['subtitle'] = $p['title'];
			$p['title'] = $title;
		}

		return $pages;
	}

	protected function getMainMenu()
	{
		if( false === ($customer = \apply_filters('lws_woorewards_ui_customers_tab_get', false)) )
		{
			$customer = array(
				'title'    => __("Customers", LWS_WOOREWARDS_DOMAIN),
				'id'       => LWS_WOOREWARDS_PAGE.'.customers',
				'rights'   => 'manage_rewards',
				'tabs'     => array('wr_customers' => array(
					'title'    => __("Customers", LWS_WOOREWARDS_DOMAIN),
					'id'       => 'wr_customers',
					'groups'   => $this->getCustomersGroups()
				))
			);
		}

		if( false === ($loyalty = \apply_filters('lws_woorewards_ui_loyalty_tab_get', false)) )
		{
			$loyalty = array(
				'title'    => __("Loyalty System", LWS_WOOREWARDS_DOMAIN),
				'rights'   => 'manage_rewards',
				'id'       => LWS_WOOREWARDS_PAGE.'.loyalty',
				'tabs' => array('wr_loyalty' => array(
					'title'    => __("Loyalty System", LWS_WOOREWARDS_DOMAIN),
					'id'       => 'wr_loyalty',
					'groups'   => $this->getLoyaltyGroups()
				))
			);
		}

		return array(
			'wr_customers' => $customer,
			'wr_loyalty'   => $loyalty,
			'wr_settings'  => array(
				'title'    => __("Settings", LWS_WOOREWARDS_DOMAIN),
				'subtitle' => __("Settings", LWS_WOOREWARDS_DOMAIN),
				'id'       => LWS_WOOREWARDS_PAGE.'.settings',
				'rights'   => 'manage_rewards',
				'tabs'     => $this->getSettingsTabs()
			)
		);
	}

	/** For all users, list points for each pool. */
	protected function getCustomersGroups()
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/editlists/userspoints.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/editlists/userspointsbulkaction.php';
		$editlist = \lws_editlist(
			'userspoints',
			'user_id',
			new \LWS\WOOREWARDS\Ui\Editlists\UsersPoints(),
			\LWS\Adminpanel\EditList::FIX,
			\apply_filters('lws_woorewards_ui_userspoints_filters', array(
				'user_search' => new \LWS\Adminpanel\EditList\FilterSimpleField('usersearch', __('Search...', LWS_WOOREWARDS_DOMAIN)),
				'points_add'  => new \LWS\WOOREWARDS\Ui\Editlists\UsersPointsBulkAction('points_add')
			))
		);

		$customersGroup = array(
			'customers_points' => array(
				'title'    => __("Points Management", LWS_WOOREWARDS_DOMAIN),
				'text'     => __("Here you can see and manage your customers reward points", LWS_WOOREWARDS_DOMAIN)
					."<br/>".__("You can view the points <b>history</b> by clicking the points total in the table", LWS_WOOREWARDS_DOMAIN)
					."<br/>".sprintf(__("You could present a point history to your customer by using the shortcode %s", LWS_WOOREWARDS_DOMAIN),'<b>[wr_show_history]</b>'),
				'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/customers-management/'),
				'editlist' => $editlist,
			)
		);

		if( (!defined('LWS_WOOREWARDS_ACTIVATED') || !LWS_WOOREWARDS_ACTIVATED) )
		{
			$customersGroup['customers_points']['fields']= array(
				'advertising' => array(
					'id' => '',
					'type' => 'help',
					'extra' => array(
						'help' => __("In <b>WooRewards Pro</b>, you can create as many loyalty systems as you want.", LWS_WOOREWARDS_DOMAIN)
						.'<br/>'.__("Try <a href='https://plugins.longwatchstudio.com/en/product/woorewards-en/'><b>WooRewards Pro</b></a> for free for 30 days.", LWS_WOOREWARDS_DOMAIN),
						'type' => 'pub',
					)
				)
			);
		}
		return $customersGroup;

	}

	/** Tease about pro version.
	 * Display standand pool settings. */
	protected function getLoyaltyGroups()
	{
		$groups = array();

		if( !\LWS_WooRewards::isWC() && (!defined('LWS_WOOREWARDS_ACTIVATED') || !LWS_WOOREWARDS_ACTIVATED) )
		{
			$groups['information'] = array(
				'id'    => 'information',
				'title' => __("Information", LWS_WOOREWARDS_DOMAIN),
				'text'  => __(
"WooRewards Standard uses WooCommerce <i>orders</i> and <i>coupons</i>.
<br/>You should install <a href='https://wordpress.org/plugins/woocommerce/' target='_blank'>WooCommerce</a> to have them active.
<br/>Or <a href='https://plugins.longwatchstudio.com/product/woorewards/' target='_blank'>upgrade <b>WooRewards</b> to the <b>Pro</b> version</a>
and enjoy new ways to earn points (social media, sponsoring... with or without WooCommerce) and a lot of new reward types !",
					LWS_WOOREWARDS_DOMAIN)
			);
		}

		// load the default pool
		$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'meta_query'  => array(
				array(
					'key'     => 'wre_pool_prefab',
					'value'   => 'yes',
					'compare' => 'LIKE'
				),
				array(
					'key'     => 'wre_pool_type',
					'value'   => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
					'compare' => 'LIKE'
				)
			)
		));

		if( $pools->count() <= 0 )
		{
			error_log("Warning: No pools loaded.");
			\lws_admin_add_notice_once('lws-wre-pool-nothing-loaded', __("No rewarding system available. Try to re-activate WooRewards plugin to install default ones.", LWS_WOOREWARDS_DOMAIN), array('level'=>'info', 'dismissible'=>true));
			$groups['failure'] = array(
				'id'    => 'failure',
				'title' => __("Loading failure", LWS_WOOREWARDS_DOMAIN),
				'text'  => __("It seems the prefab pool does not exists. Try to re-activate this plugin. If the problem persists, contact your administrator.", LWS_WOOREWARDS_DOMAIN)
			);
		}
		else
		{
			// let dedicated class create options
			$pool = $pools->get(0);
			$groups = array_merge($groups, array(
				'general'    => array(
					'id'       => 'general',
					'title'    => __("General Settings", LWS_WOOREWARDS_DOMAIN),
					'fields'   => \apply_filters('lws_woorewards_admin_pool_general_settings', array(), $pool)
				),
				'earning'    => array(
					'id'       => 'earning',
					'title'    => __("Earning points", LWS_WOOREWARDS_DOMAIN),
					'text'     => __("Here you can manage how your customers earn loyalty points", LWS_WOOREWARDS_DOMAIN),
					'editlist' => \lws_editlist(
						'EventList',
						\LWS\WOOREWARDS\Ui\Editlists\EventList::ROW_ID,
						new \LWS\WOOREWARDS\Ui\Editlists\EventList($pool),
						\LWS\Adminpanel\EditList::MOD
					)->setPageDisplay(false)->setCssClass('eventlist')
				),
				'spending'   => array(
					'id'       => 'spending',
					'title'    => __("Rewards", LWS_WOOREWARDS_DOMAIN),
					'text'     => __("Here you can manage the rewards for your customers", LWS_WOOREWARDS_DOMAIN),
					'editlist' => \lws_editlist(
						'UnlockableList',
						\LWS\WOOREWARDS\Ui\Editlists\UnlockableList::ROW_ID,
						new \LWS\WOOREWARDS\Ui\Editlists\UnlockableList($pool),
						\LWS\Adminpanel\EditList::MOD
					)->setPageDisplay(false)->setCssClass('unlockablelist')
				)
			));
		}
		if( (!defined('LWS_WOOREWARDS_ACTIVATED') || !LWS_WOOREWARDS_ACTIVATED) )
		{
			if( isset($groups['earning']) )
			{
				$groups['earning']['fields']= array(
					'tutorial' => array(
						'id' => 'lws_woorewards_tutorial_earning_vfree',
						'type' => 'help',
						'extra' => array(
							'help' => __("Watch our <a target='_blank' href='https://youtu.be/yl9WyGOGd_I'>youtube tutorial</a> to help you set up your loyalty program", LWS_WOOREWARDS_DOMAIN),
							'type' => 'youtube'
						)
					),
					'advertising' => array(
						'id' => 'lws_woorewards_advertising_earning_pro',
						'type' => 'help',
						'extra' => array(
							'help' => __("In <b>WooRewards Pro</b>, you can add many other ways to earn points.", LWS_WOOREWARDS_DOMAIN)
								.'<br/>'.__("Try <b><a href='https://plugins.longwatchstudio.com/en/product/woorewards-en/'>WooRewards Pro</a></b> for free for 30 days.", LWS_WOOREWARDS_DOMAIN),
							'type' => 'pub'
						)
					)
				);
			}
			if( isset($groups['spending']) )
			{
				$groups['spending']['fields']= array(
					'advertising' => array(
						'id' => 'lws_woorewards_advertising_spending_pro',
						'type' => 'help',
						'extra' => array(
							'help' => __("Offer free products, permanent reductions and other kinds of rewards in <b>WooRewards Pro</b>.", LWS_WOOREWARDS_DOMAIN)
								.'<br/>'.__("Try <b><a href='https://plugins.longwatchstudio.com/en/product/woorewards-en/'>WooRewards Pro</a></b> for free for 30 days.", LWS_WOOREWARDS_DOMAIN),
							'type' => 'pub'
						)
					)
				);
			}
		}

		return $groups;
	}

	/** For pool option in admin page:
	 * *	be sure field id starts with 'lws-wr-pool-option-' and Pool->setOption accept the id string rest as valid option name.
	 * *	be sure the page contains a <input> named 'pool' with relevant pool id.
	 * *	since field cannot read value in wp get_option, be sure to set the relevant value in extra array.
	 *
	 *	@param fields an array as required by 'fields' entry in admin group.
	 * 	@param $pool a Pool instance. */
	public function getPoolGeneralSettings($fields, \LWS\WOOREWARDS\Core\Pool $pool)
	{
		$poolOptionPrefix = 'lws-wr-pool-option-';

		$fields['pool'] = array(
			'id'    => 'lws-wr-pool-option',
			'type'  => 'hidden',
			'extra' => array(
				'value' => $pool->getId()
			)
		);

		$fields['enabled'] = array(
			'id'    => $poolOptionPrefix.'enabled', /// id starts with 'lws-wr-pool-option-', 'enabled' is accepted as Pool option
			'type'  => 'box',
			'title' => 'Status',
			'extra' => array(
				'class'   => 'lws_switch lws-force-confirm',
				'checked' => $pool->getOption('enabled'), /// set field value here
				'data'    => array(
					'default' => _x("Off", "pool enabled switch", LWS_WOOREWARDS_DOMAIN),
					'checked' => _x("On", "pool enabled switch", LWS_WOOREWARDS_DOMAIN)
				)
			)
		);

		$fields['title'] = array(
			'id'    => $poolOptionPrefix.'title',
			'type'  => 'text',
			'title' => _x("Title", "Pool title", LWS_WOOREWARDS_DOMAIN),
			'extra' => array(
				'required' => true,
				'value'    => $pool->getOption('title')
			)
		);

		return $fields;
	}

	protected function getSettingsTabs()
	{
		$tabs = array(
			'settings' => array(
				'id'     => 'settings',
				'title'  => __("General settings", LWS_WOOREWARDS_DOMAIN),
				'groups' => array(
					'settings' => array(
						'id'     => 'settings',
						'title'  => __("General settings", LWS_WOOREWARDS_DOMAIN),
						'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/other-settings/general-settings/'),
						'fields' => array(
							'inc_taxes' => array(
								'id'    => 'lws_woorewards_order_amount_includes_taxes',
								'title' => __("Includes taxes", LWS_WOOREWARDS_DOMAIN),
								'type'  => 'box',
								'extra' => array(
									'help'=>__("If checked, taxes will be included in the points earned when spending money", LWS_WOOREWARDS_DOMAIN),
								)
							),
							'order_state' => array(
								'id'    => 'lws_woorewards_coupon_state',
								'title' => __("Points on 'Complete' order only", LWS_WOOREWARDS_DOMAIN),
								'type'  => 'box',
								'extra' => array('help' => __("Default state to get points is the processing order status.<br/>If you want to use the complete order status instead (recommanded), check the below box", LWS_WOOREWARDS_DOMAIN))
							)
						)
					)
				)
			),
			'sty_mails' => array(
				'id'     => 'sty_mails',
				'title'  => __("Emails", LWS_WOOREWARDS_DOMAIN),
				'groups' => \lws_mail_settings(\apply_filters('lws_woorewards_mails', array()))
			),
			'sty_widgets' => array(
				'id'     => 'sty_widgets',
				'title'  => __("Widgets", LWS_WOOREWARDS_DOMAIN),
				'groups' => array(
					'showpoints' => array(
						'id' => 'showpoints',
						'title' => __("Display Points Widget", LWS_WOOREWARDS_DOMAIN),
						'text' => '',
						'fields' => array(
							'spunconnected' => array(
								'id' => 'lws_wooreward_showpoints_nouser',
								'title' => __("Text displayed if user not connected", LWS_WOOREWARDS_DOMAIN),
								'type' => 'text',
								'extra' => array(
									'size' => '50',
									'placeholder' => __("Please log in if you want to see your loyalty points", LWS_WOOREWARDS_DOMAIN),
									'help' => __("In this Widget, customers can see their Reward Points available, as well as a link to the 'My Account' page to see more details.", LWS_WOOREWARDS_DOMAIN)."<br/>".
									sprintf(__("If you want, you can use the following shortcode instead : %s.", LWS_WOOREWARDS_DOMAIN),"<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>[wr_show_points]</div><div class='lws-group-descr-copy-icon lws-icon-copy1 copy'></div></span>")
								)
							),
							'showpoints' => array(
								'id' => 'lws_woorewards_displaypoints_template',
								'type' => 'stygen',
								'extra' => array(
									'purpose' => 'filter',
									'template' => 'wr_display_points',
									'html'=> false,
									'css'=>LWS_WOOREWARDS_URL.'/css/templates/displaypoints.css',
									'help' => __("Here you can customize the look and displayed text of the shortcode/widget", LWS_WOOREWARDS_DOMAIN),
									'subids' => array(
										'lws_woorewards_displaypoints_title' => "WooRewards Show Points - title", // no translation on purpose
										'lws_woorewards_button_more_details' => "WooRewards Show Points - details", // no translation on purpose
									)
								)
							)
						)
					)
				)
			)
		);


		return $tabs;
	}

}
