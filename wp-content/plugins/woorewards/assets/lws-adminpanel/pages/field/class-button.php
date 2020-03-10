<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

/** Expect a 'callback' entry in extra which refers to a callable.
 * The callback get two arguments: button_id, array with all inputs of the group (id => value).
 * This callback should return false for failure.
 *
 * All button can be embeded inside a container by setting a extra['container'] = array('tag'=>'span', 'class'=>'my_css_class')
 *
 * On success, a string can be returned which will be displayed on html after the button. */
class Button extends \LWS\Adminpanel\Pages\Field
{
	public function label()
	{
		if( isset($this->extra['text']) )
			return parent::label();
		else
			return ""; /// title will be used as button text
	}

	public function title()
	{
		if( isset($this->extra['text']) )
			return parent::title();
		else
			return ""; /// title will be used as button text
	}

	public function input()
	{
		$triggable = (isset($this->extra['callback']) && is_callable($this->extra['callback']));
		$class = (isset($this->extra['class']) && is_string($this->extra['class']) ? " {$this->extra['class']}" : '');
		$class .= ($triggable ? ' lws-adm-btn-trigger' : '');
		$text = esc_attr($this->getExtraValue('text', $this->m_Title));

		$tag = 'span';
		if( isset($this->extra['container']) )
		{
			$cc = '';
			if( is_array($this->extra['container']) )
			{
				if( isset($this->extra['container']['tag']) )
					$tag = $this->extra['container']['tag'];
				if( isset($this->extra['container']['class']) )
					$cc = $this->extra['container']['class'];
			}
			else
				$cc = $this->extra['container'];
			echo "<$tag class='$cc'>";
		}

		echo "<input class='lws-adm-btn$class' id='{$this->m_Id}' type='button' value='$text' />";
		if( $triggable ) // answer zone
			echo "<div class='lws-adm-btn-trigger-response'></div>";

		if( isset($this->extra['container']) )
			echo "</$tag>";
	}
}

?>
