<?php
/**
 * Plugin Name: WooRewards Pro
 * Description: Loyalty and Rewards system for WooCommerce.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 3.7.1
 * Text Domain: woorewards-pro
 * WC requires at least: 3.0.0
 * WC tested up to: 3.7
 *
 * Copyright (c) 2019 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**
 * @class LWS_WooRewards_Pro The class that holds the entire plugin
 */
final class LWS_WooRewards_Pro
{
	private static $loadedPools = false;
	private static $achievements = false;
	private static $delayedMailStack = array();

	public static function init()
	{
		static $instance = false;
		if( !$instance )
		{
			$instance = new self();
			$instance->defineConstants();
			$instance->load_plugin_textdomain();

			add_action( 'lws_adminpanel_plugins', array($instance, 'plugin'), 11 ); // priority after free
			add_filter( 'lws-ap-release-woorewards', array($instance, 'mark') );

			$instance->earlyInstall();
		}
		return $instance;
	}

	function mark($rc)
	{
		$rc .= 'pro';
		return $rc;
	}

	public function v()
	{
		static $version = '';
		if( empty($version) ){
			if( !function_exists('get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data(__FILE__, false);
			$version = (isset($data['Version']) ? $data['Version'] : '0');
		}
		return $version;
	}

	/** Load translation file
	 * If called via a hook like this
	 * @code
	 * add_action( 'plugins_loaded', array($instance,'load_plugin_textdomain'), 1 );
	 * @endcode
	 * Take care no text is translated before. */
	function load_plugin_textdomain() {
		load_plugin_textdomain( LWS_WOOREWARDS_PRO_DOMAIN, FALSE, substr(dirname(__FILE__), strlen(WP_PLUGIN_DIR)) . '/languages/' );
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define( 'LWS_WOOREWARDS_PRO_VERSION', $this->v() );
		define( 'LWS_WOOREWARDS_PRO_FILE', __FILE__ );

		define( 'LWS_WOOREWARDS_PRO_PATH', dirname( LWS_WOOREWARDS_PRO_FILE ) );
		define( 'LWS_WOOREWARDS_PRO_INCLUDES', LWS_WOOREWARDS_PRO_PATH . '/include' );
		define( 'LWS_WOOREWARDS_PRO_SNIPPETS', LWS_WOOREWARDS_PRO_PATH . '/snippets' );
		define( 'LWS_WOOREWARDS_PRO_ASSETS', LWS_WOOREWARDS_PRO_PATH . '/assets' );

		define( 'LWS_WOOREWARDS_PRO_URL', plugins_url( '', LWS_WOOREWARDS_PRO_FILE ) );
		define( 'LWS_WOOREWARDS_PRO_IMG', plugins_url( '/img', LWS_WOOREWARDS_PRO_FILE ) );
		define( 'LWS_WOOREWARDS_PRO_JS', plugins_url( '/js', LWS_WOOREWARDS_PRO_FILE ) );
		define( 'LWS_WOOREWARDS_PRO_CSS', plugins_url( '/css', LWS_WOOREWARDS_PRO_FILE ) );
		define( 'LWS_WOOREWARDS_PRO_DOMAIN', 'woorewards-pro');
	}

	public function plugin()
	{
		if( defined('LWS_WOOREWARDS_FILE') && lws_require_activation(LWS_WOOREWARDS_FILE) )
		{
			$this->install();
		}
	}

	/** autoload WooRewards core and collection classes. */
	public function autoload($class)
	{
		if( substr($class, 0, 19) == 'LWS\WOOREWARDS\PRO\\' )
		{
			$rest = substr($class, 19);
			$publicNamespaces = array(
				'Collections', 'Core', 'Unlockables', 'Events'
			);
			$publicClasses = array(
				'VariousData',
			);

			if( in_array(explode('\\', $rest, 2)[0], $publicNamespaces) || in_array($rest, $publicClasses) )
			{
				$basename = str_replace('\\', '/', strtolower($rest));
				$filepath = LWS_WOOREWARDS_PRO_INCLUDES . '/' . $basename . '.php';
				if( file_exists($filepath) )
				{
					@include_once $filepath;
					return true;
				}
			}
		}
	}

	function filterShowcase($posts)
	{
		for( $i=0 ; $i<count($posts) ; ++$i )
		{
			if( $posts[$i]->slug == 'woorewards' )
				unset($posts[$i]);
		}
		return array_values($posts);
	}

	/** not sure about valid pro licence yet. */
	private function earlyInstall()
	{
		if( \is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			require_once LWS_WOOREWARDS_PRO_INCLUDES.'/updater.php';
			add_action('setup_theme', array('\LWS\WOOREWARDS\PRO\Updater', 'checkUpdate'), -99); // after free update
		}

		$pageId = 'woorewards.achievement';
		\add_filter('lws_adminpanel_plugin_version_'.$pageId, function($v){return LWS_WOOREWARDS_PRO_VERSION;});
		\add_filter('lws_woorewards_lic_page_ids', function($pageIds)use($pageId){$pageIds[] = $pageId;return $pageIds;});
	}

	static function installPools()
	{
		self::$loadedPools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'post_status' => array('publish', 'private')
		))->install();
		\do_action('lws_woorewards_pools_loaded', self::$loadedPools);

		self::$achievements = \LWS\WOOREWARDS\PRO\Collections\Achievements::instanciate();
		if( !empty(\get_option('lws_woorewards_manage_badge_enable', 'on')) )
		{
			self::$achievements->load(array(
				'post_status' => array('publish', 'private')
			))->install();
			\do_action('lws_woorewards_achievements_loaded', self::$achievements);
		}
	}

