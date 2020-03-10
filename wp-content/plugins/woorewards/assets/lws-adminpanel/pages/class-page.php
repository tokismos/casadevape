<?php
namespace LWS\Adminpanel\Pages;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Page") ) :

require_once dirname( __FILE__ ).'/class-group.php';

/**  */
class Page
{
	public static $MaxTitleLength = 21;
	private $groups = array();
	const LIC_TAB_ID = 'license';
	const YOUTUBE = 'https://www.youtube.com/channel/UCM3iPTIcjnJzfEYxMo5hLvg';
	const CHAT    = 'https://discord.gg/rWudNxr';
	const MAILTO  = 'support@longwatchstudio.com';

	public function __construct($id, $data, $parent=null)
	{
		$this->groups = array();
		$this->id = urlencode($id);
		$this->data = $data;
		$this->master = $parent;
		$this->action = isset($this->data['action']) ? $this->data['action'] : 'options.php';
		$this->hiddenTab = false;

		$this->customBehavior = array();
		if( isset($this->data['function']) && is_callable($this->data['function']) )
			$this->customBehavior[] = $this->data['function'];

		$this->lateCustomBehavior = array();
		if( isset($this->data['delayedFunction']) && is_callable($this->data['delayedFunction']) )
			$this->lateCustomBehavior[] = $this->data['delayedFunction'];
	}

	/** @return a well formated format array for Pages::test()
		* @see Pages::test() */
	public static function format()
	{
		return array(
			'id'        => \LWS\Adminpanel\Pages::format('id',        false, 'string', "Identify a page"),
			'rights'    => \LWS\Adminpanel\Pages::format('rights',    false, 'string', "User capacity required to access to this page. Usually 'manage_options'. A tab could be locally more restrictive"),
			'title'     => \LWS\Adminpanel\Pages::format('title',     false, 'string', "Display at top of the page and in the menu."),
			'subtitle'  => \LWS\Adminpanel\Pages::format('subtitle',  true, 'string', "Display after title on top of the page and replace title in sub menu."),
			'text'      => \LWS\Adminpanel\Pages::format('text',      true, 'string', "A free text displayed at top of the page, after the title banner."),
			'subtext'   => \LWS\Adminpanel\Pages::format('subtext',   true, 'string', "A free text displayed after the tab line but before tab content."),
			'dashicons' => \LWS\Adminpanel\Pages::format('dashicons', true, 'string', "The URL to the icon to be used for this menu. Dashicons helper class, base64-encoded SVG or 'none' to let css do the work."),
			'action'    => \LWS\Adminpanel\Pages::format('action',    true, 'string', "Destination url of the page <form> ('action' attribute) if it must be different than 'options.php'"),
			'index'     => \LWS\Adminpanel\Pages::format('index',     true, 'int', "The position in the menu order this one should appear."),
			'prebuild'  => \LWS\Adminpanel\Pages::format('prebuild',  true, 'bool', "The first page of the page array is the main menu entry, following pages are assumed to be submenu. Set true for the first page of the page array (default is false) to ignore content, assuming it is an existant page created by wordpress or another plugin."),
			'toc'       => \LWS\Adminpanel\Pages::format('toc',       true, 'bool', "TableOfContent. The page should display a table of content (default is true). Can be overwrite by tab."),
			'nosave'    => \LWS\Adminpanel\Pages::format('nosave',    true, 'bool', "Hide the global 'Save options' button at bottom of admin page (default is false, so show the save button). Could be overwritten by tab."),
			'groups'    => \LWS\Adminpanel\Pages::format('groups',    true, 'array', "Array of group. A group contains fields. Each group has an entry in Table of Content."),
			'tabs'      => \LWS\Adminpanel\Pages::format('tabs',      true, 'array', "Array of tab. A tab could contain another tabs level. A tab sould contain groups."),
			'function'	=> \LWS\Adminpanel\Pages::format('function',  true, 'callable', "A function to echo a custom feature."),
			'delayedFunction'	=> \LWS\Adminpanel\Pages::format('delayedFunction',	true, 'callable', "Same as function but executed after usual fields display."),
			'singular_edit' => \LWS\Adminpanel\Pages::format('singular_edit',	true, 'array', "For single post editition purpose. Could replace regular admin page."),
			'hidden'    => \LWS\Adminpanel\Pages::format('hidden',    true, 'bool', "If true, this page has no menu entry, build a link with add_query_arg('page', /*this_page_id*/, admin_url('admin.php'))."),
		);
	}

