<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


/** extra = array(
					'rows' => 15,
					'cols' => 80,
				); */
class TextArea extends \LWS\Adminpanel\Pages\Field
{
	protected function dft(){ return array('rows' => 15, 'cols' => 80); }

	public function input()
	{
		$name = $this->m_Id;
		$value = htmlspecialchars($this->readOption(false));
		echo "<textarea class='{$this->style}' rows='{$this->extra['rows']}' cols='{$this->extra['cols']}' name='$name'>$value</textarea>";
	}
}

?>
