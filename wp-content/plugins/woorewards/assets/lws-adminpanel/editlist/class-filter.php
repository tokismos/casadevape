<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Filter") ) :

/** Allows you to add filter input or link to the editlist.
 * It is up to you to apply in your EditListSource::read implementation (looking at $_POST or $_GET...)
 * This class provide only a way to display it in rightfull place.
 * Do not insert your <input>, if any, in a <form>. It will be created on-the-fly.
 *
 * You can extends this class and overload input function
 * or provide a CALLABLE to return your html code. */
class Filter
{

	/** The filter inputs.
	 *	@return a string with the form content.
	 * @note use class lws-input-enter-submit on a <input>
	 * to allow validation by pressing enter key without submit button. */
	function input($above=true)
	{
		if( isset($this->_callback) && !is_null($this->_callback) && is_callable($this->_callback) )
			return call_user_func($this->_callback, $above);
		else if( isset($this->_content) && !is_null($this->_content) && is_string($this->_content) )
			return $this->_content;
		else
			return "";
	}

	/** The filter will be defined by a callback function
	 *  @param $callable a php CALLABLE which will provide the html code.
	 * @return a EditListFilter instance */
	static function callback($callable, $class='')
	{
		$inst = new Filter($class);
		$inst->_callback = $callable;
		return $inst;
	}

	/** The filter is provided as is. Good for simple static html code.
	 * @return a EditListFilter instance */
	static function content($html, $class='')
	{
		$inst = new Filter($class);
		$inst->_content = $html;
		return $inst;
	}

	/** provided for convenience.
	 * @return build a url to apply filter with given arguments.
	 * @param $getArgs is an array of (variable_name => value),
	 * this should be read as $_GET in your custom EditListSource::read implementation.
	 * @note cannot be used with EditListFilter::content since we must be at display step to know data. */
	static function url($getArgs=array())
	{
		if( isset($_REQUEST['page']) )
			$getArgs['page'] = \sanitize_text_field($_REQUEST['page']);
		if( isset($_REQUEST['tab']) )
			$getArgs['tab'] = \sanitize_text_field($_REQUEST['tab']);
		return add_query_arg($getArgs, admin_url('/admin.php'));
	}

	function __construct($class='')
	{
		$this->_callback = null;
		$this->_content = null;
		$this->_class = $class;
	}

	function cssClass()
	{
		$c = "lws-editlist-filter";
		if( !empty($this->_class) )
			$c .= (' ' . $this->_class);
		return $c;
	}

}

/** A simple text field with a button.
 * Look for $_GET[$name] in your EditListSource::read implemention. */
class FilterSimpleField extends Filter
{
	/** @param $name you will get the filter value in $_GET[$name]. */
	function __construct($name, $placeholder, $buttonLabel='')
	{
		parent::__construct();
		$this->_class = "lws-editlist-filter-search lws-editlist-filter-" . strtolower($name);
		$this->name = $name;
		$this->placeholder = \esc_attr($placeholder);
		$this->buttonLabel = (empty($buttonLabel) ? __('Search', LWS_ADMIN_PANEL_DOMAIN) : $buttonLabel);
	}

	function input($above=true)
	{
		$search = '';
		if( isset($_GET[$this->name]) && !empty(trim($_GET[$this->name])) )
			$search = trim(esc_attr($_GET[$this->name]));

		$filterlabel = __('Narrow your search', LWS_ADMIN_PANEL_DOMAIN);

		$retour = "<div class='lws-editlist-filter-box-end'><div class='lws-editlist-filter-box-title'>{$filterlabel}</div>";
		$retour .= "<div class='lws-editlist-filter-box-content'>";
		$retour .= "<label><input type='text' placeholder='{$this->placeholder}' name='{$this->name}' value='$search' class='lws-input-enter-submit lws-ignore-confirm'>";
		$retour .= "<button class='lws-adm-btn lws-editlist-filter-btn'>{$this->buttonLabel}</button></label>";
		$retour .= "</div></div>";
		return $retour;

		/*
		$str = "<div class='lws-editlistfilter-simplefiaaeld'>";
		$str .= "<label><input type='text' placeholder='{$this->placeholder}' name='{$this->name}' value='$search' class='lws-input-enter-submit lws-ignore-confirm'>";
		$str .= "<button class='lws-adm-btn aaa'>{$this->buttonLabel}</button></label>";
		$str .= "</div>";
		return $str;
		*/
	}
}

/** A list of link to set $_GET parameters.
 * Use Filter::url to build urls,
 * you only give additionnal parameters. */
class FilterSimpleLinks extends Filter
{
	/** @param $links array of {key => (array of {var_name => value}} @see EditListFilter::url
	 * @param $suffixes (array of text), foreach link, add a text after label which is out of <a/> (set in same order as $links values).
	 * @param $cssclass a css class added to filter div.
	 * @param $titles foreach link, a human readable text; use same titles key as link key. If not set, link key is used as title.
	 * @param $name if set, add a hidden input to keep trace of choice (usefull for filter combination) and put a difference display for current choice. */
	function __construct($links=array(), $suffixes=array(), $cssclass='', $titles=array(), $name='', $label='')
	{
		parent::__construct();
		$this->_class = "lws-editlist-filter-selection";
		if( !empty($cssclass) )
			$this->_class .= " $cssclass";
		$href = array();
		if(empty($label)) $label = __("Filter the results", LWS_ADMIN_PANEL_DOMAIN);
		$retour = "<div class='lws-editlist-filter-box'><div class='lws-editlist-filter-box-title'>{$label}</div>";
		$retour .= "<div class='lws-editlist-filter-box-content'>";
		$str = '';
		$index = 0;
		$name = $this->guessName($links, $name);

		foreach( $links as $a => $url )
		{
			$href = Filter::url($url);
			if( !empty($str) )
				$str .= " | ";
			$add = (count($suffixes) > $index ? $suffixes[$index] : '');
			$title = !empty($titles) && isset($titles[$a]) ? $titles[$a] : $a;

			if( !empty($name) && (isset($_GET[$name]) ? $_GET[$name] : '') == $a )
				$str .= "<span class='lws-editlist-filter-selected'>$title</span> $add";
			else
				$str .= "<a href='$href'>$title</a> $add";

			$index++;
		}
		$retour .= $str."</div></div>";
		$this->_content = $retour;

		if( !empty($name) && isset($_GET[$name]) )
		{
			$lastValue = esc_attr($_GET[$name]);
			$this->_content .= "<input type='hidden' name='$name' value='$lastValue' />";
		}
	}

	protected function guessName($links, $name)
	{
		if( empty($name) && !empty($links) && !empty($first = reset($links)) && is_array($first) )
		{
			$name = array_keys($first)[0];
			foreach( $links as $a => $url )
			{
				if( empty($first = reset($links)) || !is_array($first) || array_keys($first)[0] != $name )
				{
					$name = '';
					break;
				}
			}
		}
		return $name;
	}
}

endif
?>
