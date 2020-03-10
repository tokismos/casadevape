<?php
namespace LWS\Adminpanel;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Pages") ) :

require_once dirname( __FILE__ ).'/pages/class-page.php';

/** @brief Create admin pages and menus.
 *
 * Needs a description as array of pages array.
 * The first page is the main one, following page in the array are assumed to be submenu.
 * In commun way, you only need makePages static function.
 * @see makePages() to add pages
 * @see pageFormat() for array details
 *
 * Example:
 * @code
\LWS\Adminpanel\Pages::makePages(
	array(
		array(
			'id' => "toto", // id of the page
			'title' => __("Toto va au marché"),
			'rights' => 'manage_options', // acces restriction to visit the page
			'dashicons' => 'dashicons-toto', // the css class which define content with icon
			'groups' => array(
				array(
					'id' => "poisson", // id of the group
					'title' => __("Marché aux poissons"),
					'text' => __("Il est frais!"), // optional
					'function' => 'any_callable', // optionnal, allows add free features, about callable see http://php.net/manual/fr/language.types.callable.php
					'fields' => array(
						array(
							'id' => "path", // this value can be get by get_option(id);
							'title' => __("Adresse"),
							'type' => 'URL'
						),
						array(
							'id' => "name",
							'title' => __("Nom"),
							'type' => 'text'
						)
					)
				)
			)
		),
		array(
			'id' => "aide",
			'title' => __("Aide"),
			'rights' => 'edit_posts',
			'text' => __("Pas besoin d'aide, Toto sait comment aller au marché.") // optional
		)
	)
);
 * @endcode */
class Pages
{

	/** Take an array to build a set of pages. */
	public static function makePages( $pagesArray )
	{
		if( !is_array($pagesArray) )
			return Pages::error(\get_class() . ":" . __FUNCTION__ . "(Argument must be an array.)");
		return new Pages($pagesArray);
	}

	protected function __construct( $pages )
	{
		$this->doingAjax = (defined('DOING_AJAX') && DOING_AJAX);
		$this->pageInstances = array();
		$this->pages = array();
		if( is_array($pages) && !empty($first = reset($pages)) )
		{
			if( isset($first['id']) && !empty($first['id']) )
				$pages = \apply_filters('lws_adminpanel_pages_'.$first['id'], $pages);
		}

		foreach( $pages as $page )
		{
			if( is_array($page) && isset($page['id']) && !empty($page['id']) )
			{
				$id = $page['id'];
				$this->pages[$id] = apply_filters('lws_adminpanel_make_page_' . $id, $page, self::isCurrentPage($id, $this->doingAjax));
			}
			else
			{
				error_log(\get_class() . ": A page is set without ['id']");
				error_log("\nRead ===>\n" . print_r($pages, true));
				error_log("\nExpect ===>\n" . print_r(\LWS\Adminpanel\Pages\Page::format(), true));
			}
		}

		if( $this->doingAjax )
		{
			add_action('wp_ajax_lws_adminpanel_field_button', array($this, 'ajaxButton'));
		}
		else if( is_admin() )
		{
			add_action('admin_menu', array($this, 'registerMenus'));
			add_action('admin_init', array($this, 'buildPage'));
			add_action('admin_notices', array($this, 'hookNotice'));
			add_action('admin_enqueue_scripts', array($this, 'hookCss'));
			add_action('admin_enqueue_scripts', array($this, 'hookJs'));
			add_action('admin_head', array($this, 'submenuUpdate'));
		}

		/// Provided to allows enqueue our scripts out of standard settings pages.
		if( !has_filter('lws_enqueue_admin_scripts') )
			add_filter('lws_enqueue_admin_scripts', array($this, 'enqueueScripts'));
	}

