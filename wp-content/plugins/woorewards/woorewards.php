<?php
/**
 * Plugin Name: WooRewards
 * Description: Improve your customers experience with Rewards, Levels and Achievements. Use it with WooCommerce to set up a loyalty program.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 3.7.1
 * License: Copyright LongWatchStudio 2019
 * Text Domain: woorewards-lite
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.7
 *
 * Copyright (c) 2019 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 *
 */


// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** That class holds the entire plugin. */
final class LWS_WooRewards
{

	public static function init()
	{
		static $instance = false;
		if( !$instance )
		{
			$instance = new self();
			$instance->defineConstants();
			$instance->load_plugin_textdomain();

			add_action( 'lws_adminpanel_register', array($instance, 'register') );
			add_action( 'lws_adminpanel_plugins', array($instance, 'plugin') );

			add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), array($instance, 'extensionListActions'), 10, 2 );
			add_filter( 'plugin_row_meta', array($instance, 'addLicenceLink'), 10, 4 );
			add_filter( 'lws_adminpanel_purchase_url_woorewards', array($instance, 'addPurchaseUrl'), 10, 1 );
			foreach( array('', '.customers', '.loyalty', '.settings') as $page)
				add_filter( 'lws_adminpanel_plugin_version_'.LWS_WOOREWARDS_PAGE.$page, array($instance, 'addPluginVersion'), 10, 1 );
			add_filter( 'lws_adminpanel_documentation_url_woorewards', array($instance, 'addDocUrl'), 10, 1 );

			if( \is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/updater.php';
				// piority as soon as possible, But sad bug from WP.
				// Trying to get property of non-object in ./wp-includes/post.php near line 3917: $feeds = $wp_rewrite->feeds;
				// cannot do it sooner.
				add_action('setup_theme', array('\LWS\WOOREWARDS\Updater', 'checkUpdate'), -100);
				add_action('setup_theme', array($instance, 'forceVisitLicencePage'), 0);
			}

			$instance->install();

