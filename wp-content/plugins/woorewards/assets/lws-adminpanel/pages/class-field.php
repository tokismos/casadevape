<?php
namespace LWS\Adminpanel\Pages;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Field") ) :


/** Créé un champ sur la page d'administrtion.
 *	La valeur saisie pourra être utilisée via get_option($key) */
abstract class Field
{
	public $Notices = array();

	/** an arry with key is type id and value is:
	 *   the fully qualified classname
	 *   or an array with
	 *  	 0: the fully qualified classname
	 *     1: the file path to include if not like ./adminpanel/pages/field/{$type_id}.php
	 * @see types() */
	protected static $Types = array(
    'autocomplete' => '\LWS\Adminpanel\Pages\Field\Autocomplete',
    'border'       => '\LWS\Adminpanel\Pages\Field\Border',    // CSS
    'box'          => '\LWS\Adminpanel\Pages\Field\Checkbox',
    'button'       => '\LWS\Adminpanel\Pages\Field\Button',
    'color'        => '\LWS\Adminpanel\Pages\Field\Color',      // CSS
    'cssbutton'    => '\LWS\Adminpanel\Pages\Field\CSSButton',  // CSS
    'div'          => '\LWS\Adminpanel\Pages\Field\Div',        // CSS
    'duration'     => '\LWS\Adminpanel\Pages\Field\Duration',
    'editlist'     => '\LWS\Adminpanel\Pages\Field\Editlist',
    'filler'       => '\LWS\Adminpanel\Pages\Field\Filler',
    'font'         => '\LWS\Adminpanel\Pages\Field\Font',      // CSS
    'stygen'       => '\LWS\Adminpanel\Pages\Field\StyGen',
    'themer'       => '\LWS\Adminpanel\Pages\Field\Themer',
    'googleapikey' => '\LWS\Adminpanel\Pages\Field\GoogleAPIsKey',
    'help'         => '\LWS\Adminpanel\Pages\Field\Help',
    'hidden'       => '\LWS\Adminpanel\Pages\Field\Hidden',
    'input'        => '\LWS\Adminpanel\Pages\Field\Input',
    'media'        => '\LWS\Adminpanel\Pages\Field\Media',
    'radio'        => '\LWS\Adminpanel\Pages\Field\Radio',
    'select'       => '\LWS\Adminpanel\Pages\Field\Select',
    'lacinput'     => '\LWS\Adminpanel\Pages\Field\LacInput',
    'lacselect'    => '\LWS\Adminpanel\Pages\Field\LacSelect',
    'lactaglist'   => '\LWS\Adminpanel\Pages\Field\LacTaglist',
    'lacchecklist' => '\LWS\Adminpanel\Pages\Field\LacChecklist',
    'slug'         => '\LWS\Adminpanel\Pages\Field\Slug',
    'text'         => '\LWS\Adminpanel\Pages\Field\Text',
    'textarea'     => '\LWS\Adminpanel\Pages\Field\TextArea',
    'taglist'      => '\LWS\Adminpanel\Pages\Field\TagList',
    'url'          => '\LWS\Adminpanel\Pages\Field\URL',
    'wpeditor'     => '\LWS\Adminpanel\Pages\Field\WPEditor',
		// deprecated, @see autocomplete instead
		'page'=>array('\LWS\Adminpanel\Pages\FieldPage', LWS_ADMIN_PANEL_PATH . '/pages/field/deprecated.php'),
		'user'=>array('\LWS\Adminpanel\Pages\FieldUser', LWS_ADMIN_PANEL_PATH . '/pages/field/deprecated.php'),
		// deprecated, fallback to textarea for backward compatibility
    'ace'          => array('\LWS\Adminpanel\Pages\Field\TextArea', LWS_ADMIN_PANEL_PATH . '/pages/field/class-textarea.php')
	);

	public static $Style = "lws-input";

	/** Write html input line. */
	abstract public function input();

	/** Override to define default extra parameters (used to customise behavior or aspect as width...).
	 *	Should return an empty array if not used. */
	protected function dft(){ return array(); }

