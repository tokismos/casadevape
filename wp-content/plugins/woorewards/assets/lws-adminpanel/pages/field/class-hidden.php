<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Hidden extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id='', $title='', $extra=null)
	{
		if( is_array($extra) )
			$extra['hidden'] = true;
		else
			$extra = array('hidden' => true);
		parent::__construct($id, $title, $extra);
	}

	public function input()
	{
		$name = $this->m_Id;
		$value = $this->readOption();
		echo "<input class='{$this->style} lws-input-hidden' type='hidden' name='$name' value='$value' />";
	}
}

?>