	/* @see \LWS\WOOREWARDS\PRO\Core\Achievement */
	static function getLoadedAchievements()
	{
		if( self::$achievements !== false )
		{
			return self::$achievements;
		}
		else
		{
			error_log(__FUNCTION__ . " called too soon. Wait after 'setup_theme' hook or use the action 'lws_woorewards_achievements_loaded'.");
			return \LWS\WOOREWARDS\PRO\Collections\Achievements::instanciate();
		}
	}

	/* active < buyable < loaded @see \LWS\WOOREWARDS\PRO\Core\Pool */
	static function getLoadedPools()
	{
		if( self::$loadedPools !== false )
		{
			return self::$loadedPools;
		}
		else
		{
			error_log(__FUNCTION__ . " called too soon. Wait after 'setup_theme' hook or use the action 'lws_woorewards_pools_loaded'.");
			return \LWS\WOOREWARDS\Collections\Pools::instanciate();
		}
	}

	/* active < buyable < loaded @see \LWS\WOOREWARDS\PRO\Core\Pool */
	static function getBuyablePools()
	{
		static $buyablePools = false;
		if( $buyablePools !== false )
		{
			return $buyablePools;
		}
		else if( self::$loadedPools !== false )
		{
			$buyablePools = self::$loadedPools->filter(function($item){return $item->isBuyable();});
			return $buyablePools;
		}
		else
		{
			error_log(__FUNCTION__ . " called too soon. Wait after 'setup_theme' hook or use the action 'lws_woorewards_pools_loaded'.");
			return \LWS\WOOREWARDS\Collections\Pools::instanciate();
		}
	}

	/* active < buyable < loaded @see \LWS\WOOREWARDS\PRO\Core\Pool */
	static function getActivePools()
	{
		static $activePools = false;
		if( $activePools !== false )
		{
			return $activePools;
		}
		else if( self::$loadedPools !== false )
		{
			$activePools = self::$loadedPools->filter(function($item){return $item->isActive();});
			return $activePools;
		}
		else
		{
			error_log(__FUNCTION__ . " called too soon. Wait after 'setup_theme' hook or use the action 'lws_woorewards_pools_loaded'.");
			return \LWS\WOOREWARDS\Collections\Pools::instanciate();
		}
	}