	/** @return a well formated format array for Pages::test()
		* @see Pages::test() */
	public static function tabFormat()
	{
		return array(
			'title'    => \LWS\Adminpanel\Pages::format('title',    false, 'string', "Display to the user."),
			'id'       => \LWS\Adminpanel\Pages::format('id',       true, 'string', "Identify a tab"),
			'rights'   => \LWS\Adminpanel\Pages::format('rights',   true, 'string', "User capacity required to access to this tab. Usually 'manage_options'."),
			'action'   => \LWS\Adminpanel\Pages::format('action',   true, 'string', "Destination url of the page <form> ('action' attribute) if it must be different than 'options.php'"),
			'toc'      => \LWS\Adminpanel\Pages::format('toc',      true, 'bool', "TableOfContent. The page should display a table of content (default is true). Can be overwrite by tab."),
			'nosave'   => \LWS\Adminpanel\Pages::format('nosave',   true, 'bool', "Hide the global 'Save options' button at bottom of admin page (default is false, so show the save button). Could be overwritten by tab."),
			'groups'   => \LWS\Adminpanel\Pages::format('groups',   true, 'array', "Array of group. A group contains fields. Each group has an entry in Table of Content."),
			'tabs'     => \LWS\Adminpanel\Pages::format('tabs',     true, 'array', "Array of tab. A tab could contain another tabs level. A tab sould contain groups."),
			'function' => \LWS\Adminpanel\Pages::format('function',	true, 'callable', "A function to echo a custom feature."),
			'delayedFunction'	=> \LWS\Adminpanel\Pages::format('delayedFunction',	true, 'callable', "Same as function but executed after usual fields display."),
			'hidden'   => \LWS\Adminpanel\Pages::format('hidden',   true, 'bool', "If true, this tab does not appea in the menu, build a link with add_query_arg(array('page'=>/*this_page_id*/, 'tab'=>/*the_tab_path*/), admin_url('admin.php'))."),
		);
	}

	/** @return a well formated format array for Pages::test()
		* @see Pages::test() */
	public static function singularEditFormat()
	{
		return array(
			'form'   => \LWS\Adminpanel\Pages::format('form', false, 'callable', "Display the form content (content only, <form> DOM is up to class-page). The \$_REQUEST[\$key] value is repeated as argument to that callable. This function should return false if a problem occurs."),
			'save'   => \LWS\Adminpanel\Pages::format('save', true, 'callable', "Save any data set in form. The \$_REQUEST[\$key] value is repeated as argument to that callable. This function should return the singular id (value that will replace \$_REQUEST[\$key])."),
			'delete' => \LWS\Adminpanel\Pages::format('delete', true, 'callable', "Delete the singular. The \$_REQUEST[\$key] value is repeated as argument to that callable. This function should return false if a problem occurs."),
			'key'    => \LWS\Adminpanel\Pages::format('key', false, 'string', "We look at \$_REQUEST, the form will be displayed only if the key exists. Else we show the regular page.")
		);
	}

	/** Register entry in wordpress lateral admin menu.
	 * @return $this. */
	public function registerMenu()
	{
		if( is_null($this->master) )
		{
			$position = isset($this->data['index']) ? $this->data['index'] : null;
			$position = $this->getFreeMenuPosition($position);
			$this->pageId = add_menu_page($this->title(), $this->title(), $this->data['rights'], $this->id, array($this, 'page'), isset($this->data['dashicons']) ? $this->data['dashicons'] : '', $position);
		}
		else
			$this->pageId = add_submenu_page($this->master, $this->title(), $this->subtitle(), $this->data['rights'], $this->id, array($this, 'page'));
		if( isset($this->data['singular_edit']) )
			add_action('admin_enqueue_scripts', array($this, 'singularEnqueueScripts'));
		return $this;
	}

	function getFreeMenuPosition($index, $step = 0.000001)
	{
		if( !empty($index) )
		{
			global $menu;
			for( $occuped = array_keys($menu) ; in_array("$index", $occuped) ; $index += $step ); // empty for
			$index = "$index";
		}
		return $index;
	}

	private function title()
	{
		return $this->data['title'];
	}

	private function subtitle()
	{
		return isset($this->data['subtitle']) && !empty($this->data['subtitle']) ? $this->data['subtitle'] : $this->data['title'];
	}

	public function getId()
	{
		return $this->id;
	}

	/** singular_edit parameter set, valid and if key is defined, key exists in $_REQUEST */
	public function isSingularEdit()
	{
		if( isset($this->data['singular_edit']) )
		{
			if( !isset($this->testedSingularEdit) )
			{
				$this->testedSingularEdit = \LWS\Adminpanel\Pages::test($this->data['singular_edit'], self::singularEditFormat(), "{$this->id}['singular_edit']");

				$this->singularId = false;
				$this->singularKey = false;
				if( $this->testedSingularEdit && isset($this->data['singular_edit']['key']) && !empty($this->data['singular_edit']['key']) )
				{
					$this->singularKey = $this->data['singular_edit']['key'];
					if( true == ($this->testedSingularEdit = isset($_REQUEST[$this->singularKey])) )
						$this->singularId = \sanitize_key($_REQUEST[$this->singularKey]);
				}
			}

			return $this->testedSingularEdit;
		}
		return false;
	}

