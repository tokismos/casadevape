<?php
namespace LWS\WOOREWARDS\Conveniencies;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Convenience class.
 *	Represents a duration in year, month or day (exclusive).
 *
 * 	Provide convertion and computing helpers
 *	for php DateTime, DateInterval or MySql sentances.
 */
class Duration
{
	protected $number = 0;
	protected $period = 'D';

	function isNull()
	{
		return $this->number <= 0;
	}

	function getDays()
	{
		return $this->period == 'D' ? $this->number : false;
	}

	function getMonths()
	{
		return $this->period == 'M' ? $this->number : false;
	}

	function getYears()
	{
		return $this->period == 'Y' ? $this->number : false;
	}

	function getPeriod()
	{
		return $this->period;
	}

	function getSqlInterval()
	{
		$text = 'INTERVAL ' . $this->getCount();
		switch($this->period)
		{
			case 'Y':
				$text .= ' YEAR';
				break;
			case 'M':
				$text .= ' MONTH';
				break;
			default:
				$text .= ' DAY';
				break;
		}
		return $text;
	}

	function getPeriodText($firstLetterUpper=false)
	{
		$text = '-';
		switch($this->period)
		{
			case 'Y':
				$text = _n("Year", "Years", $this->number, LWS_WOOREWARDS_DOMAIN);
				break;
			case 'M':
				$text = _n("Month", "Months", $this->number, LWS_WOOREWARDS_DOMAIN);
				break;
			default:
				$text = _n("Day", "Days", $this->number, LWS_WOOREWARDS_DOMAIN);
				break;
		}
		return $firstLetterUpper ? $text : strtolower($text);
	}

	function getCount()
	{
		return $this->number;
	}

	/** Compute the date at end of duration.
	 * @param $from (false|DateTime) Starting date, default false means today.
	 * @return DateTime = $form + interval  */
	function getEndingDate($from=false)
	{
		if( false === $from )
			$from = \date_create();
		return $from->add($this->toInterval());
	}

	/** @see DateInterval */
	function toString()
	{
		if( $this->isNull() )
			return '';
		else
			return 'P'.$this->number.$this->period;
	}

	function toInterval()
	{
		return new \DateInterval($this->toString());
	}

	static function fromInterval($interval)
	{
		$y = abs($interval->format('%y'));
		$m = abs($interval->format('%m'));
		$j = abs($interval->format('%d'));
		if( (min($y,1) + min($m,1) + min($d,1)) == 1 )
		{
			if( !empty($y) )
				return new self($y, 'Y');
			else if( !empty($m) )
				return new self($m, 'M');
			else if( !empty($d) )
				return new self($d, 'D');
			else
				return self::void();
		}
		$interval = \date_create()->diff(\date_create()->add($interval), true);
		return new self($interval->format('%a'), 'D');
	}

	/** @param $interval first int is assumed as delay and first [YMD] as unit. if unit is omitted, day is assumed.
	 * A starting 'P' is ignored. */
	static function fromString($interval)
	{
		if( empty($interval) )
			return self::void();
		$pattern = '/P?(\d+)([DMY])/i';
		$match = array();
		if( preg_match($pattern, $interval, $match) )
			return new self($match[1], $match[2]);
		else
			return new self(intval($interval), 'D');
	}

	static function void()
	{
		return new self(0, 'D');
	}

	static function days($count)
	{
		return new self($count, 'D');
	}

	static function months($count)
	{
		return new self($count, 'M');
	}

	static function years($count)
	{
		return new self($count, 'Y');
	}

	static function userMeta($userId, $key)
	{
		return self::fromString(\get_user_meta($userId, $key, true));
	}

	static function postMeta($postId, $key)
	{
		return self::fromString(\get_post_meta($postId, $key, true));
	}

	static function option($key)
	{
		return self::fromString(\get_option($key, 0));
	}

	function deleteUserMeta($userId, $key)
	{
		\delete_user_meta($userId, $key);
	}

	function deletePostMeta($postId, $key)
	{
		\delete_post_meta($postId, $key);
	}

	function deleteOption($key)
	{
		\delete_option($key);
	}

	function updateUserMeta($userId, $key)
	{
		\update_user_meta($userId, $key, $this->toString());
	}

	function updatePostMeta($postId, $key)
	{
		\update_post_meta($postId, $key, $this->toString());
	}

	function updateOption($key)
	{
		\update_option($key, $this->toString(), false);
	}

	protected function __construct($n=0, $p='D')
	{
		$this->number = abs(intval($n));
		$this->period = in_array(($p = strtoupper($p)), array('D', 'M', 'Y')) ? $p : 'D';
	}
}

?>