	private function install()
	{
		spl_autoload_register(array($this, 'autoload'));
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/registration.php';

		// override the default pool
		\LWS\WOOREWARDS\Collections\Pools::register('\LWS\WOOREWARDS\PRO\Core\Pool');

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/admin.php';
		new \LWS\WOOREWARDS\PRO\Ui\Admin();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/badges.php';
		new \LWS\WOOREWARDS\PRO\Ui\Editlists\Badges();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/variousdata.php';

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/rewardclaim.php';
		new \LWS\WOOREWARDS\PRO\Core\RewardClaim();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/ajax.php';
		new \LWS\WOOREWARDS\PRO\Core\Ajax();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/rest.php';
		\LWS\WOOREWARDS\PRO\Core\Rest::registerRoutes();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/usertitle.php';
		new \LWS\WOOREWARDS\PRO\Core\UserTitle();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/wc/cart.php';
		new \LWS\WOOREWARDS\PRO\WC\Cart();


		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/unlockables/freeproduct.php';
		\LWS\WOOREWARDS\PRO\Unlockables\FreeProduct::registerFeatures();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/rewardswidget.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\RewardsWidget::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/sponsorwidget.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\SponsorWidget::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/couponswidget.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\CouponsWidget::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/eventswidget.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\EventsWidget::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/badgeswidget.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\BadgesWidget::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/achievementswidget.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\AchievementsWidget::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/easteregg.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\EasterEgg::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/referralwidget.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\ReferralWidget::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/socialshare.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\SocialShareWidget::install();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/endpoints/loyalty.php';
		new \LWS\WOOREWARDS\PRO\Ui\Endpoints\LoyaltyEndpoint();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/endpoints/badges.php';
		new \LWS\WOOREWARDS\PRO\Ui\Endpoints\BadgesEndpoint();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/endpoints/achievements.php';
		new \LWS\WOOREWARDS\PRO\Ui\Endpoints\AchievementsEndpoint();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/woocommerce/birthdayfield.php';
		\LWS\WOOREWARDS\PRO\Ui\BirthdayField::register();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/woocommerce/cartcouponsview.php';
		\LWS\WOOREWARDS\PRO\Ui\CartCouponsView::register();

		\add_action('lws_woorewards_pools_loaded', function(){
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/woocommerce/productpointspreview.php';
			\LWS\WOOREWARDS\PRO\Ui\ProductPointsPreview::register();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/woocommerce/cartpointspreview.php';
			\LWS\WOOREWARDS\PRO\Ui\CartPointsPreview::register();
		});

		if( !remove_action('setup_theme', array('LWS_WooRewards', 'installPool')) )
			error_log('Cannot remove LWS_WooRewards::installPool');
		\add_action('setup_theme', array(get_class(), 'installPools'));

		\add_filter('lws_woorewards_displaypoints_detail_url', array($this, 'displayPointsDetailUrl'), 10, 4);

		$this->setupEmails();

		\add_action('shutdown', array($this, 'sendDelayedMail'));
		\add_action('init', array($this, 'registerPostTypes'));
		\add_action('set_user_role', array($this, 'roleChanged'), 9999, 3);
		\add_filter('lws_woorewards_point_symbol_translation', array($this, 'poolSymbol'), 10, 3);
		\add_filter('lws_woorewards_point_with_symbol_format', array($this, 'formatPointWithSymbol'), 10, 4);
		\add_action('wp_enqueue_scripts', array($this, 'enqueueSymbolStyle'));
		\add_action('admin_enqueue_scripts', array($this, 'enqueueSymbolStyle'));
	}