	/** Create instances of active groups and fields. */
	public function build()
	{
		if( $this->isSingularEdit() )
			$this->singularAction();
		else
		{
			\add_filter('pre_set_transient_settings_errors', array($this, 'noticeSettingsSaved'));
			$this->recursiveBuild();
		}
	}

	public function noticeSettingsSaved($value)
	{
		if( !empty($value) && isset($_POST['option_page']) && $_POST['option_page'] == $this->id )
		{
			$val = \current($value);
			if( isset($val['type']) && $val['type'] == 'updated' && isset($val['code']) && $val['code'] == 'settings_updated' )
				\lws_admin_add_notice_once('lws_ap_page', __("Your settings have been saved.", LWS_ADMIN_PANEL_DOMAIN), array('level'=>'success'));
		}
		return $value;
	}

	/** Create instances of active groups and fields. */
	protected function recursiveBuild($parentGroupFirst=true, $data=null, $path=null)
	{
		if( is_null($data) || is_null($path) )
		{
			$path = $this->localPath();
			$data = $this->data;
		}

		if( $parentGroupFirst ) $this->buildGroups($data);
		if( !empty($path) && isset($data['tabs']) )
		{
			$id = array_shift($path);
			foreach($data['tabs'] as $tab)
			{
				if( $tab['id'] == $id )
				{
					$this->recursiveBuild($parentGroupFirst, $tab, $path);
					break;
				}
			}
		}
		if( !$parentGroupFirst ) $this->buildGroups($data);
	}

	protected function buildGroups($data)
	{
		if( isset($data['groups']) )
		{
			foreach($data['groups'] as $group)
			{
				$this->groups[] = new Group($group, $this->id);
			}
		}
	}

	/** @return the path between all tab levels as an array of tab id from top level to leaf.
	 * If no tab path exists, return the first one.
	 * Cumulate the toc, nosave and action settings */
	protected function localPath()
	{
		if( !isset($this->path) )
		{
			$this->computeGraph($this->data, $this->data['rights']);
			$this->path = array();
			$path = isset($_REQUEST['tab']) ? explode('.', $_REQUEST['tab']) : array();

			if( isset($this->data['tabs']) )
			{
				$data = $this->data['tabs'];
				$depth = 0;
				while( !is_null($data) )
				{
					$data = $this->validLocalPath($data, array_slice($path, $depth++));
					if( !is_null($data) )
					{
						$this->path[] = $data['id'];
						if( isset($data['toc']) ) $this->data['toc'] = $data['toc'];
						if( isset($data['nosave']) ) $this->data['nosave'] = $data['nosave'];
						if( isset($data['action']) ) $this->action = $data['action'];
						if( isset($data['function']) && is_callable($data['function']) ) $this->customBehavior[] = $data['function'];
						if( isset($data['delayedFunction']) && is_callable($data['delayedFunction']) ) $this->lateCustomBehavior[] = $data['delayedFunction'];
						if( isset($data['hidden']) ) $this->hiddenTab = boolval($data['hidden']);
						$data = isset($data['tabs']) ? $data['tabs'] : null;
					}
				}
			}
		}
		return $this->path;
	}

	/** @return a formated path string to represent a tab path.
	 * @param $path (array sorted form root tab to leaf) if null, use the current tab path. */
	protected function pathString($path=null)
	{
		if( is_null($path) )
			$path = $this->localPath();
		return implode('.', $path);
	}

	/** @return the next tab or null if on a leaf
	 * ids must be complete on each tabs @see completeMissingIds
	 * @param $tabs an array of tab. */
	protected function validLocalPath($tabs, $subpath)
	{
		$first = null;
		foreach( $tabs as $tab )
		{
			if( is_null($first) )
			{
				$first = $tab;
				if( empty($subpath) )
					break;
			}
			if( $tab['id'] == $subpath[0] )
				return $tab;
		}
		return $first;
	}

	/** Clean the graph.
	 * remove part when access is not granted
	 * Complete missing ids: human readable group and tab id is useless, define them by hand could be boring if not use elsewhere.
	 * Complete groups and tabs without id by generating one.
	 * Generated id starts by '--', so avoid using it for your own. */
	protected function computeGraph(&$data, $rights, &$gindex=0, &$tindex=0)
	{
		$pageId = $this->id;
		if( isset($data['groups']) )
		{
			$data['groups'] = array_filter($data['groups'], function($var)use($rights, $pageId) {
				return current_user_can(isset($var['rights']) ? $var['rights'] : $rights) && \LWS\Adminpanel\Pages::test($var, Group::format(), "$pageId ... groups");
			});

			foreach( $data['groups'] as &$group )
			{
				if( !isset($group['id']) || empty($group['id']) )
				{
					$group['id'] = "--{$this->id}-group-{$gindex}";
					$gindex++;
				}
			}
		}
		if( isset($data['tabs']) )
		{
			$data['tabs'] = array_filter($data['tabs'], function($var)use($rights, $pageId) {
				return current_user_can(isset($var['rights']) ? $var['rights'] : $rights) && \LWS\Adminpanel\Pages::test($var, Page::tabFormat(), "$pageId ... tabs");
			});

			foreach( $data['tabs'] as &$tab )
			{
				if( !isset($tab['id']) || empty($tab['id']) )
				{
					$tab['id'] = "--{$this->id}-tab-{$tindex}";
					$tindex++;
				}
				$this->computeGraph($tab, isset($tab['rights']) ? $tab['rights'] : $rights, $gindex, $tindex);
			}
		}
	}

