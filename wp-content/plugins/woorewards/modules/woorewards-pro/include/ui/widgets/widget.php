<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide a widget to let display rewards.
 * Can be used as a Widget, a Shortcode [lws_rewards] or a Guttenberg block (soon).
 * Rewards can be filtered by pool
 * For a looged in user, we can filter only the unlockable ones. */
class Widget extends \WP_Widget
{
	static protected function register($className)
	{
		\add_action('widgets_init', function()use($className){\register_widget($className);});
	}

	/** echo a form select line @param $options (array) value=>text */
	protected function eFormFieldSelect($id, $label, $name, $options, $value)
	{
		$input = "<select id='$id' name='$name'>";
		foreach( $options as $v => $txt )
		{
			$selected = $v == $value ? ' selected' : '';
			$input .= "<option value='$v'$selected>$txt</option>";
		}
		$input .= "</select>";
		$this->eFormField($id, $label, $input);
	}

	/** echo a form text line */
	protected function eFormFieldText($id, $label, $name, $value, $placeholder='')
	{
		$input = "<input class='widefat' id='$id' name='$name' type='text' value='$value' placeholder='$placeholder'/>";
		$this->eFormField($id, $label, $input);
	}

	/** echo a form entry line */
	protected function eFormField($id, $label, $input)
	{
		echo "<p><label for='$id'>$label</label>$input</p>";
	}
}

?>