	/** Remove entry point for singular edition script.
	 * Rename entry point for pages with subtitle. */
	function submenuUpdate()
	{
		if( !empty($this->pages) )
		{
			global $submenu;
			$mainKey = array_keys($this->pages)[0];

			if( isset($this->pages[$mainKey]['hidden']) && boolval($this->pages[$mainKey]['hidden']) )
			{
				\remove_menu_page($mainKey);
			}
			else if( isset($submenu[$mainKey]) )
			{
				$keyIndex = 2;

				foreach( array_reverse(array_keys($submenu[$mainKey])) as $i ) // reverse walk since we could remove some.
				{
					if( count($submenu[$mainKey][$i]) > $keyIndex ) // WordPress does not change its mind.
					{
						$id = $submenu[$mainKey][$i][$keyIndex];

						if( isset($this->pages[$id]) )
						{
							// found the menu point, found the page. Should we update something?
							$values = $this->pages[$id];

							if( isset($values['hidden']) && boolval($values['hidden']) )
							{
								\remove_submenu_page($mainKey, $id);
							}
							else if( isset($values['subtitle']) && !empty($values['subtitle']) )
							{
								$submenu[$mainKey][$i][0] = $values['subtitle'];
							}
						}
					}
				}

				if( empty($submenu[$mainKey]) )
					unset($submenu[$mainKey]);
			}
		}
	}

	/** Insert our page menu entry in WordPress lateral admin menu.
	 * Hooked in 'admin_menu' */
	function registerMenus()
	{
		$first = null;
		foreach($this->pages as $id => $page)
		{
			if( $this->isOurPage($id) && self::test($page, Pages\Page::format(), $id) )
			{
				if( !isset($page['rights']) || current_user_can($page['rights']) )
					$this->pageInstances[$id] = (new Pages\Page($id, $page, $first))->registerMenu();
			}

			if( empty($first) )
				$first = $id;
		}
	}

	/** Register fields (to options.php) for the active page if it belong to this.
	 * Hooked in 'admin_init' */
	function buildPage()
	{
		$id = $this->currentPage();
		if( $this->isOurPage($id) && isset($this->pages[$id]) && isset($this->pageInstances[$id]) )
		{
			$page = $this->pages[$id];
			if( !isset($page['rights']) || current_user_can($page['rights']) )
				$this->pageInstances[$id]->build();
		}
	}

	/** test an array against a format.
	 * If an error is detected, it is sent to error_log().
	 * @return true if format is respeced.
	 * @param $array the array to test.
	 * @param $format an array of format array @see format().
	 * @param $id help indicated error source in log in case of error. */
	public static function test($array, $format, $id)
	{
		if( !is_array($array) )
		{
			error_log("Error near '$id' : expect an array");
			return false;
		}

		if( isset($array['id']) ) $errId = "$id ... {$array['id']}";
		else if( isset($array['title']) ) $errId = "$id ... /{$array['title']}/";
		else $errId = $id;

		foreach($format as $k => $f)
		{
			if( isset($array[$k]) )
			{
				$error = false;
				$type = $f['type'];
				if( $type == 'string' ) $error = !is_string($array[$k]);
				else if( $type == 'int' ) $error = !is_numeric($array[$k]);
				else if( $type == 'bool' ) $error = !is_bool($array[$k]);
				else if( $type == 'array' ) $error = !is_array($array[$k]);
				else if( $type == 'callable' ) $error = !is_callable($array[$k]);
				else if( substr($type, 0, 6) == 'class:' ) $error = !is_a($array[$k], substr($type, 6));
				if( $error )
				{
					error_log("Error near '$errId' : wrong item type " . print_r($f, true));
					return false;
				}
			}
			else if( !boolval($f['optional']) )
			{
				error_log("Error near '$errId' : missing item " . print_r($f, true));
				return false;
			}
		}

		foreach($array as $k => $v)
		{
			if( !isset($format[$k]) && !in_array($k, array('activated', 'slug')) )
			{
				error_log("Error near '$errId' : unknow item '$k'.\nExpect " . print_r($format, true));
				return false;
			}
		}
		return true;
	}

	/** @return a well formated format subarray for test()
	 * @see test */
	public static function format($key, $optional, $type, $description, $children=null)
	{
		$ar = array(
			'key' => $key,
			'optional' => $optional,
			'type' => $type,
			'description' => $description
		);
		if( !empty($children) )
			$ar['children'] = $children;
		return $ar;
	}