	/** @return an array of Field instances. */
	public function getFields()
	{
		$f = array();
		foreach( $this->groups as $Group )
				$Group->mergeFields($f);
		return $f;
	}

	protected function hasField()
	{
		foreach( $this->groups as $Group )
		{
			if( $Group->hasFields(true) )
				return true;
		}
		return false;
	}

	protected function hasGroup()
	{
		return !empty($this->groups);
	}

	/** Page display entry point */
	public function page()
	{
		if( !$this->isSingularEdit() )
		{
			echo "<div class='lws-adminpanel'>";
			self::echoTopBar($this->id, $this->getTopBarSettings());
			if( isset($this->data['text']) )
				echo "<div class='lws-description'>{$this->data['text']}</div>";

			if( $this->hasGroup() || !empty($this->customBehavior) || !empty($this->lateCustomBehavior) )
			{
				echo "<br/><div id='lws-Tabs'>";
				$this->echoTab();
				echo "</div>";
			}

			echo "</div>";
		}
		else
		{
			echo "<div class='lws-adminpanel lws-adminpanel-singular'>";
			self::echoTopBar($this->id, $this->getTopBarSettings());
			echo "<div class='lws-adminpanel-singular-body' role='main'>";
			$this->singularForm();
			echo "</div></div>";
		}
	}

	/** Enqueue WordPress' script for handling the metaboxes */
	function singularEnqueueScripts($hook)
	{
		if( $hook == $this->pageId && $this->isSingularEdit() )
		{
		}
	}

	private function tabCount($withLicenceTab=true)
	{
		return intval($this->hasTabs($this->data, $withLicenceTab));
	}

	/** Show tab bar and tab content. */
	private function echoTab()
	{
		if( $this->tabCount(false) > 1 )
			$this->tabs($this->data['tabs'], $this->localPath());

		$advTitle = __("Advanced Settings", LWS_ADMIN_PANEL_DOMAIN);
		echo "<div class='lws-sub-description'>";
		echo "<div id='lws_toc_options'><div class='lws-toc-options-wrapper'><div class='lws-toc-options-icon lws-icon-cogs'></div><div class='lws-toc-options-text'>$advTitle</div></div></div>";
		if( isset($this->data['subtext']) )
			echo $this->data['subtext'];
		echo "</div>";

		echo "<div class='lws-table-of-content'>";
		if( $this->useTableOfContent() )
			$this->echoTableOfContent();

		echo "<div id='lws-toc' class='lws-table-of-content-page'>";
		echo "<div class='lws-table-of-content-page-inside'>";

		foreach( $this->customBehavior as $fct )
			call_user_func( $fct, $this->id, $this->localPath() );

		if( $this->hasGroup() )
			$this->echoForm();

		foreach( $this->lateCustomBehavior as $fct )
			call_user_func( $fct, $this->id, $this->localPath() );

		echo "</div></div></div>";
	}

	private function hasTabs($page, $withLicTab=false)
	{
		if( isset($page['tabs']) && !empty($page['tabs']) )
		{
			$c = 0;
			foreach( $page['tabs'] as $tab )
			{
				if( !(isset($tab['hidden']) && boolval($tab['hidden'])) && ($withLicTab || !(isset($tab['id']) && $tab['id'] == self::LIC_TAB_ID)) )
					++$c;
			}
			return $c;
		}
		return false;
	}

	private function makemenu($tabs, $path, $depth, $from=array())
	{
		$menu="";
		$depth++;
		$current = array_shift($path);
		$cpt=0;
		foreach($tabs as $tab)
		{
			if( ($tab['id'] != self::LIC_TAB_ID || !empty($from)) && !(isset($tab['hidden']) && boolval($tab['hidden'])) )
			{
				$hasTab = $this->hasTabs($tab) > 1;

				$class ="lws-mtab-li-$depth";
				$classa = "ui-tabs-anchor lws-theme-over-fg";
				if( $tab['id'] == $current )
					$classa .= " lws-mtab-active";
				if( $depth==0 && $cpt != 0 )
					$class .= " lws-mtab-menu-sep";
				if( $hasTab )
					$class .= " lws_mtab_hassub";

				$newfrom =array_merge($from, array($tab['id']));
				$tabRef = $this->pathString($newfrom);
				$title = $tab['title'];
				if( $hasTab )
				{
					if( $depth == 0 )
						$title .= "<span class='lws-mtab-menu-arrow lws-icon-circle-down'></span>";
					else
						$title = "<div class='lws-mtab-submenu-label'>$title</div><div class='lws-mtab-menu-arrow lws-icon-circle-right'></div>";
				}

				$href = ($hasTab ? '#' : "?page={$this->id}&tab={$tabRef}");
				$menu .=	"<li id='{$tab['id']}' class='$class' role='tab'>";
				$menu .=	"<a  class='$classa' role='presentation' href='{$href}'>{$title}</a>";
				if( $hasTab )
				{
					$menu .=	"<ul class='lws-top-menu lws-mtab-ul-".($depth+1)." lws-mtab-menu-hidden' role='tablist' data-depth='".($depth+1)."'>";
					$menu .= $this->makemenu($tab['tabs'], $tab['id'] == $current ? $path : array(), $depth, $newfrom);
					$menu .= "</ul>";
				}
				$menu .=	"</li>";
				$cpt++;
			}
		}
		return $menu;
	}