			register_activation_hook( __FILE__, 'LWS_WooRewards::activation' );
		}
		return $instance;
	}

	function forceVisitLicencePage()
	{
		if( \get_option('lws_woorewards_redirect_to_licence', 0) > 0 )
		{
			\update_option('lws_woorewards_redirect_to_licence', 0);
			\wp_redirect(\add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.settings', 'tab'=>'license'), admin_url('admin.php')));
		}
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
		load_plugin_textdomain( LWS_WOOREWARDS_DOMAIN, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define( 'LWS_WOOREWARDS_VERSION', $this->v() );
		define( 'LWS_WOOREWARDS_FILE', __FILE__ );
		define( 'LWS_WOOREWARDS_DOMAIN', 'woorewards-lite' );
		define( 'LWS_WOOREWARDS_PAGE', 'woorewards' );

		define( 'LWS_WOOREWARDS_PATH', dirname( LWS_WOOREWARDS_FILE ) );
		define( 'LWS_WOOREWARDS_INCLUDES', LWS_WOOREWARDS_PATH . '/include' );
		define( 'LWS_WOOREWARDS_SNIPPETS', LWS_WOOREWARDS_PATH . '/snippets' );
		define( 'LWS_WOOREWARDS_ASSETS',   LWS_WOOREWARDS_PATH . '/assets' );

		define( 'LWS_WOOREWARDS_URL', 		plugins_url( '', LWS_WOOREWARDS_FILE ) );
		define( 'LWS_WOOREWARDS_JS',  		plugins_url( '/js', LWS_WOOREWARDS_FILE ) );
		define( 'LWS_WOOREWARDS_CSS', 		plugins_url( '/css', LWS_WOOREWARDS_FILE ) );
		define( 'LWS_WOOREWARDS_IMG', 		plugins_url( '/img', LWS_WOOREWARDS_FILE ) );
	}

	public function extensionListActions($links, $file)
	{
		$label = __('Settings'); // use standart wp sentence, no text domain
		$url = add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty'), admin_url('admin.php'));
		array_unshift($links, "<a href='$url'>$label</a>");
		$label = __('Help'); // use standart wp sentence, no text domain
		$url = esc_attr($this->addDocUrl(''));
		$links[] = "<a href='$url'>$label</a>";
		return $links;
	}

	public function addLicenceLink($links, $file, $data, $status)
	{
		if( (!defined('LWS_WOOREWARDS_ACTIVATED') || !LWS_WOOREWARDS_ACTIVATED) && plugin_basename(__FILE__)==$file)
		{
			$label = __('Add Licence Key', LWS_WOOREWARDS_DOMAIN);
			$url = add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.settings', 'tab'=>'license'), admin_url('admin.php'));
			$links[] = "<a href='$url'>$label</a>";
		}
		return $links;
	}

	public function addPurchaseUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/en/product/woorewards-en/", LWS_WOOREWARDS_DOMAIN);
	}

	public function addPluginVersion($url)
	{
		return $this->v();
	}

	public function addDocUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/docs/woorewards/", LWS_WOOREWARDS_DOMAIN);
	}

	function register()
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/admin.php';
		new \LWS\WOOREWARDS\Ui\Admin();
	}

	public function plugin()
	{
		lws_register_update(__FILE__, null, md5(\get_class() . 'update'));
		$licPageIds = \apply_filters('lws_woorewards_lic_page_ids', array(LWS_WOOREWARDS_PAGE, LWS_WOOREWARDS_PAGE.'.settings', LWS_WOOREWARDS_PAGE.'.loyalty'));
		$activated = lws_require_activation(__FILE__, null, $licPageIds, md5(\get_class() . 'update'));

		lws_extension_showcase(__FILE__);
		define( 'LWS_WOOREWARDS_ACTIVATED', $activated );

		if( !(defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED) && !empty(get_option('lws_woorewards_pro_version')) )
		{
			add_action('setup_theme', array($this, 'parseQueryForPoolCompletion'), 0);
			add_action('admin_notices', array($this, 'testPoolCompletion'));
		}
	}

	/** Add elements we need on this plugin to work */
	public static function activation()
	{
		require_once dirname(__FILE__).'/include/updater.php';
		\LWS\WOOREWARDS\Updater::activate();

		wp_schedule_event(time(), 'daily', 'lws_woorewards_daily_event');
	}

	/** autoload WooRewards core and collection classes. */
	public function autoload($class)
	{
		if( substr($class, 0, 15) == 'LWS\WOOREWARDS\\' )
		{
			$rest = substr($class, 15);
			$publicNamespaces = array(
				'Collections', 'Abstracts', 'Conveniencies', 'Core', 'Unlockables', 'Events'
			);
			$publicClasses = array(
				'Ui\Editlists\EventList', 'Ui\Editlists\UnlockableList'
			);

			if( in_array(explode('\\', $rest, 2)[0], $publicNamespaces) || in_array($rest, $publicClasses) )
			{
				$basename = str_replace('\\', '/', strtolower($rest));
				$filepath = LWS_WOOREWARDS_INCLUDES . '/' . $basename . '.php';
				if( file_exists($filepath) )
				{
					@include_once $filepath;
					return true;
				}
			}
		}
	}

	/**	Is WooCommerce installed and activated.
	 *	Could be sure only after hook 'plugins_loaded'.
	 *	@return is WooCommerce installed and activated.
	 *	@param $false provided to be used with filters. */
	static public function isWC($false=false)
	{
		return function_exists('wc');
	}

	/** If another name/symbol should be used instead of point.
	 * @param $count to return singular or plural form. */
	static public function getPointSymbol($count=1, $poolName='')
	{
		static $symbols = array();
		$single = ($count > 1 ? 'p' : 's');
//		if( !isset($symbols[$poolName]) || !isset($symbols[$poolName][$single]) )
		{
			// pool dedicated
			$sym = \apply_filters('lws_woorewards_point_symbol_translation_'.$poolName, false, $count);
			if( $sym === false ) // generic symbol
				$sym = \apply_filters('lws_woorewards_point_symbol_translation', false, $count, $poolName);
			if( $sym === false ) // default symbol
			{
				$sym = ($count == 1 ? __("Point", LWS_WOOREWARDS_DOMAIN) : __("Points", LWS_WOOREWARDS_DOMAIN));
			}
			$symbols[$poolName][$single] = $sym;
		}
		return $symbols[$poolName][$single];
	}

	static public function formatPointsWithSymbol($points, $poolName)
	{
		$sym = self::getPointSymbol($points, $poolName);
		return \apply_filters('lws_woorewards_point_with_symbol_format', sprintf("%s %s", $points, $sym), $points, $sym, $poolName);
	}

	static public function symbolFilter($symbol='', $count=1, $poolName='')
	{
		return getPointSymbol($count, $poolName);
	}

	/** Take care WP_Post manipulation is hazardous before hook 'setup_theme' (since global $wp_rewrite is not already set) */
	private function install()
	{
		spl_autoload_register(array($this, 'autoload'));
		add_filter('lws_woorewards_is_woocommerce_active', array(get_class(), 'isWC'));
		add_filter('lws_woorewards_point_symbol', array(get_class(), 'symbolFilter', 10, 3));
		require_once LWS_WOOREWARDS_INCLUDES . '/registration.php';

		add_image_size('lws_wr_thumbnail', 96, 96);
		add_image_size('lws_wr_thumbnail_small', 42, 42);
		add_filter( 'image_size_names_choose', function($sizes){
			return array_merge($sizes, array(
				'lws_wr_thumbnail' => __("WooRewards Thumbnail", LWS_WOOREWARDS_DOMAIN),
				'lws_wr_thumbnail_small' => __("WooRewards Thumbnail Small", LWS_WOOREWARDS_DOMAIN)
			));
		});

		add_filter('lws_adminpanel_field_types', function($types){
			$types['woorewards_duration'] = array('\LWS\WOOREWARDS\Ui\DurationField', LWS_WOOREWARDS_INCLUDES . '/ui/durationfield.php');
			return $types;
		});

		// anyway, load all pools and install configured events. Do it as soon as possible but let anywho to hook before.
		add_action('setup_theme', array(get_class(), 'installPool'));
		add_action('init', array($this, 'registerPostTypes'));

		add_filter('lws_woorewards_order_events', array($this, 'getOrderValidationStates'));

		require_once LWS_WOOREWARDS_INCLUDES . '/options.php';
		new \LWS\WOOREWARDS\Options();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/achievement.php';
		new \LWS\WOOREWARDS\Ui\Achievement();
		require_once LWS_WOOREWARDS_INCLUDES . '/core/ajax.php';
		new \LWS\WOOREWARDS\Core\Ajax();
		require_once LWS_WOOREWARDS_INCLUDES.'/unlockables/coupon.php';
		\LWS\WOOREWARDS\Unlockables\Coupon::addUiFilters();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/userhistory.php';
		new \LWS\WOOREWARDS\Ui\UserHistory();

		// Widgets
		require_once LWS_WOOREWARDS_INCLUDES .'/ui/widgets/pointsdisplayer.php';
		LWS\WOOREWARDS\Ui\Widgets\PointsDisplayer::install();

		// Email template
		require_once LWS_WOOREWARDS_INCLUDES . '/mails/newreward.php';
		new \LWS\WOOREWARDS\Mails\NewReward();

		// Gutenberg
		if( function_exists('register_block_type') )
			$this->gutenberg();
	}

	function getOrderValidationStates($events)
	{
		$events = array('completed');
		if( empty(\get_option('lws_woorewards_coupon_state' , '')) )
			$events[] = 'processing';
		return $events;
	}

	function registerPostTypes()
	{
		\register_post_type(\LWS\WOOREWARDS\Core\Pool::POST_TYPE, array(
			'hierarchical' => true,
			'labels' => array(
				'name' => __("Loyalty Systems", LWS_WOOREWARDS_DOMAIN),
				'singular_name' => __("Loyalty System", LWS_WOOREWARDS_DOMAIN)
			)
		));
		\register_post_type(\LWS\WOOREWARDS\Abstracts\Event::POST_TYPE, array(
			'hierarchical' => true,
			'labels' => array(
				'name' => __("Earning Points Methods", LWS_WOOREWARDS_DOMAIN),
				'singular_name' => __("Earning Points Method", LWS_WOOREWARDS_DOMAIN)
			)
		));
		\register_post_type(\LWS\WOOREWARDS\Abstracts\Unlockable::POST_TYPE, array(
			'hierarchical' => true,
			'labels' => array(
				'name' => __("Rewards", LWS_WOOREWARDS_DOMAIN),
				'singular_name' => __("Reward", LWS_WOOREWARDS_DOMAIN)
			)
		));
	}

	/** Init gutenberg bloc icon and category. */
	protected function gutenberg()
	{
		add_filter('block_categories', function($cats, $post){
			$cats[] = array(
				'slug' => LWS_WOOREWARDS_PAGE,
				'title' => __("WooRewards", LWS_WOOREWARDS_DOMAIN)
			);
			return $cats;
		}, 10, 2);

		add_action('init', function(){
			\wp_register_script('woorewards-gutenberg', LWS_WOOREWARDS_JS.'/gutenberg/icon.js', array('wp-blocks', 'wp-element'), LWS_WOOREWARDS_VERSION);
		});
	}

	static function installPool()
	{
		\LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'post_status' => array('publish', 'private'),
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
		))->install();
	}

	/** Should be called inside an admin_notice hook.
	 * Call only if a trial was installed but plugin downgraded to free. */
	function testPoolCompletion()
	{
		require_once LWS_WOOREWARDS_INCLUDES.'/updater.php';
		if( \LWS\WOOREWARDS\Updater::isMissingPrefabEventsAndUnlockables() )
		{
			$content = __("We detect a WooRewards licence downgrade: Click here to restore missing configuration.", LWS_WOOREWARDS_DOMAIN);
			$button = esc_attr(__("Restore", LWS_WOOREWARDS_DOMAIN));
			$formId = 'lws-wr-missing-form';

			echo "<div class='notice notice-error lws-adminpanel-notice is-dismissible'><p>";
			echo "<form id='$formId' name='$formId' method='post'>";
			\wp_nonce_field($formId, 'lws-wr-missing-nonce', true, true);
			echo "<input type='hidden' name='lws-wr-missing-restore' value='restore'/>" . $content;
			echo "<input type='submit' value='$button' class='lws-adm-btn lws-wr-missing-restore-submit'/>";
			echo "</form></p></div>";
		}
	}

	/** is pool completion requested, then do it @see \LWS\WOOREWARDS\Updater::addMissingPrefabEventsAndUnlockables */
	function parseQueryForPoolCompletion()
	{
		$formId = 'lws-wr-missing-form';
		if( isset($_POST['lws-wr-missing-restore'])
		&& trim($_POST['lws-wr-missing-restore']) == 'restore'
		&& \check_admin_referer($formId, 'lws-wr-missing-nonce')
		&& \wp_verify_nonce($_POST['lws-wr-missing-nonce'], $formId) )
		{
			require_once LWS_WOOREWARDS_INCLUDES.'/updater.php';
			\LWS\WOOREWARDS\Updater::addMissingPrefabEventsAndUnlockables();
		}
	}

	/**	Display an achievement on the page (backend or frontend).
	 *	$param options (array)
	 *	or an array for custom achievement widget with:
	 *	* 'title' (string) At least a title is required for custom achievement.
	 *	* 'message' (string) optional, display a custom achievement with that message.
	 *	* 'image' (url) Achievement icon, if no url given, a default image is picked.
	 *	*	'user' (int) recipient user id.
	 *	* 'origin' (mixed, optional) source of the achievement. */
	public static function achievement($options=array())
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/achievement.php';
		\LWS\WOOREWARDS\Ui\Achievement::push($options);
	}
}

LWS_WooRewards::init();
@include_once dirname(__FILE__) . '/assets/lws-adminpanel/lws-adminpanel.php';
@include_once dirname(__FILE__) . '/modules/woorewards-pro/woorewards-pro.php';

?>