<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Source") ) :

class RowLimit
{
	/// the offset of the first row to return
	public $offset = 0;
	/// the number of row to return
	public $count = 10;
	/// @return a mysql sentence part to add to your sql query
	public function toMysql(){ return " LIMIT {$this->offset}, {$this->count}"; }
	public function valid(){ if($this->offset < 0){$this->count += $this->offset; $this->offset = 0;} return ($this->count>0); }
	public static function append($limit, $sql)
	{
		if(!is_null($limit) && is_a($limit, \get_class()) && $limit->valid() && is_string($sql))
			$sql .= $limit->toMysql();
		return $sql;
	}
}

/** can be returned by write to detail the action result */
class UpdateResult
{
	public $data; /// (array) the data array, as it should be updated in view
	public $success; /// (bool) success of operation
	public $message; /// (string) empty, error reason or success additionnal information to display.
	/** @return a success UpdateResult instance. */
	public static function ok($data, $message='')
	{
		$me = new self();
		$me->success = true;
		$me->data = is_array($data) ? $data : array();
		$me->message = is_string($message) ? $message : '';
		return $me;
	}
	/** @return an error UpdateResult instance. */
	public static function err($reason='')
	{
		$me = new self();
		$me->success = false;
		$me->data = null;
		$me->message = is_string($reason) ? trim($reason) : '';
		return $me;
	}
	/** @return (bool) is a UpdateResult instance. */
	public static function isA($instance)
	{
		return \is_a($instance, get_class());
	}
}

/** As post, display a list of item with on-the-fly edition. */
abstract class Source
{

	/** The edition inputs.
	 *	input[name] should refers to all $line array keys (use input[type='hidden'] for not editable elements).
	 * Readonly element can be displayed using <span data-name='...'></span> but this one will not be send
	 * back at validation, display is its only prupose (name can be the same as an hidden input if you want return)
	 *	@return a string with the form content. */
	abstract function input();

	/**	@return an array with the column which must be displayed in the list.
	 *	array ( $key => array($label [, $col_width]) )
	 * The width (eg. 10% or 45px) to apply to column is optionnal. */
	abstract function labels();

	/**	get the list content and return it as an array.
	 * @param $limit an instance of RowLimit class or null if deactivated (if EditList::setPageDisplay(false) called).
	 *	@return an array of line array. array( array( key => value ) ) */
	abstract function read($limit);

	/**	Save one edited line. If the index is not found, this function must create a new record.
	 * @param $row (array) the edited item to save.
	 * @return On success return the updated line, if failed, return false or a \WP_Error instance to add details. */
	abstract function write( $row );

	/**	Delete one edited line.
	 * @param $row (array) the item to remove.
	 * @return true if succeed. */
	abstract function erase( $row );

	/** this function to return the total number of record in source.
	 * @return the record count or -1 if not implemented or unavailable. */
	public function total()
	{
		return -1;
	}

	/** Override this function to specify default values (array) for a new edition form. */
	public function defaultValues()
	{
		return "";
	}

	/** @deprecated use 'lws_adminpanel_arg_parse' filter instead.
	 * @see \LWS\Adminpanel\ArgParser */
	public static function invalidArray(&$array, $format, $strictFormat=true, $strictArray=true, $translations=array())
	{
		require_once LWS_ADMIN_PANEL_PATH . '/argparser.php';
		return \LWS\Adminpanel\ArgParser::invalidArray($array, $format, $strictFormat, $strictArray, $translations);
	}

}

endif
?>
