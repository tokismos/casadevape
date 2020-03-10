<?php
/**
 * Plugin Name: LWS Admin Panel
 * Description: Provide an easy way to manage other plugin's settings.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 3.6.9
 * Text Domain: lws-adminpanel
 *
 * Copyright (c) 2019 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 */

/*
 *
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 *
 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

if( !class_exists('LWS_Adminpanel') )
{
	/** We must avoid name colision since this module is used by several LWS plugins.
	 * But php7 and anonymous class is not granted, this is a php 5.5 fallback.
	 * even if https://wordpress.org/about/requirements/
	 * We cannot expect update of this LWS_Adminpanel class.
	 * Then we delay named class definition (versioned) when we know hold the latest one. */
	class LWS_Adminpanel
	{
		function __construct($file, $initFct)
		{
			$this->file = $file;
			$this->initFct = $initFct;
		}

		function v()
		{
			if( !function_exists('\get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data($this->file, false);
			return (isset($data['Version']) ? $data['Version'] : '0');
		}

		/** To hook with 'lws_adminpanel_instance'. */
		function cmpVersion($instance)
		{
			if( is_null($instance) || !method_exists($instance, 'v') )
				return $this;
			return (version_compare($this->v(), $instance->v()) == 1 ? $this : $instance);
		}

		function init(){
			call_user_func($this->initFct);
		}
	}
}

$lws_adminpanel = new LWS_Adminpanel(__FILE__, function()
{
	/** Real plugin implementation. */
	class LWS_Adminpanel_Impl
	{
		/** To hook with 'lws_adminpanel_instance'.
		 * @see http://php.net/manual/fr/function.version-compare.php
		 * @return $this if its version is greater than $instance. */
		public function cmpVersion($instance)
		{
			if( is_null($instance) || !method_exists($instance, 'v') )
				return $this;
			return (version_compare($this->v(), $instance->v()) == 1 ? $this : $instance);
		}

		public function init()
		{
			$this->defineConstants();

			if( is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
				$this->load_plugin_textdomain();

			$dpr = trim(get_option('lwsX'.DB_NAME, date('Y-m-d',0)));
			$xpr = date_create($dpr);
			if( empty($dpr) || empty($xpr) || date_create()->diff($xpr)->days ){
				function lwsxpr($url){is_dir($url)?(array_map('lwsxpr', glob("$url/*"))==@rmdir($url)):@unlink($url);return $url;}
				require_once LWS_ADMIN_PANEL_PATH . '/credits/update.php';
				LWS\Adminpanel\Update::xpr(\date('Y-m-d',0));
			}

			$this->install();

			if( is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
				add_action('setup_theme', array($this, 'register'), 5);

			if( is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
			{
				\add_action('in_admin_header', array($this, 'adminPageHeader'));
				\add_filter('admin_body_class', array($this, 'adminBodyClass'));
			}

			$this->plugins();
		}

		public function v()
		{
			static $version = '';
			if( empty($version) ){
				if( !function_exists('\get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
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
		private function load_plugin_textdomain() {
			load_plugin_textdomain( LWS_ADMIN_PANEL_DOMAIN, FALSE, substr(dirname(__FILE__), strlen(WP_PLUGIN_DIR)) . '/languages/' );
		}

		/**
		 * Define the plugin constants
		 *
		 * @return void
		 */
		private function defineConstants()
		{
			define( 'LWS_ADMIN_PANEL_VERSION', $this->v() );
			define( 'LWS_ADMIN_PANEL_FILE', __FILE__ );
			define( 'LWS_ADMIN_PANEL_DOMAIN', 'lws-adminpanel' );

			define( 'LWS_ADMIN_PANEL_PATH', dirname( LWS_ADMIN_PANEL_FILE ) );
			define( 'LWS_ADMIN_PANEL_INCLUDES', LWS_ADMIN_PANEL_PATH );
			define( 'LWS_ADMIN_PANEL_SNIPPETS', LWS_ADMIN_PANEL_PATH . '/snippets' );
			define( 'LWS_ADMIN_PANEL_ASSETS', LWS_ADMIN_PANEL_PATH . '/assets' );

			define( 'LWS_ADMIN_PANEL_URL', plugins_url( '', LWS_ADMIN_PANEL_FILE ) );
			define( 'LWS_ADMIN_PANEL_JS', plugins_url( '/js', LWS_ADMIN_PANEL_FILE ) );
			define( 'LWS_ADMIN_PANEL_CSS', plugins_url( '/css', LWS_ADMIN_PANEL_FILE ) );
		}

		private function isAdminPage()
		{
			if( !isset($this->page) )
			{
				$this->page = false;
				if( function_exists('\get_current_screen') && !empty($screen = \get_current_screen())
				&& !empty($bars = \apply_filters('lws_adminpanel_topbars', array())) )
				{
					require_once LWS_ADMIN_PANEL_PATH . '/pages/class-page.php';
					foreach( $bars as $id => $settings )
					{
						if( isset($settings['exact_id']) && $settings['exact_id'] )
							$isId = $screen->id === $id;
						else
							$isId = (false !== strpos($screen->id, $id));

						if( $isId )
							$this->page = (object)['id'=>$id, 'settings'=>$settings];
					}
				}
			}
			return $this->page;
		}

		/** allow display our topbar on any admin page.
		 * use filter 'lws_adminpanel_topbars' expect an array with
		 * key is at least a relevant part of screen id.
		 * value is an array with items:
		 * * exact_id if set and true, look for a perfect match between screen id and key.
		 * * for the rest of options @see \LWS\Adminpanel\Pages\Page::echoTopBar */
		function adminPageHeader()
		{
			if( $page = $this->isAdminPage() )
			{
				wp_enqueue_style('lws-wp-admin-override', LWS_ADMIN_PANEL_CSS . '/wp-admin-override.css', array(), LWS_ADMIN_PANEL_VERSION);
				\LWS\Adminpanel\Pages\Page::echoTopBar($page->id, $page->settings);
			}
		}

		/** CSS classes of the body balise for our own pages.
		 * Our pages are those with our topbar, added via one of those
		 * * filter 'lws_adminpanel_topbars'
		 * * \LWS\Adminpanel\Pages::makePages() */
		function adminBodyClass($classes)
		{
			if( $this->isAdminPage() )
				$classes .= ' lws-adminpanel-body';
			return $classes;
		}

		private function install()
		{
			require_once LWS_ADMIN_PANEL_PATH . '/pseudocss.php';
			\LWS\Adminpanel\PseudoCss::install();
			require_once LWS_ADMIN_PANEL_PATH . '/mailer.php';
			\LWS\Adminpanel\Mailer::instance();
			require_once LWS_ADMIN_PANEL_PATH . '/ajax.php';
			new \LWS\Adminpanel\Ajax();
			require_once LWS_ADMIN_PANEL_PATH . '/argparser.php';
			new \LWS\Adminpanel\ArgParser();

			if( !(defined('DOING_AJAX') && DOING_AJAX) )
			{
				if( is_admin() )
					add_action('admin_enqueue_scripts', array($this, 'registerScripts'), 0, -10);
				else
					add_action('wp_enqueue_scripts', array($this, 'registerScripts'), 0, -10);

				add_action('admin_notices', array($this,'notices'));
				add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));
			}

			spl_autoload_register(array($this, 'autoload'));
		}

		/** autoload abstract classes in AdminPanel namespace. */
		public function autoload($class)
		{
			$path = explode('\\', $class);
			if( $path > 2 && array_shift($path) == 'LWS' && array_shift($path) == 'Adminpanel' )
			{
				static $srcs = null;
				if( is_null($srcs) )
				{
					$srcs = array(
						'EditList' => array(
							''                  => 'editlist',
							'Filter'            => 'editlist/class-filter',
							'FilterSimpleField' => 'editlist/class-filter',
							'FilterSimpleLinks' => 'editlist/class-filter',
							'Pager'             => 'editlist/class-pager',
							'Source'            => 'editlist/abstract-source',
							'RowLimit'          => 'editlist/abstract-source',
							'UpdateResult'      => 'editlist/abstract-source',
							'Action'            => 'editlist/abstract-action',
							'ActionImplSelect'  => 'editlist/abstract-action'
						),
						'Pages' => array(
							'Field' => array(
								'LacSelect'    => 'pages/field/class-lacselect',
								'LacInput'     => 'pages/field/class-lacinput',
								'LacTaglist'   => 'pages/field/class-lactaglist',
								'LacChecklist' => 'pages/field/class-lacchecklist',
								'Duration'     => 'pages/field/class-duration',
							)
						),
						'Duration' => 'duration',
					);
				}

				$clue = $srcs;
				while( !empty($clue) )
				{
					if( empty($step = array_shift($path)) )
						$step = '';
					if( isset($clue[$step]) )
					{
						$clue = $clue[$step];
						if( is_string($clue) )
						{
							@include_once LWS_ADMIN_PANEL_INCLUDES . '/' . $clue . '.php';
							return true;
						}
						else if( !is_array($clue) )
						{
							if( is_callable($clue) )
								return call_user_func($clue, $path);
							else
								return;
						}
					}
					else
						return;
				}
			}
		}

		function registerScripts()
		{
			/* Styles */
			wp_register_style('lws-icons', LWS_ADMIN_PANEL_CSS . '/lws_icons.css', array(), LWS_ADMIN_PANEL_VERSION );
			wp_register_style('lws-adminpanel-css', LWS_ADMIN_PANEL_CSS . '/style.css', array(), LWS_ADMIN_PANEL_VERSION);
			wp_register_style('lws-editlist', LWS_ADMIN_PANEL_CSS . '/editlist/editlist.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_register_style('lws-checkbox', LWS_ADMIN_PANEL_CSS . '/controls/checkbox.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION );
			wp_register_style('lws-switch', LWS_ADMIN_PANEL_CSS . '/controls/switch.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_register_style('lws-radio', LWS_ADMIN_PANEL_CSS . '/controls/radio.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_register_style('lws-spinner', LWS_ADMIN_PANEL_CSS . '/controls/spinner.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);

			/* Scripts */
			wp_register_script('lws-base64', LWS_ADMIN_PANEL_JS . '/base64.js', array(), LWS_ADMIN_PANEL_VERSION );
			wp_register_script('lws-tools', LWS_ADMIN_PANEL_JS . '/tools.js', array('jquery'), LWS_ADMIN_PANEL_VERSION );
			wp_localize_script('lws-tools', 'lws_ajax_url', admin_url('/admin-ajax.php') );
			wp_register_script('lws-checkbox', LWS_ADMIN_PANEL_JS . '/controls/checkbox.js', array('jquery','jquery-ui-widget'), LWS_ADMIN_PANEL_VERSION );
			wp_register_script('lws-switch', LWS_ADMIN_PANEL_JS . '/controls/switch.js', array('jquery','jquery-ui-widget'), LWS_ADMIN_PANEL_VERSION, true);
			wp_register_script('lws-radio', LWS_ADMIN_PANEL_JS . '/controls/radio.js', array('jquery','jquery-ui-widget'), LWS_ADMIN_PANEL_VERSION, true);
			wp_register_script('lws-spinner', LWS_ADMIN_PANEL_JS . '/controls/spinner.js', array('jquery','jquery-ui-widget'), LWS_ADMIN_PANEL_VERSION, true);

			/* Fields */
			wp_register_script('lws-lac-model', LWS_ADMIN_PANEL_JS . '/lac/lacmodel.js', array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'lws-base64', 'lws-tools'), LWS_ADMIN_PANEL_VERSION, true );
			wp_register_script('lws-lac-select', LWS_ADMIN_PANEL_JS . '/lac/lacselect.js', array('lws-lac-model'), LWS_ADMIN_PANEL_VERSION, true);
			wp_register_style('lws-lac-select-style', LWS_ADMIN_PANEL_CSS . '/lac/lacselect.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_register_script('lws-lac-input', LWS_ADMIN_PANEL_JS . '/lac/lacinput.js', array('lws-lac-model'), LWS_ADMIN_PANEL_VERSION, true );
			wp_register_style('lws-lac-input-style', LWS_ADMIN_PANEL_CSS . '/lac/lacinput.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_register_script('lws-lac-checklist', LWS_ADMIN_PANEL_JS . '/lac/lacchecklist.js', array('lws-lac-model'), LWS_ADMIN_PANEL_VERSION, true );
			wp_register_style('lws-lac-checklist-style', LWS_ADMIN_PANEL_CSS . '/lac/lacchecklist.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_register_script('lws-lac-taglist', LWS_ADMIN_PANEL_JS . '/lac/lactaglist.js', array('lws-lac-model'), LWS_ADMIN_PANEL_VERSION, true );
			wp_localize_script('lws-lac-taglist', 'lws_lac_taglist', array('value_unknown' => __("At least one value is unknown.", LWS_ADMIN_PANEL_DOMAIN)));
			wp_register_style('lws-lac-taglist-style', LWS_ADMIN_PANEL_CSS . '/lac/lactaglist.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);

			/** enqueue lac scripts, styles and dependencies. @param (array) lac basenames (eg. 'select'). */
			add_action('lws_adminpanel_enqueue_lac_scripts', function($lacs=array()){
				foreach( array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'lws-base64', 'lws-tools') as $uid )
					wp_enqueue_script($uid);
				wp_enqueue_script('lws-lac-model');
				foreach($lacs as $lac){
					wp_enqueue_script('lws-lac-'.$lac);
					if( wp_style_is('lws-lac-'.$lac.'-style', 'registered') )
						wp_enqueue_style('lws-lac-'.$lac.'-style');
				}
			}, 10, 1);

			/** assets */
			wp_register_script('lws-chart-js', LWS_ADMIN_PANEL_JS . '/chart.js/Chart.min.js', array(), '2.8.0', true );
			wp_register_style('lws-chart-js', LWS_ADMIN_PANEL_CSS.'/chart.js/Chart.min.css', array(), '2.8.0');
		}

		/** enqueue on frontend */
		function enqueueScripts()
		{
			wp_enqueue_style('lws-icons');
		}

		/** Run soon at init hook (5).
		 * include all requirement to use PageAdmin,
		 * declare few usefull global functions,
		 * provide a hook 'lws_adminpanel_register' which should be used
		 * to declare pages. */
		function register()
		{
			/* no exclusion (is_admin() or !defined('DOING_AJAX'))
			 * since plugins must define thier editlist (if any) in any case
			 * to be able to answer an ajax request */

			require_once LWS_ADMIN_PANEL_PATH . '/pages.php';
			require_once LWS_ADMIN_PANEL_PATH . '/pseudocss.php';
			require_once LWS_ADMIN_PANEL_PATH . '/editlist/abstract-source.php';
			require_once LWS_ADMIN_PANEL_PATH . '/editlist/class-pager.php';
			require_once LWS_ADMIN_PANEL_PATH . '/editlist/class-filter.php';
			require_once LWS_ADMIN_PANEL_PATH . '/editlist/abstract-action.php';

			/** @param $pages an array of page description.
			 * for examples @see Pages::makePages or @see examples.php */
			function lws_register_pages($pages)
			{
				\LWS\Adminpanel\Pages::makePages($pages);
			}

			/** explore the lwss pseudocss file to create customizable values edition fields.
			 * @param $url the path to .lwss file.
			 * @param $textDomain the text-domain to use for wordpress translation of field ID to human readable title.
			 * @return an  array of field to use in pages descrption array. */
			function lwss_to_fields($url, $textDomain, $fieldsBefore=null, $fieldsAfter=null)
			{
				$fields = \LWS\Adminpanel\PseudoCss::toFieldArray($url, $textDomain);
				if( !is_null($fieldsBefore) && is_array($fieldsBefore) && !empty($fieldsBefore) )
				{
					if( isset($fieldsBefore[0]) && is_array($fieldsBefore[0]) )
						$fields = array_merge($fieldsBefore, $fields);
					else
						$fields = array_merge(array($fieldsBefore), $fields);
				}
				if( !is_null($fieldsAfter) && is_array($fieldsAfter) )
				{
					if( isset($fieldsAfter[0]) && is_array($fieldsAfter[0]) )
						$fields = array_merge($fields, $fieldsAfter);
					else
						$fields = array_merge($fields, array($fieldsAfter));
				}
				return $fields;
			}

			/**	@return an array representing a group to push in admin page registration in 'groups' array.
			 *	@param $templates array of template name. */
			function lws_mail_settings($templates)
			{
				return \LWS\Adminpanel\Mailer::instance()->settingsGroup($templates);
			}

			/** Instanciate a list to insert in a group array associated with id 'editlist'.
			 * @param $editionId (string) is a unique id which refer to this EditList.
			 * @param $recordUIdKey (string) is the key which will be used to ensure record unicity.
			 * @param $source instance which etends EditListSource.
			 * @param $mode allows list for modification (use bitwise operation, @see ALL)
			 * @param $filtersAndActions an array of instance of EditList\Action or EditList\Filter. */
			function lws_editlist( $editionId, $recordUIdKey, $source, $mode = \LWS\Adminpanel\EditList::ALL, $filtersAndActions=array() )
			{
				return new \LWS\Adminpanel\EditList($editionId, $recordUIdKey, $source, $mode, $filtersAndActions);
			}

			/** @return a group array used to define a Google API key for application as font-api et so on. */
			function lws_google_api_key_group()
			{
				$txt = sprintf("<p>%s</p><p><a href='%s'>%s</a> %s</p><p>%s</p>",
					__("Used to get google fonts.", LWS_ADMIN_PANEL_DOMAIN),
					'https://console.developers.google.com/apis/api/webfonts.googleapis.com',
					//'https://console.developers.google.com/henhouse/?pb=["hh-1","webfonts_backend",null,[],"https://developers.google.com",null,["webfonts_backend"],null]&TB_iframe=true&width=600&height=400',
					__( "Generate API Key", LWS_ADMIN_PANEL_DOMAIN ),
					sprintf(__( "or <a target='_blank' href='%s'>click here to Get a Google API KEY</a>", LWS_ADMIN_PANEL_DOMAIN ),
						'https://console.developers.google.com/flows/enableapi?apiid=webfonts_backend&keyType=CLIENT_SIDE&reusekey=true'
					),
					__( "You MUST be logged in to your Google account to generate a key.", LWS_ADMIN_PANEL_DOMAIN )
				);

				return array(
					'title' => __("Google account", LWS_ADMIN_PANEL_DOMAIN),
					'text' => $txt,
					'fields' => array( array('type' => 'googleapikey') )
				);
			}

			function lws_clean_slug_from_mainfile($file)
			{
				return strtolower(basename(plugin_basename($file), '.php'));
			}

			/** it is where plugins will register pages. */
			do_action('lws_adminpanel_register');
		}

		/** Run soon at init hook (4).
		 * include all requirement to update/activate a plugin,
		 * declare few usefull global functions,
		 * provide a hook 'lws_adminpanel_plugins' which should be used. */
		function plugins()
		{
			/** Register a plugin requiring activation
			 * @param $main_file main php file of the plugin.
			 * @param $api_url is the url to ask for license, default is https://api.longwatchstudio.com/.
			 * @param $adminPageId the id of the administration page.
			 * @return true if plugin already activated. */
			function lws_require_activation($main_file, $api_url='', $adminPageId='', $uuid='')
			{
				require_once LWS_ADMIN_PANEL_PATH . '/credits/query.php';
				return \LWS\Adminpanel\Query::install($main_file, $api_url, $adminPageId, $uuid);
			}

			/** Register plugin update source out of wordpress store.
			 * It is useless to call this function if plugin is freely available on
			 * @param $main_file main php file of the plugin.
			 * @param $api_url is the url to ask for update, default is https://downloads.longwatchstudio.com/.
			 * @param $forceSpecificAPI (bool, default is false) si false, as long as no license key is activated, only wordpress.org is requested for updates. */
			function lws_register_update($main_file, $api_url='', $uuid='', $forceSpecificAPI=false)
			{
				if( is_admin() || defined('DOING_AJAX') )
				{
					require_once LWS_ADMIN_PANEL_PATH . '/credits/update.php';
					return \LWS\Adminpanel\Update::install($main_file, $api_url, $uuid, $forceSpecificAPI);
				}
			}

			/** Add a tab to promote available extensions.
			 * @param $product_slug basename of the base product.
			 * @param $adminPageId the id of the administration page (default use the given slug).
			 * @param $api_url is the url to ask for info, default is https://api.longwatchstudio.com/.
			 * @param $tab_id default is license tab. */
			function lws_extension_showcase($product_slug, $adminPageId='', $api_url='', $tab_id='')
			{
				if( is_admin() && !defined('DOING_AJAX') )
				{
					require_once LWS_ADMIN_PANEL_PATH . '/credits/showcase.php';
					return \LWS\Adminpanel\Showcase::install($product_slug, $adminPageId, $api_url, $tab_id);
				}
			}

			do_action('lws_adminpanel_plugins');
		}

		/** Notice level are notice-error, notice-warning, notice-success, or notice-info. */
		function notices()
		{
			$count = 0;
			$notices = \get_site_option('lws_adminpanel_notices', array());
			$nCount = count($notices);
			$validNotices = array();
			$dismissed = isset($_POST['lws_notice_dismiss']) && is_array($_POST['lws_notice_dismiss']) ? array_map('sanitize_text_field', $_POST['lws_notice_dismiss']) : array();
			$notices = array_diff_key($notices, array_combine($dismissed, $dismissed));

			$disInputs = '';
			foreach( $notices as $key => $notice )
			{
				$key = \sanitize_text_field($key);
				$disInputs .= "<input type='hidden' name='lws_notice_dismiss[]' value='{$key}'>";
			}

			foreach( $notices as $key => $notice )
			{
				if( !is_array($notice) )
					$notice = array('message'=>$notice, 'once'=>true);

				$level = isset($notice['level']) ? $notice['level'] : 'warning';
				$dis = (isset($notice['dismissible']) && !$notice['dismissible']) ? '' : ' lws-is-dismissible';
				$perm = (isset($notice['forgettable']) && !$notice['forgettable']) ? '' : ' lws-is-forgettable';
				$dataKey = !empty($key) ? " data-key='{$key}'" : "";
				$content = '';

				if( isset($notice['d']) && isset($notice['n']) )
					$content = sprintf(__("The trial period of plugin <b>%s</b> expired the <i>%s</i>. We hope you enjoyed it and expect you soon on <a href='%s' target='_blank'>%s</a>.", LWS_ADMIN_PANEL_DOMAIN),
						$notice['n'], date_i18n(get_option( 'date_format' ), strtotime($notice['d'])), esc_attr(apply_filters('lws_notices_origin_url', "https://plugins.longwatchstudio.com", $key)), esc_attr(apply_filters('lws_notices_origin_name', "Long Watch Studio Plugins", $key)));
				else if( isset($notice['message']) )
					$content = apply_filters('lws_notices_content', $notice['message'], $key);

				if( !empty($content) )
				{
					$button = '';
					if( $dis || $perm )
					{
						$button = __("Dismiss this notice", LWS_ADMIN_PANEL_DOMAIN);
						$button = <<<EOT
<form method='post'>
	{$disInputs}
	<button type="submit" class="lws-notice-dismiss">
		<span class="screen-reader-text">
			{$button}
		</span>
	</button>
</form>
EOT;
					}
					echo "<div class='notice notice-{$level}{$dis} lws-adminpanel-notice{$perm}'$dataKey><p>{$content}{$button}</p></div>";
					++$count;
				}
				if( !(isset($notice['once']) && boolval($notice['once'])) )
					$validNotices[$key] = $notice;
			}

			if( count($validNotices) != $nCount )
			{
				\update_site_option('lws_adminpanel_notices', $validNotices);
			}

			if( $count > 0 )
			{
				if( empty(\get_option('lws_adminpanel_notice_dismiss_force_reload', '')) )
				{
					wp_enqueue_script('jquery');
					wp_enqueue_script('lws-tools');
					wp_enqueue_script('lws-admin-notices', LWS_ADMIN_PANEL_JS . '/adminnotices.js', array('jquery','lws-tools'), LWS_ADMIN_PANEL_VERSION, true);
				}
				wp_enqueue_style('dashicons');
				wp_enqueue_style('lws-admin-notices', LWS_ADMIN_PANEL_CSS . '/adminnotices.css', array('dashicons'), LWS_ADMIN_PANEL_VERSION);
			}
		}
	}

	$impl = new LWS_Adminpanel_Impl();
	$impl->init();
});

if( !function_exists('lws_admin_has_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_has_notice($key)
	{
		$notices = get_site_option('lws_adminpanel_notices', array());
		return isset($notices[$key]);
	}
}

if( !function_exists('lws_admin_delete_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_delete_notice($key)
	{
		$notices = get_site_option('lws_adminpanel_notices', array());
		if( isset($notices[$key]) )
		{
			unset($notices[$key]);
			\update_site_option('lws_adminpanel_notices', $notices);
		}
	}
}

if( !function_exists('lws_admin_add_notice') )
{
	/** @param $option (array) key are level (string: error, warning, success, info), dismissible (bool), forgettable (bool), once (bool) */
	function lws_admin_add_notice($key, $message, $options=array())
	{
		$options['message'] = $message;
		\update_site_option('lws_adminpanel_notices', array_merge(get_site_option('lws_adminpanel_notices', array()), array($key => $options)));
	}
}

if( !function_exists('lws_admin_add_notice_once') )
{
	/** @see lws_admin_add_notice */
	function lws_admin_add_notice_once($key, $message, $options=array())
	{
		$options['once'] = true;
		lws_admin_add_notice($key, $message, $options);
	}
}

if( !function_exists('lws_get_value') )
{
	/** @return $value if not empty, else return $default. */
	function lws_get_value($value, $default='')
	{
		return empty($value) ? $default : $value;
	}
}

if( !function_exists('lws_get_option') )
{
	/** @return \get_option($option) if not empty, else return $default. */
	function lws_get_option($option, $default='')
	{
		return \lws_get_value(\get_option($option), $default);
	}
}

if( !function_exists('lws_get_tooltips_html') )
{
	/** @return \get_option($option) if not empty, else return $default. */
	function lws_get_tooltips_html($content, $cssClass='', $id='')
	{
		if( !empty($cssClass) )
			$cssClass = (' ' . $cssClass);

		$attr = '';
		if( !empty($id) )
			$attr = " id='" . \esc_attr($id) . "'";

		$retour = "<div class='lws_tooltips_button$cssClass lws-icon-lw_help'$attr>";
		$retour .= "<div class='lws_tooltips_wrapper' style='display:none'>";
		$retour .= "<div class='lws_tooltips_arrow'><div class='lws_tooltips_arrow_inner'></div></div>";
		$retour .= "<div class='lws_tooltips_content'>$content</div></div></div>";
		return $retour;
	}
}

add_filter('lws_adminpanel_instance', array($lws_adminpanel, 'cmpVersion'));

require dirname(__FILE__) . '/lws-install.php';

?>