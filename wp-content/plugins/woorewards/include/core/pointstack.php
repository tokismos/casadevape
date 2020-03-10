<?php
namespace LWS\WOOREWARDS\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage user points (and point history in pro version).
 *	Few functions have a force argument to reset the buffered amount by reading the database again. */
final class PointStack implements \LWS\WOOREWARDS\Abstracts\IPointStack
{
	function __construct($name, $userId)
	{
		$this->name = $name;
		$this->userId = $userId;
	}

	function get($force = false)
	{
		if( !isset($this->amount) || $force )
		{
			$this->amount = intval(round(\get_user_meta($this->userId, $this->metaKey(), true)));
		}
		return $this->amount;
	}

	function set($points, $reason='')
	{
		$this->amount = intval(round($points));
		\update_user_meta($this->userId, $this->metaKey(), $this->amount);
		$this->trace($points, null, $reason);
		return $this;
	}

	function add($points, $reason='', $force = false)
	{
		if( !empty($points = intval(round($points))) )
		{
			$amount = $this->get($force);
			$this->amount = $amount + $points;
			\update_user_meta($this->userId, $this->metaKey(), $this->amount);
			$this->trace($this->amount, $points, $reason);
		}
		return $this;
	}

	function sub($points, $reason='', $force = false)
	{
		if( !empty($points = intval(round($points))) )
		{
			$amount = $this->get($force);
			$this->amount = $amount - $points;
			\update_user_meta($this->userId, $this->metaKey(), $this->amount);
			$this->trace($this->amount, -$points, $reason);
		}
		return $this;
	}

