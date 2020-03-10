<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Filter users on loyalty point in range */
class UsersPointsRangeFilter extends \LWS\Adminpanel\EditList\Filter
{
	function __construct($name)
	{
		parent::__construct("lws-editlist-filter-search lws-editlist-filter-" . strtolower($name));
		$this->name = $name;

		static $once = true;
		if( $once )
			\add_filter('lws_woorewards_ui_userspoints_request', array($this, 'filter'), 10, 2);
		$once = false;
	}

	function filter($sql, $full=true)
	{
		$args = $this->getArgs();
		if( !empty($this->args->sysValue) && (!empty($this->args->minValue) || !empty($this->args->maxValue)) )
		{
			$data = $this->load();
			if( isset($data[$this->args->sysValue]) )
			{
				$metatable = 'pts_in';
				$glue = ' AND ';
				$clause = array();
				if( !empty($this->args->minValue) )
					$clause[] = "{$metatable}.meta_value >= {$this->args->minValue}";

				if( !empty($this->args->maxValue) )
				{
					$clause[] = "{$metatable}.meta_value <= {$this->args->maxValue}";
					if( empty($this->args->minValue) )
					{
						$glue = ' OR ';
						$clause[] = "{$metatable}.meta_value IS NULL";
					}
				}

				if( !empty($clause) )
				{
					$sql['where'] .= ((empty($sql['where']) ? 'WHERE (' : 'AND (') . implode($glue, $clause) . ')');

					global $wpdb;
					$metakey = \esc_attr('lws_wre_points_'.$data[$this->args->sysValue]->stack_id);
					$sql['join'] .= "\nLEFT JOIN {$wpdb->usermeta} as {$metatable} ON {$metatable}.user_id=u.ID AND {$metatable}.meta_key='{$metakey}'";
				}
			}
		}
		return $sql;
	}

	/** @return (string) alias usable to form mysql field name */
	protected function sqlAlias($key)
	{
		return str_replace('-', '$', \sanitize_key($key));
	}

	function input($above=true)
	{
		$args = $this->getArgs();
		$opts = array(array('value' => '', 'label' => __('No filter', LWS_WOOREWARDS_PRO_DOMAIN)));
		foreach( $this->load() as $id => $data )
			$opts[] = array('value' => $id, 'label' => $data->post_title);
		$opts = base64_encode(json_encode($opts));

		$filterlabel = __('Filter by user points', LWS_WOOREWARDS_PRO_DOMAIN);
		$apply = __('Apply', LWS_WOOREWARDS_PRO_DOMAIN);
		$ph = __('Loyalty System ...', LWS_WOOREWARDS_PRO_DOMAIN);

		$select = "<input name='{$args->sysKey}' class='lac_select lws-ignore-confirm' value='{$args->sysValue}' data-source='{$opts}' data-placeholder='{$ph}'>";
		$min = "<input type='text' size='3' name='{$args->minKey}' value='{$args->minValue}' placeholder='0' class='lws-input-enter-submit lws-ignore-confirm'>";
		$max = "<input type='text' size='3' name='{$args->maxKey}' value='{$args->maxValue}' class='lws-input-enter-submit lws-ignore-confirm'>";
		$retour = "<div class='lws-editlist-filter-box'><div class='lws-editlist-filter-box-title'>{$filterlabel}</div>";
		$retour .= "<div class='lws-editlist-filter-box-content'>";
		$tr = __('Between %1$s and %2$s in %3$s', LWS_WOOREWARDS_PRO_DOMAIN);
		$retour .= sprintf("{$tr}<button class='lws-adm-btn lws-editlist-filter-btn'>{$apply}</button>", $min, $max, $select);
		$retour .= "</div></div>";
		return $retour;
	}

	private function getArgs()
	{
		if( !isset($this->args) )
		{
			$this->args = (object)array(
				'sysKey'   => $this->name . '_o',
				'sysValue' => '',
				'minKey'   => $this->name . '_i',
				'minValue' => '',
				'maxKey'   => $this->name . '_a',
				'maxValue' => ''
			);

			if( isset($_GET[$this->args->sysKey]) && !empty($sys = trim($_GET[$this->args->sysKey])) && is_numeric($sys) )
				$this->args->sysValue = \absint($sys);
			if( isset($_GET[$this->args->minKey]) && !empty($min = trim($_GET[$this->args->minKey])) && is_numeric($min) )
				$this->args->minValue = \absint($min);
			if( isset($_GET[$this->args->maxKey]) && !empty($max = trim($_GET[$this->args->maxKey])) && is_numeric($max) )
				$this->args->maxValue = \absint($max);

			if( !empty($this->args->maxValue) && !empty($this->args->minValue) && ($this->args->maxValue < $this->args->minValue) )
			{
				$tmp = $this->args->maxValue;
				$this->args->maxValue = $this->args->minValue;
				$this->args->minValue = $tmp;
			}
		}
		return $this->args;
	}

	/** @return array({ID, post_name, post_title, stack_id}) */
	private function load()
	{
		if( !isset($this->data) )
		{
			$type = \LWS\WOOREWARDS\Core\Pool::POST_TYPE;
			global $wpdb;
			$sql = <<<EOT
SELECT ID, post_name, post_title, meta_value as stack_id FROM {$wpdb->posts}
LEFT JOIN {$wpdb->postmeta} ON ID=post_id AND meta_key='wre_pool_point_stack'
WHERE post_type='$type' AND post_status NOT IN ('trash') ORDER BY menu_order ASC, post_title ASC
EOT;
			$this->data = $wpdb->get_results($sql, OBJECT_K);
		}
		return $this->data;
	}
}

?>