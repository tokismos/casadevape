<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Help extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id='', $title='', $extra=null)
	{
		parent::__construct($id, $title, $extra);
		$this->gizmo = true;

		$this->content = $this->getExtraValue('help');
		if( isset($this->extra['help']) )
			unset($this->extra['help']);

		static $once = true;
		if( $once )
		{
			\add_action('admin_enqueue_scripts', array(\get_class($this) , 'scripts'));
			$once = false;
		}
	}

	static public function scripts($hook)
	{
		\wp_enqueue_script('lws-adp-field-help', LWS_ADMIN_PANEL_JS . '/helpdismiss.js', array('jquery'), LWS_ADMIN_PANEL_VERSION, false);
	}

	public function input()
	{
		switch($this->getExtraValue('type'))
		{
			case 'youtube':
				$icon = 'lws-icon-youtube-play';
				$extraclass = 'lws-youtube';
				if( !isset($this->extra['dismissible']) )
					$this->extra['dismissible'] = true;
				break;
			case 'pub':
				$icon = 'lws-icon-lw_ad';
				$extraclass = 'lws-pub';
				break;
			default:
				$icon = 'lws-icon-lw_idea';
				$extraclass = 'lws-help';
		}

		$id = \esc_attr(empty($this->id()) ? \md5($this->content) : $this->id());

		echo "<div class='lws_adp_field_help lws-field-expl {$extraclass}' id='{$id}'>";
		echo "<div class='lws-field-expl-icon {$extraclass} {$icon}'></div>";
		echo "<div class='lws-field-expl-text {$extraclass}'>{$this->content}</div>";
		if( $this->getExtraValue('dismissible') )
		{
			echo "<div class='lws_adp_field_help_dismissible lws-field-expl-close lws-icon-cross {$extraclass}'></div>";
		}
		echo "</div>";
	}
}

?>