	/** Provide to cleanup wordpress database when user uninstall the plugin.
	* Use it in register_uninstall_hook callback.
	* This will look for each field to delete the option in database.
	* @param $pagesArray the same array you gave to makePages.
	*/
	public static function uninstallPages( $pagesArray )
	{
		foreach( $pagesArray as $key => $val )
		{
			if( $key == 'fields' )
			{
				foreach( $val as $field )
				{
					if( is_array($field) && isset($field['id']) && !empty($field['id']) )
						delete_option($field['id']);
				}
			}
			else if( is_array($val) )
				uninstallPages($val);
		}
	}

	/** Provide to cleanup wordpress database when user uninstall the plugin.
	* Use it in register_uninstall_hook callback.
	* Use this if you build a Pages instance yourself instead using the makePages function with an array.
	*/
	public function uninstall()
	{
		self::uninstallPages($this->pages);
	}

	/** Try to find out the asking page and return if it is $pageId.
	 * Works only for AdminPanel pages.
	 * If it cannot be guessed, return $unknow */
	public static function isCurrentPage($pageId, $unknow=false)
	{
		$current = self::currentPage();
		if( !is_null($current) )
			return $current == $pageId;
		else
			return $unknow;
	}

	/** Try to find out the currently displayed admin page. */
	public static function currentPage()
	{
		static $lws_adminpanel_page = null;
		if( is_null($lws_adminpanel_page) )
		{
			if( isset($_GET['page']) )
				$lws_adminpanel_page = \sanitize_text_field($_GET['page']);
			else if( isset($_POST['option_page']) )
				$lws_adminpanel_page = \sanitize_text_field($_POST['option_page']);
			else if( function_exists('\get_current_screen') && !empty($screen = \get_current_screen()) )
				$lws_adminpanel_page = $screen->id;
		}
		return $lws_adminpanel_page;
	}

	/** @return if the given pageId belong to this Pages. */
	protected function isOurPage($pageId)
	{
		if( !in_array($pageId, $this->wordPressPages()) && isset($this->pages[$pageId]) )
		{
			$page = $this->pages[$pageId];
			return !(isset($page['prebuild']) && boolval($page['prebuild']));
		}
		return false;
	}

	/** It is possible to insert subpage in WP official page by setting a faky one
	 *	as first page in the array with the wordpress ID of that page. */
	private function wordPressPages()
	{
		return array(
			'index.php',
			'index',
			'users.php',
			'users',
			'profile.php',
			'profile',
			'plugins.php',
			'plugins',
			'themes.php',
			'themes',
			'edit-comments.php',
			'edit-comments',
			'edit.php?post_type=page',
			'upload.php',
			'upload',
			'edit.php',
			'edit',
			'tools.php',
			'tools',
			'options-general.php',
			'options-general'
		);
	}

	public function hookNotice()
	{
		$page = self::currentPage();
		if( $this->isOurPage($page) )
		{
			$done = array();
			foreach( $this->pageInstances as $P )
			{
				if( $P->getId() == $page )
				{
					foreach( $P->getFields() as $f )
					{
						foreach( $f->Notices as $k => $notice)
						{
							if( !in_array($k, $done) )
							{
								$done[] = $k;
								echo "<div id='setting-error-$k' class='notice notice-warning is-dismissible'><p>$notice</p></div>";
							}
						}
					}
				}
			}
		}
		return false;
	}

