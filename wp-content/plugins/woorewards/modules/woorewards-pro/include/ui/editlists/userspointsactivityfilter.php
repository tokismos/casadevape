<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Filter users looking for history entries in days. */
class UsersPointsActivityFilter extends \LWS\Adminpanel\EditList\Filter
{
	function __construct($name)
	{
		parent::__construct('lws-editlist-filter-' . strtolower($name));
		$this->name = $name;

		static $once = true;
		if( $once )
			\add_filter('lws_woorewards_ui_userspoints_request', array($this, 'filter'), 10, 2);
		$once = false;
	}

	function filter($sql, $countOnly=false)
	{
		$args = $this->getArgs();
		if( !empty($args->opeValue) && !empty($args->dayValue) )
		{
			global $wpdb;
			$select = "SELECT user_id FROM {$wpdb->prefix}lws_wr_historic WHERE date(mvt_date) >= DATE_SUB(NOW(), INTERVAL {$args->dayValue} DAY)";

			$operator = ($args->opeValue == 'ge' ? 'IN' : 'NOT IN');
			$clause = empty($sql['where']) ? 'WHERE ' : 'AND ';
			$clause .= "u.ID {$operator} ($select)";

			$sql['where'] .= $clause;
		}
		return $sql;
	}

	function input($above=true)
	{
		$args = $this->getArgs();
		$opts = base64_encode(json_encode(array(
			array('value' => '',   'label' => __("No filter", LWS_WOOREWARDS_PRO_DOMAIN)),
			array('value' => 'le', 'label' => __("Inactive for", LWS_WOOREWARDS_PRO_DOMAIN)),
			array('value' => 'ge', 'label' => __("Active in the last", LWS_WOOREWARDS_PRO_DOMAIN))
		)));
		$filterlabel = __('Filter by activity/inactivity period', LWS_WOOREWARDS_PRO_DOMAIN);
		$apply = __('Apply', LWS_WOOREWARDS_PRO_DOMAIN);
		$ph = __('Active/Inactive ...', LWS_WOOREWARDS_PRO_DOMAIN);

		$retour = "<div class='lws-editlist-filter-box'><div class='lws-editlist-filter-box-title'>{$filterlabel}</div>";
		$retour .= "<div class='lws-editlist-filter-box-content'>";
		$retour .= "<input name='{$args->opeKey}' class='lac_select lws-ignore-confirm' data-mode='select' value='{$args->opeValue}' data-source='{$opts}' data-placeholder='{$ph}'>";
		$retour .= "<input type='text' size='2' pattern='\\d*' id='{$args->dayKey}' name='{$args->dayKey}' value='{$args->dayValue}' class='lws-input-enter-submit lws-ignore-confirm'>";
		$retour .= "<label for='{$args->dayKey}'>".__("days", LWS_WOOREWARDS_PRO_DOMAIN)."</label>";
		$retour .= "<button class='lws-adm-btn lws-editlist-filter-btn'>{$apply}</button>";
		$retour .= "</div></div>";
		return $retour;
	}

	private function getArgs()
	{
		if( !isset($this->args) )
		{
			$this->args = (object)array(
				'opeKey'   => $this->name . '_o',
				'opeValue' => '',
				'dayKey'   => $this->name . '_d',
				'dayValue' => ''
			);

			if( isset($_GET[$this->args->opeKey]) && !empty($op = trim($_GET[$this->args->opeKey])) && in_array($op, array('ge', 'le')) )
				$this->args->opeValue = $op;
			if( isset($_GET[$this->args->dayKey]) && !empty($day = trim($_GET[$this->args->dayKey])) && is_numeric($day) )
				$this->args->dayValue = \absint($day);
		}
		return $this->args;
	}
}

?>