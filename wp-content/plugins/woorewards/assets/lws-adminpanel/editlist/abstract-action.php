<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Action") ) :

/** Grouped action to apply on a selection of element. */
abstract class Action
{
	/** @param $uid an action identifier */
	function __construct($uid){ $this->UID = sanitize_key($uid); }

	/** The edition inputs.
	 * Allows the user to choose the grouped action to apply.
	 *	@return a string with the form content without submit button. */
	abstract function input();

	/**	Apply the action on the rows.
	 * It is up to you to get action information (should be in $_POST if use any <input>).
	 * @param $itemsIds (array of array) the ids of the selected items to update.
	 * @return true if succeed, or false if failed. */
	abstract function apply( $itemsIds );

}

/** A default implementation to display a select.
 * You provide select content to constructor.
 * action is performed by a CALLABLE you must provide. */
class ActionImplSelect extends Action
{
	/** Dispose a <select> filled with the given $choices.
	 * At validation, call the given $callback.
	 * @param $choices is array of(value => text)
	 * @param $callback is a php CALLABLE which accept 3 arguments: $uid, select.value, selected items */
	function __construct($uid, $choices, $callback)
	{
		parent::__construct($uid);
		$this->choices = array();
		if( is_array($this->choices) )
		{
			foreach($choices as $v => $t )
				$this->choices[esc_attr($v)] = sanitize_text_field($t);
		}
		$this->callback = $callback;
	}

	function input()
	{
		$str = "<select name='{$this->UID}' class='lws-ignore-confirm'>";
		if( is_array($this->choices) )
		{
			foreach( $this->choices as $v => $t )
				$str .= "<option value='$v'>$t</option>";
		}
		$str .= "</select>";
		return $str;
	}

	function apply( $itemsIds )
	{
		if( isset($_POST[$this->UID]) && is_array($this->choices) )
		{
			$action = esc_attr($_POST[$this->UID]);
			if( isset($this->choices[$action]) && $this->callback != null && is_callable($this->callback) )
			{
				call_user_func( $this->callback, $this->UID, $action, $itemsIds );
				return true;
			}
		}
		return false;
	}
}

endif
?>
