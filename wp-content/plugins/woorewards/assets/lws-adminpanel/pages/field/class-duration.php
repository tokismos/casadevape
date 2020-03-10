<?php
namespace LWS\Adminpanel\Pages\Field;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Set integer and time unit {Day, month, year}
 * $extra['value'] and stored wp_option format is DateInterval format.
 */
class Duration extends \LWS\Adminpanel\Pages\Field
{
	/** @return field html. */
	public static function compose($id, $extra=null)
	{
		$me = new self($id, '', $extra);
		return $me->html();
	}

	public function input()
	{
		echo $this->html();
	}

	private function html()
	{
		\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));
		\wp_enqueue_script('lws-checkbox');
		\wp_enqueue_style('lws-checkbox');
		\wp_enqueue_script('lws-adm-duration', LWS_ADMIN_PANEL_JS.'/controls/duration.js', array('jquery'), LWS_ADMIN_PANEL_JS, true);

		$duration = \LWS\Adminpanel\Duration::fromString($this->hasExtra('value') ? $this->getExtraValue('value') : get_option($id, ''));

		$html = $this->checkbox($duration);
		$html .= $this->value($duration);
		$html .= $this->period($duration);
		$html .= $this->master($duration);
		return "<span class='lws-editlist-opt-multi lws_adm_durationfield'>".$html."</span>";
	}

	protected function master($duration)
	{
		$d = $duration->toString();
		return "<input name='{$this->m_Id}' type='hidden' class='lws_adm_lifetime_master' value='$d'/>";
	}

	protected function period($duration)
	{
		$units = array(
			'D' => __("Days", LWS_ADMIN_PANEL_DOMAIN),
			'M' => __("Months", LWS_ADMIN_PANEL_DOMAIN),
			'Y' => __("Years", LWS_ADMIN_PANEL_DOMAIN)
		);
		$hidden = $duration->isNull() ? ' style="display:none"' : '';
		$p = $duration->getPeriod();

		$period = "<select class='{$this->style} lac_select lws_adm_lifetime_unit' data-mode='select'$hidden>";
		foreach( $units as $value => $text )
		{
			$selected = ($p == $value ? ' selected' : '');
			$period .= "<option value='$value'$selected>$text</option>";
		}
		$period .= "</select>";
		return $period;
	}

	protected function value($duration)
	{
		$title = esc_attr(__("An integer value greater than zero.", LWS_ADMIN_PANEL_DOMAIN));
		$hidden = $duration->isNull() ? ' style="display:none"' : '';
		$v = $duration->getCount();

		return "<input size='4' class='{$this->style} lws_adm_lifetime_value' pattern='\\d*' title='$title' maxlength='4' type='text' value='$v'$hidden/>";
	}

	protected function checkbox($duration)
	{
		$checked = $duration->isNull() ? '' : ' checked';
		return "<input id='{$this->m_Id}' type='checkbox' class='lws_checkbox lws_adm_lifetime_check'$checked>";
	}
}
