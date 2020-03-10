<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add a badge column to customer editlist.
 * Add a filter on user badge. */
class UsersPointsBadgeFilter extends \LWS\Adminpanel\EditList\Filter
{

	function __construct($name)
	{
		parent::__construct("lws-editlist-filter-search lws-editlist-filter-" . strtolower($name));
		$this->name = $name;

		static $once = true;
		if( $once )
		{
			\add_filter('lws_woorewards_ui_userspoints_request', array($this, 'filter'), 10, 2);
			\add_filter('lws_woorewards_ui_userspoints_rewards_cell', array($this, 'rewardCellContent'), 11, 2);
		}
		$once = false;
	}

	function rewardCellContent($content, $user)
	{
		if( !empty($user) && isset($user['user_id']) )
		{
			$c = \LWS\WOOREWARDS\PRO\Core\Badge::countByUser($user['user_id']);
			if( $c )
			{
				$url = \esc_attr(\add_query_arg(array('post_type'=>\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE, 'user_id'=>$user['user_id']), \admin_url('edit.php')));
				if( !empty($url) )
				{
					static $link = false;
					if( $link === false )
						$link = __("See badges (%d)", LWS_WOOREWARDS_PRO_DOMAIN);
					$label = sprintf($link, $c);
					$content[] = "<a class='lws_wre_rewards_link' href='$url' target='_blank'>$label</a>";
				}
			}
			else
			{
				static $disp = false;
				if( $disp === false )
					$disp = __("No badge", LWS_WOOREWARDS_PRO_DOMAIN);
				$content[] = "<div class='lws_wre_rewards_no_link'>$disp</div>";
			}
		}
		return $content;
	}

	function filter($sql, $full=true)
	{
		$args = $this->getArgs();
		if( !empty($this->args->value) )
		{
			global $wpdb;
			$table = \LWS\WOOREWARDS\PRO\Core\Badge::getLinkTable();
			if( !isset($sql['join']) )
				$sql['join'] = '';
			else if( !empty($sql['join']) )
				$sql['join'] .= ' ';
			$sql['join'] .= $wpdb->prepare("INNER JOIN {$table} as badge ON badge.user_id=u.ID AND badge.badge_id=%d", $this->args->value);
		}
		return $sql;
	}

	function input($above=true)
	{
		$args = $this->getArgs();
		$label = __('Filter by badge', LWS_WOOREWARDS_PRO_DOMAIN);
		$apply = __('Apply', LWS_WOOREWARDS_PRO_DOMAIN);
		$ph = __('Badge ...', LWS_WOOREWARDS_PRO_DOMAIN);

		$retour = <<<EOT
<div class='lws-editlist-filter-box'>
	<div class='lws-editlist-filter-box-title'>{$label}</div>
	<div class='lws-editlist-filter-box-content'>
		<input name='{$args->key}' class='lac_select lws-ignore-confirm' value='{$args->value}' data-ajax='lws_woorewards_badge_list' data-placeholder='{$ph}'>
		<button class='lws-adm-btn lws-editlist-filter-btn'>{$apply}</button>
	</div>
</div>
EOT;
		return $retour;
	}

	private function getArgs()
	{
		if( !isset($this->args) )
		{
			$this->args = (object)array(
				'key'   => $this->name,
				'value' => ''
			);

			if( isset($_GET[$this->args->key]) && !empty($badge = trim($_GET[$this->args->key])) && is_numeric($badge) )
				$this->args->value = \absint($badge);
		}
		return $this->args;
	}

}
?>