	private function tabs($tabs, $path)
	{
		$depth = -1;
		$menu=	"<div class='lws-tabs-zone'>";
		$menu.=	"<div class='lws-tabs-small-menu'><div class='lws-tabs-sm-menubutton lws-icon-bars'></div></div>";
		$menu.=	"<ul class='lws-mtab-ul-0' role='tablist' data-depth='.($depth+1).'>";
		$menu.= $this->makemenu($tabs, $path, $depth);
		$menu.=	"</ul>";
		$menu.=	"</div>";
		echo($menu);
	}


	private function echoTableOfContent()
	{
		echo "<div id='lws-toc-menu' class='lws-table-of-content-menu'><ul class='lws-toc-ul'>";
		$premier = 'active';
		foreach( $this->groups as $Group )
		{
			$title = $Group->title(self::$MaxTitleLength);
			if( !empty($title) )
			{
				$advanced = $Group->isAdvanced() ? ' lws_advanced_option' : '';
				$anchor = "#" . $Group->targetId();
				echo "<li class='lws-toc-li$advanced'><a class='$premier' href='$anchor'>$title</a></li>";
				$premier='';
			}
		}
		echo "</ul>";
		echo "</div>";
	}

	/** Deepest displaying step, show groups in a form. */
	private function echoForm()
	{
		$formAttrs = \apply_filters('lws_adminpanel_form_attributes'.$this->id, array(
			'method' => 'post',
			'action' => $this->action,
		));
		$attrs = '';
		foreach($formAttrs as $k => $v)
		{
			$v = \esc_attr($v);
			$attrs .= " $k='$v'";
		}
		echo "<form {$attrs}>";
		$this->settingsFields();

		foreach( $this->groups as $Group )
			$Group->eContent();

		if( $this->allowSubmit() )
			\submit_button();
		echo "</form>";
	}

	/** echo required input for admin page working. */
	private function settingsFields()
	{
		$path = $this->pathString();
		echo "<input type='hidden' name='tab' value='$path'>";
		\settings_fields($this->id);
	}

	private function useTableOfContent()
	{
		return count($this->groups) > 1 && (isset($this->data['toc']) ? boolval($this->data['toc']) : true);
	}

	private function allowSubmit()
	{
		return $this->hasField() && !(isset($this->data['nosave']) && boolval($this->data['nosave']));
	}