	/** That action is performed for all users.
	 *
	 * Reset any point amount in this stack unchanged since $threshold.
	 * If option 'lws_woorewards_pointstack_timeout_delete' is 'on', delete all record before that date.
	 * @param $threshold reset points if last change is before that date.
	 * @param $getAffectedUserIds (bool) if true, return an array with affected user IDs. default is false.
	 * @return null|array depends on $getAffectedUserIds */
	public function timeout(\DateTime $threshold, $getAffectedUserIds=false)
	{
		$affected = null;
		global $wpdb;
		$table = self::table();

		// reset point values for customers without recent activity but with points (note we set '' and not zero)
		$update = <<<EOT
UPDATE {$wpdb->usermeta} as raz SET raz.meta_value='' WHERE raz.meta_key=%s AND raz.meta_value>0
AND raz.user_id NOT IN (SELECT DISTINCT good.user_id FROM $table as good WHERE good.stack=%s AND date(good.mvt_date) >= date(%s))
EOT;
		$wpdb->query($wpdb->prepare(
			$update,
			$this->metaKey(),
			$this->name,
			$threshold->format('Y-m-d')
		));

		// insert reset line for customers with '' as point value
		$insert = <<<EOT
INSERT INTO $table (user_id, new_total, stack, commentar)
SELECT DISTINCT pts.user_id, 0, %s, %s FROM {$wpdb->usermeta} as pts WHERE pts.meta_key=%s AND pts.meta_value=''
EOT;
		$wpdb->query($wpdb->prepare(
			$insert,
			$this->name,
			_x("Lost due to inactivity", "Point Timeout reason", LWS_WOOREWARDS_DOMAIN),
			$this->metaKey()
		));

		if( $getAffectedUserIds )
		{
			$affected = $wpdb->get_col($wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} as raz WHERE raz.meta_key=%s AND raz.meta_value=''",
				$this->metaKey()
			));
		}

		// clean points amounts values (replace '' by zero)
		$wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->usermeta} as raz SET raz.meta_value='0' WHERE raz.meta_key=%s AND raz.meta_value=''",
			$this->metaKey()
		));

		if( !empty(\get_option('lws_woorewards_pointstack_timeout_delete', '')) )
		{
			$this->cleanup($threshold);
		}

		if( isset($this->amount) )
			unset($this->amount);
		return $affected;
	}

	/** That action is performed for all users.
	 *
	 * Remove from db any trace of that stack (usermeta and history) */
	public function delete()
	{
		global $wpdb;
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key=%s", $this->metaKey()));

		$table = self::table();
		$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE stack=%s", $this->name));

		if( isset($this->amount) )
			unset($this->amount);
	}

	/** @return (bool) in usage by a pool */
	public function isUsed()
	{
		global $wpdb;
		$c = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='wre_pool_point_stack' AND meta_value=%s",
			$this->name
		));
		return is_null($c) ? false : !empty($c);
	}

	/** Merge points from another stack to this one.
	 * The other stack is NOT modified. */
	public function merge($otherStackName)
	{
		global $wpdb;
		$table = self::table();

		// mark the merge in history, let stack empty for futur reference
		$insert = <<<EOT
INSERT INTO $table (user_id, new_total, points_moved, stack, commentar)
SELECT m.user_id, SUM(m.meta_value), SUM(m.diff), '', %s FROM (
	SELECT s.user_id, s.meta_value, 0 as diff FROM {$wpdb->usermeta} as s
	WHERE s.meta_key=%s
	UNION
	SELECT d.user_id, d.meta_value, d.meta_value as diff FROM {$wpdb->usermeta} as d
	WHERE d.meta_key=%s
) as m GROUP BY m.user_id
EOT;
		$wpdb->query($wpdb->prepare(
			$insert,
			sprintf(_x("Merge from %s", "Point Merge", LWS_WOOREWARDS_DOMAIN), $otherStackName),
			$this->metaKey(),
			$this->metaKey($otherStackName)
		));

		// copy points in history back to usermeta
		$update = <<<EOT
UPDATE {$wpdb->usermeta} as d
INNER JOIN {$table} as s ON s.user_id=d.user_id AND s.stack=''
SET d.meta_value=s.new_total
WHERE d.meta_key=%s
EOT;
		$wpdb->query($wpdb->prepare(
			$update,
			$this->metaKey()
		));

		// clean history, restore stack name
		$wpdb->query($wpdb->prepare(
			"UPDATE {$table} SET stack=%s WHERE stack=''",
			$this->name
		));

		if( isset($this->amount) )
			unset($this->amount);
	}

	/** That action is performed for all users.
	 *
	 * delete history in database.
	 * @param $threshold (DateTime) remove all entry before that date. */
	protected function cleanup(\DateTime $threshold)
	{
		global $wpdb;
		$table = self::table();
		$wpdb->query($wpdb->prepare(
			"DELETE FROM $table WHERE date(mvt_date)<date(%s)",
			$threshold->format('Y-m-d')
		));
	}

	protected function metaKey($name=false)
	{
		return self::MetaPrefix . ($name===false ? $this->name : $name);
	}

	public function getName()
	{
		return $this->name;
	}

	/** @return array[op_date, op_value, op_result, op_reason] */
	function getHistory($force = false)
	{
		if( !isset($this->history) || $force )
		{
			global $wpdb;
			$table = self::table();
			$this->history = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT mvt_date as op_date, points_moved as op_value, new_total as op_result, commentar as op_reason FROM $table WHERE user_id=%d AND stack=%s ORDER BY mvt_date DESC, id DESC",
					$this->userId,
					$this->name
				),
				ARRAY_A
			);
		}
		return $this->history;
	}

	protected function trace($points, $move=null, $reason='')
	{
		global $wpdb;
		$wpdb->insert( self::table(),
			array(
				'user_id'      => $this->userId,
				'stack'        => $this->name,
				'points_moved' => $move,
				'new_total'    => $points,
				'commentar'    => $reason
			),
			array(
				'%d',
				'%s',
				'%d',
				'%d',
				'%s'
			)
		);
		return $this;
	}

	static function table()
	{
		global $wpdb;
		return $wpdb->prefix.'lws_wr_historic';
	}

}

?>