<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Ajax API. Actions are:
 * * lws_woorewards_wc_product_list */
class Ajax
{
	function __construct()
	{
		\add_action( 'wp_ajax_lws_woorewards_wc_product_list', array( $this, 'getWCProducts') );
		\add_action( 'wp_ajax_lws_woorewards_pool_list', array( $this, 'getPools') );
		\add_action( 'wp_ajax_lws_woorewards_badge_list', array( $this, 'getBadges') );
	}

	public function getBadges()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);

		global $wpdb;
		$sql = "SELECT ID as value, post_title as label FROM {$wpdb->posts}";
		if( $fromValue )
		{
			$sql .= " WHERE ID IN (" . implode(',', $term) . ")";
		}
		else
		{
			$where = array();
			if( !empty($term) )
			{
				$search = trim($term, "%");
				$where[] = $wpdb->prepare("post_title LIKE %s", "%$search%");
			}
			$where[] = "post_type='".\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE."'";

			$sql .= " WHERE " . implode(' AND ', $where);
			$sql .= " AND post_status IN ('publish', 'private', 'future', 'pending')";
		}
		$sql = $this->finalizeQuery($sql, 'post_title');

		$badges = $wpdb->get_results($sql);
		foreach($badges as &$badge)
		{
			$img = \get_the_post_thumbnail($badge->value, array(21, 21), array('class'=>'lws-wr-thumbnail lws-wr-badge-icon'));
			$badge->html = "<div class='lws-wr-select-badge-icon'>" . ($img ? $img : '') . "</div>";
			$badge->html .= "<div class='lws-wr-select-badge-label'>{$badge->label}</div>";
		}
		\wp_send_json($badges);
	}

	public function getPools()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);

		global $wpdb;
		$sql = "SELECT ID as value, post_title as label FROM {$wpdb->posts}";
		if( $fromValue )
		{
			$sql .= " WHERE ID IN (" . implode(',', $term) . ")";
		}
		else
		{
			$where = array();
			if( !empty($term) )
			{
				$search = trim($term, "%");
				$where[] = $wpdb->prepare("post_title LIKE %s", "%$search%");
			}
			$where[] = "post_type='".\LWS\WOOREWARDS\Core\Pool::POST_TYPE."'";

			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$sql = $this->finalizeQuery($sql, 'post_title');
		\wp_send_json($wpdb->get_results($sql));
	}

	/** autocomplete/lac compliant.
	 * Search wp_post(wc_product) on id (or name if fromValue is false or missing).
	 * @see hook 'lws_woorewards_wc_product_list'.
	 * @param $_REQUEST['term'] (string) filter on product name
	 * @param $_REQUEST['page'] (int /optional) result page, not set means return all.
	 * @param $_REQUEST['count'] (int /optional) number of result per page, default is 10 if page is set. */
	public function getWCProducts()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);
		$spec = array();

		global $wpdb;
		$sql = "SELECT ID as value, post_title as label FROM {$wpdb->posts}";
		if( $fromValue )
		{
			$sql .= " WHERE ID IN (" . implode(',', $term) . ")";
		}
		else
		{
			$where = array();
			if( !empty($term) )
			{
				$search = trim($term, "%");
				$where[] = $wpdb->prepare("post_title LIKE %s", "%$search%");
			}
			$where[] = "post_type='product' AND (post_status='publish' OR post_status='private')";

			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$sql = $this->finalizeQuery($sql, 'post_title');
		\wp_send_json($wpdb->get_results($sql));
	}

	/** @return $sql with order by and limit appended. */
	protected function finalizeQuery($sql, $orderBy='', $dir='ASC')
	{
		if( !empty($orderBy) )
			$sql .= " ORDER BY {$orderBy} {$dir}";

		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) )
		{
			$count = absint(isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 10);
			$offset = absint($_REQUEST['page']) * $count;
			$sql .= " LIMIT $offset, $count";
		}
		return $sql;
	}

	/** @param $readAsIdsArray (bool) true if term is an array of ID or false if term is a string
	 *	@param $prefix (string) remove this prefix at start of term values.
	 *	@param $_REQUEST['term'] (string) filter on post_title or if $readAsIdsArray (array of int) filter on ID.
	 *	@return an array of int if $readAsIdsArray, else a string. */
	private function getTerm($readAsIdsArray, $prefix='')
	{
		$len = strlen($prefix);
		$term = '';
		if( isset($_REQUEST['term']) )
		{
			if( $readAsIdsArray )
			{
				if( is_array($_REQUEST['term']) )
				{
					$term = array();
					foreach( $_REQUEST['term'] as $t )
					{
						if( $len > 0 && substr($t, 0, $len) == $prefix )
							$t = substr($t, $len);
						$term[] = intval($t);
					}
				}
				else
					$term = array(intval($_REQUEST['term']));
			}
			else
				$term = \sanitize_text_field(trim($_REQUEST['term']));
		}
		return $term;
	}
}

?>