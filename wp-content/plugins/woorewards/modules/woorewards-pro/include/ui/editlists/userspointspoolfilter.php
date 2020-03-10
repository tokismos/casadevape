<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add all loylaty system columns and allow filtering. */
class UsersPointsPoolFilter
{
	static function install()
	{
		$me = new self();
		\add_filter('lws_adminpanel_make_page_' . LWS_WOOREWARDS_PAGE, array($me, 'appendTo'));

		\add_filter('lws_woorewards_ui_userspoints_labels', array($me, 'labels'));
		\add_action('lws_woorewards_ui_userspoints_enqueue_scripts', array($me, 'scripts'), 10, 2);
	}

	public function appendTo($page)
	{
		$page['tabs']['wr_customers']['groups']['customers_points']['function'] = array($this, 'input');
		return $page;
	}

	function input()
	{
		$str = '';
		$visibles = $this->visiblePools();
		foreach( $this->poolsInfo as $name => $details )
		{
			$label = \apply_filters('the_title', $details->post_title, $details->ID);
			$checked = (isset($visibles[$name]) && $visibles[$name]) ? ' checked' : '';
			$id = 'lws-wr-pool-column-visibility-' . $name;
			$str .= "<div class='lws-wr-pool-column-checkbox'><input type='checkbox' id='$id' class='lws_checkbox lws-ignore-confirm lws_wre_pool_visibility' data-name='lws_wre_pool_$name' data-size='15' name='lws_wre_pool_visibility_$name'$checked/><div class='lws-wr-pool-column-label'>$label</div></div>";
		}
		$title = __("Show/Hide loyalty systems points", LWS_WOOREWARDS_PRO_DOMAIN);

		echo "<div class='lws-wr-userspoints-pool-visibilities'>";
		echo "<div class='lws-wr-userspoints-pool-filter-title'>$title</div><div class='lws_wre_pool_visibilities'>$str</div>";
		echo "</div>";
	}

	/** Is pool visible by default in userpoints editlist?
	 * After that, visible pools selected by the user are kept in coockies. */
	protected function visiblePools()
	{
		$this->load();
		$visible = array();
		foreach( $this->poolsInfo as $name => $details )
		{
			$visible[$name] = true;
		}
		return $visible;
	}

	/** Add a column for each pool. */
	public function labels($labels=array())
	{
		$this->load();
		$preLen = strlen(\LWS\WOOREWARDS\Ui\Editlists\UsersPoints::L_PREFIX);
		foreach( $labels as $key => $label )
		{
			if( substr($key, 0, $preLen) == \LWS\WOOREWARDS\Ui\Editlists\UsersPoints::L_PREFIX )
				unset($labels[$key]);
		}

		$poolsLabels = array();
		foreach( $this->poolsInfo as $name => $post )
		{
			// \LWS_WooRewards::getPointSymbol(2, $name)
			$poolsLabels[\LWS\WOOREWARDS\Ui\Editlists\UsersPoints::L_PREFIX.$name] = \apply_filters('the_title', $post->post_title, $post->ID);
		}

		return array_slice($labels, 0, 1, true) + $poolsLabels + array_slice($labels, 1, null, true);
	}

	public function scripts($hook='', $tab='')
	{
		\wp_enqueue_script('lws-checkbox');
		\wp_enqueue_style('lws-checkbox');
		\wp_enqueue_script('lws-wr-poolfilter', LWS_WOOREWARDS_PRO_JS.'/poolfilter.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION, true);
		\wp_localize_script('lws-wr-poolfilter', 'lws_wr_userspoints_visible_pools', $this->visiblePools());
		\wp_enqueue_script('lws-wr-poolfilter');
		\wp_enqueue_style('lws-wr-poolfilter', LWS_WOOREWARDS_PRO_CSS.'/poolfilter.css', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function load()
	{
		if( !isset($this->poolsInfo) )
		{
			global $wpdb;
			$type = \LWS\WOOREWARDS\Core\Pool::POST_TYPE;
			$sql = "SELECT post_name, ID, post_title FROM {$wpdb->posts}";
			$sql .= " WHERE post_type='$type' AND post_status NOT IN ('trash') ORDER BY menu_order ASC, ID ASC";

			$this->poolsInfo = $wpdb->get_results($sql, OBJECT_K);
		}
	}

}
?>