	function enqueueSymbolStyle()
	{
		\wp_enqueue_style('lws-wr-point-symbol', LWS_WOOREWARDS_PRO_CSS.'/pointsymbol.css', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	function formatPointWithSymbol($text, $points, $sym, $poolName)
	{
		$pool = \LWS_WooRewards_Pro::getLoadedPools()->find($poolName);
		if( !$pool )
			$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('name'=>$poolName, 'deep'=>false, 'numberposts'=>1))->last();
		if( $pool )
		{
			$format = $pool->getOption('point_format');
			if( $format )
				$text = sprintf($format, $points, $sym);
		}
		return $text;
	}

	function poolSymbol($sym, $count, $poolName)
	{
		if( !$sym )
		{
			$pool = \LWS_WooRewards_Pro::getLoadedPools()->find($poolName);
			if( !$pool )
				$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('name'=>$poolName, 'deep'=>false, 'numberposts'=>1))->last();
			if( $pool )
			{
				$value = $pool->getOption('symbol_image');
				if( !$value )
					$value = $pool->getOption('disp_point_name_'.($count==1 ? 'singular' : 'plural'));
				if( !$value && $count != 1 )
					$value = $pool->getOption('disp_point_name_singular');
				if( $value )
					$sym = $value;
			}
		}
		return $sym;
	}

	/** @return if locked and lock for the nexts. */
	static function isRoleChangeLocked($releaseLock=false)
	{
		static $locked = false;
		$old = $locked;
		if( $releaseLock )
			$old = $locked = false;
		else
			$locked = true;
		return $old;
	}

	/** If role changed from elsewhere, restore the WooRewards roles. */
	function roleChanged($userId, $role, $old_roles)
	{
		if( !self::isRoleChangeLocked() )
		{
			$user = false;
			$prefix = \LWS\WOOREWARDS\PRO\Unlockables\Role::PREFIX;
			if( 0 !== strpos($role, $prefix) )
			{
				foreach( $old_roles as $old_role )
				{
					if( 0 === strpos($old_role, $prefix) )
					{
						if( !$user )
							$user = \get_user_by('ID', $userId);
						if( $user )
							$user->add_role($old_role);
					}
				}
			}

			self::isRoleChangeLocked(true);
		}
	}

	function registerPostTypes()
	{
		\register_post_type('lws_custom_reward', array(
			'labels' => array(
				'name' => __("Custom rewards", LWS_WOOREWARDS_PRO_DOMAIN),
				'singular_name' => __("Custom reward", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		\LWS\WOOREWARDS\PRO\Core\Badge::registerPostType();
		\register_post_type('lws-wre-achievement', array(
			'hierarchical' => true,
			'labels' => array(
				'name' => __("Achievements", LWS_WOOREWARDS_PRO_DOMAIN),
				'singular_name' => __("Achievement", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
	}

	/** Add (redirection to my-account/woorewards) button in displayPoints widget. */
	function displayPointsDetailUrl($url, $poolname, $pointstotal, $lws_stygen)
	{
		if( isset($myacc_url) && !empty($myacc_url) )
			return $myacc_url;
		else if( \LWS_WooRewards::isWC() )
			return \wc_get_endpoint_url('lws_woorewards', '', \wc_get_page_permalink('myaccount'));
		else
			return $url;
	}

	/** Register Email templates */
	protected function setupEmails()
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/newreward.php';
		new \LWS\WOOREWARDS\PRO\Mails\NewReward();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/achieved.php';
		new \LWS\WOOREWARDS\PRO\Mails\Achieved();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/availableunlockables.php';
		new \LWS\WOOREWARDS\PRO\Mails\AvailableUnlockables();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/sponsored.php';
		new \LWS\WOOREWARDS\PRO\Mails\Sponsored();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/couponreminder.php';
		new \LWS\WOOREWARDS\PRO\Mails\CouponReminder();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/pointsreminder.php';
		new \LWS\WOOREWARDS\PRO\Mails\PointsReminder();
	}

	/** The n-uplet {$ref, $email, $template} is used for unicity.
	 * whatever the data, a new delayed mail with same n-uplet overrides any previous email. */
	static function delayedMail($ref, $email, $template, $data)
	{
		self::$delayedMailStack[$template][$ref][$email] = $data;
	}

	function sendDelayedMail()
	{
		foreach( self::$delayedMailStack as $template => $refs )
		{
			foreach( $refs as $ref => $emails )
			{
				foreach( $emails as $email => $data )
				{
					\do_action('lws_mail_send', $email, $template, $data);
				}
			}
		}
	}

}

LWS_WooRewards_Pro::init();