	static function echoTopBar($id, $settings)
	{
		$txt = \apply_filters('lws_adminpanel_topbar_labels_'.$id, array(
			'doc'      => __("Documentation", LWS_ADMIN_PANEL_DOMAIN),
			'showcase' => __("Other Plugins", LWS_ADMIN_PANEL_DOMAIN),
			'mailto'   => __("Support", LWS_ADMIN_PANEL_DOMAIN),
			'purchase' => __("Try the Pro Version for free", LWS_ADMIN_PANEL_DOMAIN),
			'chat'     => __("Join Us on Discord", LWS_ADMIN_PANEL_DOMAIN),
			'licence'  => __("Activate your license", LWS_ADMIN_PANEL_DOMAIN),
			'youtube'  => __("Watch our Youtube tutorials", LWS_ADMIN_PANEL_DOMAIN),
		));

		$settings = \wp_parse_args($settings, array(
			'title'   => \get_admin_page_title(),
			'subtitle'=> '',
			'url'     => __("https://plugins.longwatchstudio.com/en/home/", LWS_ADMIN_PANEL_DOMAIN),
			'version' => '',
			'origin'  => array('LWS', 'Long Watch Studio'),
			'doc'     => __("https://plugins.longwatchstudio.com/en/documentation/", LWS_ADMIN_PANEL_DOMAIN),
			'showcase'=> __("https://plugins.longwatchstudio.com/en/home/", LWS_ADMIN_PANEL_DOMAIN),
			'youtube' => self::YOUTUBE,
			'chat'    => self::CHAT,
			'mailto'  => self::MAILTO,
			'purchase'=> false,
			'licence' => false,
			'activated' => false,
		));

		$row = '';
		if( $settings['purchase'] )
			$row .= "<a class='lws-opt-menu-text lws-theme-focus' title='Buy Pro Version' href='".\esc_attr($settings['purchase'])."'>{$txt['purchase']}</a>";
		if( $settings['url'] )
			$row .= "<a class='lws-opt-menu-link lws-opt-menu-logo' title='".\esc_attr(end($settings['origin']))."' href='".\esc_attr($settings['url'])."' target='_blank'></a>";
		if( $settings['doc'] )
			$row .= "<a class='lws-opt-menu-link lws-icon-books lws-theme-over-fg' title='".\esc_attr($txt['doc'])."' href='".\esc_attr($settings['doc'])."' target='_blank'></a>";
		if( $settings['showcase'] )
			$row .= "<a class='lws-opt-menu-link lws-icon-download lws-theme-over-fg' title='".\esc_attr($txt['showcase'])."' href='".\esc_attr($settings['showcase'])."' target='_blank'></a>";
		if( $settings['youtube'] )
			$row .= "<a class='lws-opt-menu-link lws-icon-youtube2 lws-theme-over-fg' title='".\esc_attr($txt['youtube'])."' href='".\esc_attr($settings['youtube'])."' target='_blank'></a>";
		if( $settings['chat'] )
			$row .= "<a class='lws-opt-menu-link lws-icon-lw_discord lws-theme-over-fg' title='".\esc_attr($txt['chat'])."' href='".\esc_attr($settings['chat'])."' target='_blank'></a>";
		if( $settings['mailto'] )
			$row .= "<a class='lws-opt-menu-link lws-icon-bubbles3 lws-theme-over-fg' title='".\esc_attr($txt['mailto'])."' href='mailto:".\esc_attr($settings['mailto'])."'></a>";
		if( $settings['licence'] )
		{
			$css = $settings['activated'] ? ' lws-icon-key lws-opt-licence-ok' : '';
			$label = $settings['activated'] ? '' : "<span class='lws-opt-menu-licence-text'>{$txt['licence']}</span>";
			$row .= "<a class='lws-theme-bg lws-opt-menu-licence$css' title='Licence' href='".\esc_attr($settings['licence'])."'>{$label}</a>";
		}

		$copyright = sprintf(__('%1$s Admin Panel %2$s', LWS_ADMIN_PANEL_DOMAIN), reset($settings['origin']), LWS_ADMIN_PANEL_VERSION);
		$subtitle = ($settings['subtitle'] ? "<div class='lws-adm-title lws-subtitle'>{$settings['subtitle']}</div>" : '');
		echo <<<EOT
<div id='lws-TopBar'>
	<div class='lws-adm-titlebar'>
		<div class='lws-adm-title lws-admpanel-title'>
			{$copyright}
		</div>
		<div class='lws-adm-title lws-title lws-theme-bg'>
			{$settings['title']} {$settings['version']}
		</div>
		{$subtitle}
	</div>
	<div class='lws-opt-menu'>
		{$row}
	</div>
</div>
EOT;
	}

	protected function getTopBarSettings()
	{
		$settings = array(
			'subtitle'=> isset($this->data['subtitle']) ? $this->data['subtitle'] : false,
			'url'     => __("https://plugins.longwatchstudio.com/en/home/", LWS_ADMIN_PANEL_DOMAIN),
			'version' => \apply_filters('lws_adminpanel_plugin_version_'     . $this->id, ''),
			'origin'  => \apply_filters('lws_adminpanel_plugin_origin_'      . $this->id, array('LWS', 'Long Watch Studio')),
			'doc'     => \apply_filters('lws_adminpanel_documentation_url_'  . $this->id, __("https://plugins.longwatchstudio.com/en/documentation/", LWS_ADMIN_PANEL_DOMAIN)),
			'showcase'=> \apply_filters('lws_adminpanel_plugin_showcase_url_'. $this->id, __("https://plugins.longwatchstudio.com/en/home/", LWS_ADMIN_PANEL_DOMAIN)),
			'youtube' => \apply_filters('lws_adminpanel_plugin_youtube_url_' . $this->id, self::YOUTUBE),
			'chat'    => \apply_filters('lws_adminpanel_plugin_chat_url_'    . $this->id, self::CHAT),
			'mailto'  => \apply_filters('lws_adminpanel_plugin_support_email'. $this->id, self::MAILTO),
		);
		if( isset($this->data['activated']) )
		{
			if( !$this->data['activated']  )
				$settings['purchase'] = \apply_filters('lws_adminpanel_purchase_url_' . $this->id, $settings['showcase']);
			$settings['licence'] = \add_query_arg(array('page'=>$this->id, 'tab'=>self::LIC_TAB_ID), \admin_url('/admin.php'));
			$settings['activated'] = $this->data['activated'];
		}
		return $settings;
	}