	public static function autoload($class)
	{
		//if( substr($class, 0, 20) == 'LWS\Adminpanel\Pages' )
		if( substr($class, 0, 4) == 'LWS\\' )
		{
			$class = "\\$class";
			foreach( self::types() as $key => $definition )
			{
				$file = false;
				if( is_array($definition) )
				{
					if( $class == $definition[0] )
					{
						if( count($definition) > 1 )
							$file = $definition[1];
						else
							$file = dirname(__FILE__)."/field/class-{$key}.php";
					}
				}
				else if( $class == $definition )
				{
					$file = dirname(__FILE__)."/field/class-{$key}.php";
				}
				if( $file !== false )
				{
					@include_once $file;
					return true;
				}
			}
		}
	}

	/** Allow plugins add custom field types by filter 'lws_adminpanel_field_types'
	 * @see Field::$Types */
	public static function types()
	{
		static $types = null;
		if( is_null($types) )
			$types = apply_filters('lws_adminpanel_field_types', self::$Types);
		return $types;
	}

	/** Make a new instance of derived field of type $type.
	 *	and return it, or null if $type is unknown. */
	public static function create($type, $id, $title, $extra=null)
	{
		$inst = null;
		$types = Field::types();
		if( isset($types[$type]) && !empty($types[$type]) )
		{
			$def = $types[$type];
			$classname = is_array($def) ? $def[0] : $def;
			$inst = new $classname($id, $title, $extra);
		}
		else
			error_log(__NAMESPACE__ . ' : field type "' . $type . '" is not supported.');
		return $inst;
	}

	public static function hasLimitedRequirement($type)
	{
		return in_array($type, array('googleapikey', 'help', 'filler'));
	}

	/** register to wordpress if required, then return $this.
	 * Any extra 'subids' (string|array) will be registered too.
	 * @param $page the admin page id that displays this field.
	 * @return $this */
	public function register($page)
	{
		$this->ownerPage = $page;

		if( !$this->isGizmo() )
		{
			\register_setting( $page, $this->id() );

			if( isset($this->extra['wpml']) && !empty($this->extra['wpml']) )
			{
				$wpmlTitle = $this->extra['wpml'];
				\add_action(
					"update_option_{$this->m_Id}",
					function($old_value, $value, $option)use($wpmlTitle){
						\do_action('wpml_register_single_string', 'Widgets', ucfirst($wpmlTitle), $value);
					},
					10, 3
				);
			}
		}

		if( isset($this->extra['subids']) )
		{
			$subids = is_array($this->extra['subids']) ? $this->extra['subids'] : array($this->extra['subids']);
			foreach( $subids as $k => $v )
			{
				$sub = is_string($k) ? $k : $v;
				\register_setting($page, $sub);

				$wpmlTitle = $this->title() . ' - ' . $v;
				\add_action(
					"update_option_{$sub}",
					function($old_value, $value, $option)use($wpmlTitle){
						\do_action('wpml_register_single_string', 'Widgets', ucfirst($wpmlTitle), $value);
					},
					10, 3
				);
			}
		}

		return $this;
	}

	public function __construct($id, $title, $extra=null)
	{
		$this->m_Id = $id;
		$this->m_Title = $title;
		$this->style = Field::$Style;
		$this->readExtra($this->dft(), $extra);
	}

	public function id()
	{
		return $this->m_Id;
	}

	public function title()
	{
		return $this->m_Title;
	}

	public function help()
	{
		if( $this->hasExtra('help', 's') )
			return $this->extra['help'];
		else
			return '';
	}

	/** format title (in span element) */
	public function label()
	{
		return "<div class='lws-field-label'>{$this->m_Title}</div>";
	}

	public function getTooltips()
	{
		return $this->getExtraValue('tooltips');
	}

	/** @param $strict not null/zero and not empty. */
	protected function hasExtra($key, $strict=false)
	{
		if( isset($this->extra[$key]) )
		{
			if( !$strict ) return true;
			else if( !empty($this->extra[$key]) )
			{
				if( $strict === true ) return true;
				else if( $strict == 's' ) return is_string($this->extra[$key]);
				else if( $strict == 'd' ) return is_numeric($this->extra[$key]);
				else if( $strict == 'a' ) return is_array($this->extra[$key]);
				else return is_a($this->extra[$key], $strict);
			}
		}
		return false;
	}