	public function hookCss($hook)
	{
		wp_enqueue_style('lws-icons');
		wp_enqueue_style('lws-adminpanel-css');

		if( $this->isOurPage(self::currentPage()) )
		{
			wp_enqueue_style('lws-editlist');
			wp_enqueue_style('lws-adminpanel-pseudocss', LWS_ADMIN_PANEL_CSS . '/pseudocss.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_enqueue_style('lws-adminpanel-colorselector', LWS_ADMIN_PANEL_CSS . '/controls/colorselector.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_enqueue_style('lws-adminpanel-colorpicker', LWS_ADMIN_PANEL_CSS . '/controls/colorpicker.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_enqueue_style('lws-adminpanel-fontselector', LWS_ADMIN_PANEL_CSS . '/fontselector.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_enqueue_style('lws-adminpanel-stygen', LWS_ADMIN_PANEL_CSS . '/stygen.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_enqueue_style('lws-adminpanel-themer', LWS_ADMIN_PANEL_CSS . '/controls/themer.css', array('lws-adminpanel-css'), LWS_ADMIN_PANEL_VERSION);
			wp_enqueue_media();

			\add_filter('admin_body_class', function($classes){return ($classes . ' lws-adminpanel-body');});
		}
	}

	private function enqueueScript($guid, $script, $dependencies=array(), $after=true, $localisations=array(), $jsVar='')
	{
		wp_enqueue_script( $guid, LWS_ADMIN_PANEL_JS . $script, $dependencies, LWS_ADMIN_PANEL_VERSION, $after );
		if( !empty($localisations) && !empty($jsVar) )
		{
			wp_localize_script( $guid, $jsVar, $localisations );
			wp_enqueue_script( $guid );
		}
	}

	/** @param $scriptFilters (array) empty means enqueue all or script, else enqueue only the one with the guid. */
	public function enqueueScripts($scriptFilters=array())
	{
		$todo = array();
		if( !empty($scriptFilters) )
		{
			foreach($scriptFilters as $script)
			{
				$todo[$script] = true;
				if( $script == 'lws-adminpanel-fields' )
					$todo['lws-adminpanel-autocomplete'] = true;
			}
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-widget' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-effects-slide' );
		wp_enqueue_script( 'jquery-ui-selectmenu' );

		wp_enqueue_script( 'lws-base64' );
		wp_enqueue_script( 'lws-tools' );

		if( empty($todo) || isset($todo['lws-adminpanel-admin']) )
			$this->enqueueScript( 'lws-adminpanel-admin', '/admin.js', array('jquery') );
		if( empty($todo) || isset($todo['lws-adminpanel-formfields']) )
			$this->enqueueScript( 'lws-adminpanel-formfields', '/formfields.js', array('jquery') );
		if( empty($todo) || isset($todo['lws-adminpanel-fontselector']) )
			$this->enqueueScript( 'lws-adminpanel-fontselector', '/fontselector.js', array('lws-tools', 'jquery') );

		if( empty($todo) || isset($todo['lws-adminpanel-pseudocss']) )
			$this->enqueueScript( 'lws-adminpanel-pseudocss', '/pseudocss.js', array('lws-tools', 'jquery') );
		if( empty($todo) || isset($todo['lws-adminpanel-colorselector']) )
			$this->enqueueScript( 'lws-adminpanel-colorselector', '/colorselector.js', array('jquery', 'jquery-ui-core', 'jquery-effects-slide') );

		if( empty($todo) || isset($todo['lws-adminpanel-fields']) )
		{
			wp_enqueue_script( 'jquery-ui-autocomplete' );
			$this->enqueueScript( 'lws-adminpanel-fields', '/fields.js', array('lws-tools', 'lws-base64', 'jquery', 'jquery-ui-autocomplete'), true,
				array(
					'confirmLeave' => __("Changes not commited.", LWS_ADMIN_PANEL_DOMAIN),
					'editlistOnHold' => __("Please confirm or cancel the active form before submit that page.", LWS_ADMIN_PANEL_DOMAIN),
					'confirmDel' => __("Do you really want to delete the line?", LWS_ADMIN_PANEL_DOMAIN),
					'updateAlert' => __("Update error, please check the values.", LWS_ADMIN_PANEL_DOMAIN),
					'triggerError' => __("An error occured, please try later.", LWS_ADMIN_PANEL_DOMAIN),
					'noSelection' => __("Please, select an item.", LWS_ADMIN_PANEL_DOMAIN),
					'fontPlaceHolder' => __("Select a font", LWS_ADMIN_PANEL_DOMAIN),
					'fontToggleMore' => __("Show more", "Font list", LWS_ADMIN_PANEL_DOMAIN),
					'fontToggleLess' => __("Show less", "Font list", LWS_ADMIN_PANEL_DOMAIN),
					'fontWeightTr' => array(
						'100' => _x("Thin", "Font weight", LWS_ADMIN_PANEL_DOMAIN),
						'200' => _x("Extra Light", "Font weight", LWS_ADMIN_PANEL_DOMAIN),
						'300' => _x("Light", "Font weight", LWS_ADMIN_PANEL_DOMAIN),
						'400' => _x("Normal", "Font weight", LWS_ADMIN_PANEL_DOMAIN),
						'regular' => _x("Normal", "Font weight", LWS_ADMIN_PANEL_DOMAIN),
						'500' => _x("Medium", "Font weight", LWS_ADMIN_PANEL_DOMAIN),
						'600' => _x("Semi Bold", "Font weight", LWS_ADMIN_PANEL_DOMAIN),
						'700' => _x("Bold", "Font weight", LWS_ADMIN_PANEL_DOMAIN),
						'800' => _x("Extra Bold", "Font weight", LWS_ADMIN_PANEL_DOMAIN),
						'900' => _x("Black", "Font weight", LWS_ADMIN_PANEL_DOMAIN)
					)
				), 'lws_adminpanel'
			);
		}

		if( empty($todo) || isset($todo['lws-adminpanel-autocomplete']) )
		{
			wp_enqueue_script( 'jquery-ui-tooltip' );
			wp_enqueue_script( 'jquery-ui-autocomplete' );
			$this->enqueueScript( 'lws-adminpanel-autocomplete', '/autocomplete.js', array('lws-tools', 'lws-base64', 'jquery','jquery-ui-tooltip','jquery-ui-autocomplete'), true,
				array(
					'notMatch'=>__(" didn't match any items", LWS_ADMIN_PANEL_DOMAIN),
					'btnTitle'=>__("Show All Items", LWS_ADMIN_PANEL_DOMAIN)
				), 'lws_autocomplete_localize'
			);
		}
	}

	public function hookJs($hook)
	{
		if( $this->isOurPage(self::currentPage()) )
			$this->enqueueScripts();
	}

	public function ajaxButton()
	{
		if( isset($_REQUEST['button']) && isset($_REQUEST['form']) )
		{
			$button = sanitize_key($_REQUEST['button']);
			if( empty($button) )
				exit(0);
			if( empty($_REQUEST['form']) )
				exit(0);
			$data = @base64_decode($_REQUEST['form']);
			if( $data === false )
				exit(0);
			$data = @json_decode( $data, true );
			if( $data === null )
				exit(0);

			$response = $this->trigAjaxButton($this->pages, $button, $data);
			if( !is_null($response) )
			{
				wp_send_json($response);
				exit();
			}
		}
	}

	/** recursive function, trigger all known ajax user fields (as button). */
	private function trigAjaxButton($tree, $button, $data)
	{
		foreach( $tree as $node )
		{
			if( is_array($node) )
			{
				if( isset($node['fields']) )
				{
					foreach($node['fields'] as $field)
					{
						if( isset($field['type']) && $field['type'] == 'button' && isset($field['id']) && $field['id'] == $button )
						{
							$response = array('status' => 0);
							$extra = (isset($field['extra']) && is_array($field['extra']) ? $field['extra'] : array());
							if( isset($extra['callback']) && is_callable($extra['callback']) )
							{
								$val = call_user_func( $extra['callback'], $button, $data );
								if( $val !== false )
								{
									$response['status'] = 1;
									if( is_string($val) )
										$response['data'] = $val;
								}
							}
							else
								$response['data'] = "No callback";
							return $response;
						}
					}
				}
				else
				{
					if( isset($node['groups']) )
					{
						if( !is_null($response = $this->trigAjaxButton($node['groups'], $button, $data)) )
							return $response;
					}
					if( isset($node['tabs']) )
					{
						if( !is_null($response = $this->trigAjaxButton($node['tabs'], $button, $data)) )
							return $response;
					}
				}
			}
		}
		return null;
	}

}

endif
?>