	protected function singularForm()
	{
		$formId = 'lws_adminpanel_singular_form_'.$this->id;
		$formAttr = '';
		foreach( \apply_filters('lws_adminpanel_singular_form_attributes_'.$this->id, array()) as $attr => $value )
			$formAttr .= " $attr='" . esc_attr($value) . "'";
		echo "<div id='lws-adminpanel-singular-wrap'><form id='$formId' name='$formId' method='post'$formAttr>";

		// hidden fields for validation
		\wp_nonce_field($formId, '_lws_ap_single_nonce', true, true);
		$value = \esc_attr(\get_current_user_id());
		echo "<input type='hidden' name='editor' value='$value'>";
		if( isset($this->data['singular_edit']['save']) )
		{
			$value = empty($this->singularId) ? 'create' : 'update';
			echo "<input type='hidden' name='hiddenaction' value='$value'>";
		}
		$value = \esc_attr($this->singularId);
		echo "<input type='hidden' name='singular_id' value='$value'>";

		echo "<div id='lws-adminpanel-singular-holder' class='lws-metabox-holder columns-2'>"; // metabox holder
		echo "<div id='lws-adminpanel-singular-container-1' class='lws-postbox-container lws-postbox-left'>"; // postbox-container 1

		// the content
		echo "<div id='lws-adminpanel-singular-edit'>";
		echo "<div id='lws-adminpanel-singular-edit-main' class='lws-adminpanel-singular-edit-main lws-adminpanel-singular-box'>";
		$ok = call_user_func($this->data['singular_edit']['form'], $this->singularId);
		echo "</div><div class='lws-adminpanel-singular-edit-meta'>";
		\do_action('lws_adminpanel_singular_form_'.$this->id, $this->singularId);
		echo "</div></div>"; // ## lws-adminpanel-singular-edit-main ## lws-adminpanel-singular-edit

		echo "</div>"; // ## end of postbox-container 1

		// button group (save, delete and anything else)
		echo "<div id='lws-adminpanel-singular-container-2' class='lws-postbox-container lws-postbox-right'>"; // postbox-container 2
		echo "<div id='lws-adminpanel-singular-actions' class='lws-adminpanel-singular-actions lws-adminpanel-singular-actionbox'>"; // meta-box-sortables
		if( $ok !== false )
		{
			$publish = '';
			if( !empty($this->singularId) && !empty($this->singularKey) && isset($this->data['singular_edit']['delete']) )
			{
				$delete = array(
					'btn' => _x("Delete element", "Singular object edition screen", LWS_ADMIN_PANEL_DOMAIN),
					'yes' => esc_attr(_x("I really want to delete it", "Singular object edition screen", LWS_ADMIN_PANEL_DOMAIN)),
					'no' => esc_attr(_x("Cancel", "Singular object edition screen", LWS_ADMIN_PANEL_DOMAIN)),
					'confirm' => _x("This element will be permanently removed. Are you sure?", "Singular object edition screen", LWS_ADMIN_PANEL_DOMAIN),
					'title' => esc_attr(_x("Permanent deletion", "Singular object edition screen", LWS_ADMIN_PANEL_DOMAIN))
				);

				$args = array(
					'page' => $this->id,
					'action' => 'delete',
					$this->singularKey => $this->singularId,
					'lws-nonce' => \wp_create_nonce($this->id . '-' . $this->singularId)
				);
				$href = \esc_attr(\add_query_arg($args, \admin_url('admin.php')));

				$publish .= "<a class='lws-adminpanel-singular-delete-button' data-yes='{$delete['yes']}' data-no='{$delete['no']}' href='$href'>{$delete['btn']}</a>";
				$publish .= "<div style='display:none;' title='{$delete['title']}' class='lws-adminpanel-singular-delete-confirmation'>{$delete['confirm']}</div>";
			}

			if( isset($this->data['singular_edit']['save']) )
			{
				$submit = empty($this->singularId) ? _x("Create", "Singular object creation screen", LWS_ADMIN_PANEL_DOMAIN) : _x("Update", "Singular object edition screen", LWS_ADMIN_PANEL_DOMAIN);
				$publish .= "<div class='lws-adminpanel-singular-main-action-button'>";
				$publish .= "<button class='lws-adminpanel-singular-commit-button lws-adm-btn button button-primary button-large'>$submit</button>";
				$publish .= "</div>";
			}

			$publish = \apply_filters('lws_adminpanel_singular_buttons_'.$this->id, $publish, $this->singularId);
			if( !empty($publish) )
				echo $this->getSingularPostbox('singular-publishing', __("Publish", LWS_ADMIN_PANEL_DOMAIN), $publish, 'lws-adminpanel-singular-publish-actions');

			/** Hook lws_adminpanel_singular_boxes_{$page_id}
			 * List the available meta boxes.
			 * @param 1 default boxes (empty array)
			 * @param 2 the singular page id
			 * @return array as $box_id => array( 'title' => (strirg), 'css' => (css classname) ) */
			foreach( \apply_filters('lws_adminpanel_singular_boxes_'.$this->id, array(), $this->singularId) as $boxId => $box)
			{
				if( !is_array($box) )
					$box = array('title'=>$box);
				/** Hook lws_adminpanel_singular_box_content_{$page_id}_{$box_id}
				 * @param 1 default content (empty string)
				 * @param 2 the singular page id
				 * @return (string) box html content. */
				$content = \apply_filters('lws_adminpanel_singular_box_content_'.$this->id.'_'.$boxId, '', $this->singularId);
				echo $this->getSingularPostbox($boxId, isset($box['title']) ? $box['title'] : '', $content, isset($box['css']) ? $box['css'] : '');
			}
		}
		echo "</div></div>"; // ## meta-box-sortables ## postbox-container 2
		echo "</div>"; // ## metabox holder ## poststuff
		echo "</form></div>"; // ## wrap and form
	}