	protected function getExtraValue($key, $default='')
	{
		return isset($this->extra[$key]) ? $this->extra[$key] : $default;
	}

	protected function getExtraAttr($key, $attr, $default=false)
	{
		if( isset($this->extra[$key]) )
			return " {$attr}='" . esc_attr($this->extra[$key]) . "'";
		else if( $default !== false )
			return " {$attr}='" . esc_attr($default) . "'";
		else
			return '';
	}

	private function readExtra($default, $extra)
	{
		$this->extra = $default;
		if( is_array($extra) )
		{
			foreach( $extra as $k => $v )
				$this->extra[$k] = $v;
		}
	}

	/** @return 1. extra value, wp option, extra default, empty string. */
	protected function readOption($esc_attr=true)
	{
		$value = '';
		if( isset($this->extra['value']) )
			$value = $this->extra['value'];
		else
		{
			$value = \get_option($this->m_Id, false);
			if( false === $value )
				$value = isset($this->extra['default']) ? $this->extra['default'] : '';
		}
		return $esc_attr ? \esc_attr($value) : $value;
	}

	/** Advanced fields are hidden by default. User must clic on a dedicated button to show them. */
	public function isAdvanced()
	{
		return boolval($this->getExtraValue('advanced', false));
	}

	/** @return true if the input must be only registered but will be a hidden input.
	 * input still be called, but no new row is added in html.
	 * extra contains 'hidden'=>true
	 * @see LWSFields.mergeInput in fields.js */
	public function isHidden()
	{
		return boolval($this->getExtraValue('hidden', false));
	}

	/** if a master name is indicated in extra ('master'=>'a master input name'),
	 * this function return " data-master='a master input name'",
	 * else, it return an empty string.
	 * @see LWSFields.mergeInput in fields.js
	 * Note element with a master will not be registered to be saved. */
	public function getMasterHtml()
	{
		return $this->getExtraAttr('master', 'data-master');
	}

	/** A Gizmo field is provided only for display but is not registered to WordPress to be saved. */
	public function isGizmo()
	{
		if( isset($this->gizmo) && $this->gizmo ) return true;
		else if( isset($this->extra['gizmo']) && boolval($this->extra['gizmo']) ) return true;
		else return !empty($this->getExtraValue('master'));
	}

	public function uninstall()
	{
		delete_option($this->m_Id);
	}
}

spl_autoload_register('\LWS\Adminpanel\Pages\Field::autoload');


abstract class FieldCSSGroup extends \LWS\Adminpanel\Pages\Field
{
	protected abstract function cssPairs();

	public function readExtraValues()
	{
		$this->saved = isset($this->extra['values']) ? $this->extra['values'] : array();
		$this->defaults = isset($this->extra['defaults']) ? $this->extra['defaults'] : array();
		$this->defaults = \wp_parse_args($this->defaults, $this->cssPairs());
		$this->values = array_merge($this->defaults, $this->saved);
		$this->source = array_key_exists('source', $this->extra) ? $this->extra['source'] : '';
	}

	public function eHSeparator($width=null)
	{
		$w = is_null($width) ? '' :  " style='width:{$width}px'";
		echo "<div class='lwss-font-hor-sep'$w></div>";
	}

	public function eVSeparator($height=null)
	{
		$h = is_null($height) ? '' :  " style='height:{$height}px'";
		echo "<div class='lwss-bloc-vertical-separator'$h></div>";
	}

	/** @return an html property with saved values for this field. */
	public function mergedProps($other=array())
	{
		$props = array();
		foreach($this->saved as $k => $v)
			$props[] = "$k:$v";
		if( !empty($other) && is_array($other) ){
			foreach($other as $k => $v)
				$props[] = "$k:$v";
		}
		$val = esc_attr(implode(';', $props));
		return "value='$val'";
	}
}

endif
?>