	protected function getSingularPostbox($id, $title, $content, $css='')
	{
		$attrId = empty($id) ? '' : " id='$id'";
		$class = 'lws-adminpanel-singular-metabox lws-postbox';
		if( !empty($css) )
			$class .= ' ' . $css;
		$html = "<div$attrId class='$class'>";
		$html .= "<h2 class='lws-singular-postbox-title'><span>{$title}</span></h2>";
		$html .= "<div class='inside'>{$content}</div></div>";
		return $html;
	}

	/** @return (bool) singular should still be displayed. */
	protected function singularAction()
	{
		if( isset($this->data['rights']) && !empty($this->data['rights']) )
		{
			if( !\current_user_can($this->data['rights']) )
			{
				\lws_admin_add_notice_once('singular_edit', __("Action rejected for current user. Insufficient capacities.", LWS_ADMIN_PANEL_DOMAIN), array('level'=>'error'));
				return;
			}
		}

		if( isset($_GET['action']) && $_GET['action'] == 'delete' )
			$this->singularDelete();
		elseif( isset($_POST['hiddenaction']) && in_array($_POST['hiddenaction'], array('create', 'update')) )
			$this->singularUpdate();
	}

	/** Call save callable then redirect to avoid input reposting. */
	protected function singularUpdate()
	{
		$formId = 'lws_adminpanel_singular_form_'.$this->id;
		$doaction = true;
		// trustable origin
		if( !isset($_POST['_lws_ap_single_nonce']) )
			$doaction = false;
		elseif( !\check_admin_referer($formId, '_lws_ap_single_nonce') )
			$doaction = false;
		elseif( !\wp_verify_nonce($_POST['_lws_ap_single_nonce'], $formId) )
			$doaction = false;
		elseif( !isset($this->data['singular_edit']['save']) )
			$doaction = false;

		if( $doaction )
		{
			\lws_admin_add_notice_once('singular_edit', __("Your settings have been saved.", LWS_ADMIN_PANEL_DOMAIN), array('level'=>'success'));
			$id = call_user_func($this->data['singular_edit']['save'], $this->singularId);

			if( empty($this->singularId) && (is_string($id) || is_numeric($id) || is_bool($id)) )
				$this->singularId = sanitize_key($id);
			\do_action('lws_adminpanel_singular_update_'.$this->id, $this->singularId);

			// redirection
			$args = array('page' => $this->id);
			if( !empty($this->singularKey) )
				$args[$this->singularKey] = $this->singularId;
			$redirect_to = \add_query_arg($args, \admin_url('admin.php'));
			\wp_redirect($redirect_to, 303);
			exit;
		}
	}

	protected function singularDelete()
	{
		if( !empty($this->singularId) )
		{
			$args = array('page' => $this->id);

			if( !isset($this->data['singular_edit']['delete']) )
			{
				\lws_admin_add_notice_once('singular_edit', _x("Unavailable action.", "post deletion", LWS_ADMIN_PANEL_DOMAIN), array('level'=>'error'));
				if( !empty($this->singularKey) )
					$args[$this->singularKey] = $this->singularId;
			}
			elseif( !isset($_GET['lws-nonce']) || !\wp_verify_nonce($_GET['lws-nonce'], $this->id . '-' . $this->singularId) )
			{
				\lws_admin_add_notice_once('singular_edit', _x("Security check failed.", "post deletion", LWS_ADMIN_PANEL_DOMAIN), array('level'=>'error'));
				if( !empty($this->singularKey) )
					$args[$this->singularKey] = $this->singularId;
			}
			else // trustable origin
			{
				\lws_admin_add_notice_once('singular_edit', __("Element permanently removed.", LWS_ADMIN_PANEL_DOMAIN), array('level'=>'success'));

				if( false !== call_user_func($this->data['singular_edit']['delete'], $this->singularId) )
					\do_action('lws_adminpanel_singular_delete_'.$this->id, $this->singularId);
				else if( !empty($this->singularKey) )
					$args[$this->singularKey] = $this->singularId;
			}

			// redirection
			$redirect_to = \add_query_arg($args, \admin_url('admin.php'));
			\wp_redirect($redirect_to, 303);
			exit;
		}
	}

}

endif
?>