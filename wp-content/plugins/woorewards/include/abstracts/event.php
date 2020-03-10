<?php
namespace LWS\WOOREWARDS\Abstracts;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_INCLUDES . '/core/pool.php';

/** Base class for each way to earn points.
 *	To be used, an Event must be declare by calling Event::register @see IRegistrable
 *
 *	The final purpose of an Event is to generate points @see addPoint()
 *
 *	Each pool is in charge to :
 * * install its selected events @see _install()
 * * save specific settings @ss _save()
 * * load specific data @see _fromPost()
 *
 *	Anyway, an event is available for information or selection and so can be instanciated from anywhere.
 *  */
abstract class Event implements ICategorisable, IRegistrable
{
	const POST_TYPE = 'lws-wre-event';
	private static $s_events = array();

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	abstract protected function _fromPost(\WP_Post $post);
	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	abstract protected function _save($id);
	/** @return a human readable type for UI */
	abstract public function getDisplayType();
	/** Add hook to grab events and add points. */
	abstract protected function _install();
	/** @return (int|false) point that should be earned for the given context in case that event is triggered.
	 * Return false if no estimation is possible. */
	function guessEarning($context='') { return false; }

	/**	@return array of data to feed the form @see getForm.
	 *	Each key should be the name of an input balise. */
	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		return array(
			$prefix.'multiplier' => $this->getMultiplier(),
			$prefix.'title' => isset($this->title) ? $this->title : ''
		);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of returned inputs, default is an edition in editlist.
	 *	@return (string) the inside of a form (without any form balise).
	 *	@notice in override, dedicated option name must be type specific @see getDataKeyPrefix()
	 *	dedicated DOM must declare css attribute for hidden/show editlist behavior
	 * 	@code
	 *		class='lws_woorewards_system_choice {$this->getType()}'
	 *	@endcode
	 *	You can use several placeholder balises to insert DOM in middle of previous form (take care to keep for anyone following).
	 *	For each fieldset (numbered from 0, 1...) @see str_replace @see getFieldsetPlaceholder()
	 *	@code
	 *	<!-- [fieldset-1-head:{$this->getType()}] -->
	 *	<!-- [fieldset-1-foot:{$this->getType()}] -->
	 *	@endcode */
	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$str = $this->getFieldsetBegin(0, __("Earning Method", LWS_WOOREWARDS_DOMAIN), 'col30 pdr5', false);

		$str .= "<tr><td class='lcell' nowrap><div class='lws-$context-opt-title'>".__("Method", LWS_WOOREWARDS_DOMAIN)."</div></td>";
		$str .= "<td class='rcell rcell-choice-title'><div class='lws-wr-choice-title lws_woorewards_system_type_info'>".$this->getDisplayType()."</div>";
		$str .= "</td></tr>";
		$str .= $this->getFieldsetPlaceholder(true, 0); // type will always be first, so exceptionnaly put at second place

		// custom title
		$label = _x("Title", "Event title", LWS_WOOREWARDS_DOMAIN);
		$placeholder = \esc_attr(\apply_filters('the_title', $this->getDisplayType(), $this->getId()));
		$value = isset($this->title) ? \esc_attr($this->title) : '';
		$str .= "<tr><td class'lcell' nowrap><label for='{$prefix}title' class='lws-$context-opt-title'>$label</label></td>";
		$str .= "<td class='rcell'><div class='lws-editlist-fs-table-row-rcell lws-$context-opt-input'><input type='text' id='{$prefix}title' name='{$prefix}title' value='$value' placeholder='$placeholder' /></div>";
		$str .= "</td></tr>";

		$str .= $this->getFieldsetEnd(0);
		$str .= $this->getFieldsetBegin(1, __("Method Points", LWS_WOOREWARDS_DOMAIN), 'col10 pdr5');

		// multiplier
		$label = _x("Earned points", "Event point multiplier", LWS_WOOREWARDS_DOMAIN);
		$placeholder = '1';
		$value = empty($this->getMultiplier()) ? '' : \esc_attr($this->getMultiplier());
		$str .= "<tr><td class='lcell' nowrap><label for='{$prefix}multiplier' class='lws-$context-opt-title'>$label</label></td>";
		$str .= "<td class='rcell'><div class='lws-$context-opt-input'><input type='text' size='5' id='{$prefix}multiplier' name='{$prefix}multiplier' value='$value' placeholder='$placeholder' pattern='\\d*' /></div>";
		$str .= "</td></tr>";

		$str .= $this->getFieldsetEnd(1);
		return $str;
	}

	/** Provided to be overriden.
	 *	Back from the form, set and save data from @see getForm
	 *	@param $source origin of form values. Expect 'editlist' or 'post'. If 'post' we will apply the stripSlashes().
	 * 	@return true if ok, (false|string|WP_Error) false or an error description on failure. */
	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'multiplier' => '0',
				$prefix.'title'      => 't'
			),
			'defaults' => array(
				$prefix.'multiplier' => '0',
				$prefix.'title'      => ''
			),
			'labels'   => array(
				$prefix.'multiplier' => __("Earned points", LWS_WOOREWARDS_DOMAIN),
				$prefix.'title'      => __("Title", LWS_WOOREWARDS_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$this->setTitle($values['values'][$prefix.'title']);
		$this->setMultiplier($values['values'][$prefix.'multiplier']);
		return true;
	}

	protected function getFieldsetBegin($index, $title='', $css='', $withPlaceholder=true)
	{
		if( !empty($css) )
			$css .= ' ';
		$css .= "lws-editlist-fieldset aaa fieldset-$index";
		$str = "<fieldset class='$css'>";
		if( !empty($title) )
			$str .= "<div class='lws-editlist-title'>$title</div>";
		$str .= "<table class='lws-editlist-fs-table' cellpadding='0' cellspacing='0'>";
		if( $withPlaceholder )
			$str .= $this->getFieldsetPlaceholder(true, $index);
		return $str;
	}

	protected function getFieldsetEnd($index, $withPlaceholder=true)
	{
		$str = $withPlaceholder ? $this->getFieldsetPlaceholder(false, $index) : '';
		return $str . "</table></fieldset>";
	}

	/** @see getForm insert that balise at top and bottom of each fieldset.
	 * @return (string) html */
	protected function getFieldsetPlaceholder($top, $index)
	{
		return "<!-- [fieldset-".intval($index)."-".($top?'head':'foot').":".$this->getType()."] -->";
	}

	protected function getDataKeyPrefix()
	{
		if( !isset($this->dataKeyPrefix) )
			$this->dataKeyPrefix = \esc_attr($this->getType()) . '_';
		return $this->dataKeyPrefix;
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		return $this->getDisplayType();
	}

	/** Purpose of an event: earn point for a pool.
	 *	Call this function when an earning event occurs.
	 *	@param $user (WP_User|int) the user earning points.
	 *	@param $reason (string) the cause of the earning.
	 *	@param $pointCount (int) number of point earned, usually 1 since it is up to the pool to multiply it. */
	protected function addPoint($user, $reason='', $pointCount=1)
	{
		if( !empty($this->getPool()) && !empty($user) )
		{
			if( (is_numeric($user) && $user > 0) || is_a($user, 'WP_User') )
			{
				if( $this->getPool()->userCan($user) )
				{
					$userId = is_numeric($user) ? $user : $user->ID;
					$this->getPool()->addPoints($userId, $this->getMultiplier()*$pointCount, $reason, $this);
					$this->getPool()->tryUnlock($userId);
				}
			}
			else
				error_log("Try to add points to an undefined user: " . print_r($user, false));
		}
	}

	public function install()
	{
		$this->_install();
		\do_action('lws_woorewards_abstracts_event_installed', $this);
	}

	static public function fromPost(\WP_Post $post)
	{
		$type = \get_post_meta($post->ID, 'wre_event_type', true);
		$event = static::instanciate($type);

		if( empty($event) )
		{
//			\lws_admin_add_notice_once('lws-wre-event-instanciate', __("Error occured during rewarding event instanciation.", LWS_WOOREWARDS_DOMAIN), array('level'=>'error'));
		}
		else
		{
			$event->id = intval($post->ID);
			$event->name = $post->post_name;
			$event->title = $post->post_title;
			$event->setMultiplier(\get_post_meta($post->ID, 'wre_event_multiplier', true));

			$event->_fromPost($post);
		}
		return \apply_filters('lws_woorewards_abstracts_event_loaded', $event, $post);
	}

	/** @param $type (string|array) a registered type or an item of getRegistered(). */
	static function instanciate($type)
	{
		$instance = null;
		$registered = (is_string($type) ? static::getRegisteredByName($type) : $type);

		if( is_array($registered) && !empty($registered) )
		{
			try{
				require_once $registered[1];
				$instance = new $registered[0];
			}catch(Exception $e){
				error_log("Cannot instanciate an woorewards Event: " . $e->getMessage());
			}
		}
//		else
//			error_log("Unknown wooreward event registered type from : ".print_r($type, true));

		return $instance;
	}

	public function save(\LWS\WOOREWARDS\Core\Pool &$pool)
	{
		$this->setPool($pool);
		$data = array(
			'ID'          => isset($this->id) ? intval($this->id) : 0,
			'post_parent' => $pool->getId(),
			'post_type'   => self::POST_TYPE,
			'post_status' => $this->getMultiplier() > 0 ? $this->getPoolStatus() : 'draft',
			'post_name'   => $this->getName($pool),
			'post_title'  => isset($this->title) ? $this->title : '',
			'meta_input'  => array(
				'wre_event_multiplier' => $this->getMultiplier(),
				'wre_event_type' => $this->getType(),
			)
		);

		$postId = \wp_insert_post($data, true);
		if( \is_wp_error($postId) )
		{
			error_log("Error occured during event saving: " . $postId->get_error_message());
			\lws_admin_add_notice_once('lws-wre-event-save', __("Error occured during rewarding event saving.", LWS_WOOREWARDS_DOMAIN), array('level'=>'error'));
			return $this;
		}
		$this->id = intval($postId);
		if( isset($this->title) )
			\do_action('wpml_register_string', $this->title, 'title', $this->getPackageWPML(true), __("Title", LWS_WOOREWARDS_DOMAIN), 'LINE');

		$this->_save($this->id);
		\do_action('lws_woorewards_abstracts_event_save_after', $this);
		return $this;
	}

	/** @see https://wpml.org/documentation/support/string-package-translation
	 * Known wpml bug: kind first letter must be uppercase */
	function getPackageWPML($full=false)
	{
		$pack = array(
			'kind' => 'WooRewards Points Earning Method',//strtoupper(self::POST_TYPE),
			'name' => $this->getId(),
		);
		if( $full )
		{
			$title = (isset($this->title) && !empty($this->title)) ? $this->title : ($this->getDisplayType() . '/' . $this->getId());
			if( $pool = $this->getPool() )
				$title = ($pool->getOption('title') . ' - ' . $title);
			$pack['title'] = $title;
			$pack['edit_link'] = \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty', 'tab'=>'wr_loyalty.wr_upool_'.$this->getPoolName()), admin_url('admin.php'));
		}
		return $pack;
	}

	public function getTitle($fallback=true)
	{
		$title = ((isset($this->title) && !empty($this->title)) ? $this->title : ($fallback ? $this->getDisplayType() : ''));
		if( !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
			$title = \apply_filters('wpml_translate_string', $title, 'title', $this->getPackageWPML());
		return \apply_filters('the_title', $title, $this->getId());
	}

	public function setTitle($title='')
	{
		$this->title = $title;
		return $this;
	}

	public function delete()
	{
		if( isset($this->id) && !empty($this->id) )
		{
			\do_action('lws_woorewards_abstracts_event_delete_before', $this);
			if( empty(\wp_delete_post($this->id, true)) )
				error_log("Failed to delete the rewarding event {$this->id}");
			else
			{
				$pack = $this->getPackageWPML();
				\do_action('wpml_delete_package_action', $pack['name'], $pack['kind']);

				unset($this->id);
			}
		}
		return $this;
	}

	/** Declare a new kind of event. */
	static public function register($classname, $filepath, $unregister=false, $typeOverride=false)
	{
		$id = empty($typeOverride) ? self::formatType($classname) : $typeOverride;
		if( $unregister )
		{
			if( isset(self::$s_events[$id]) )
				unset(self::$s_events[$id]);
		}
		else
			self::$s_events[$id] = array($classname, $filepath);
	}

	static public function getRegistered()
	{
		return self::$s_events;
	}

	static public function getRegisteredByName($name)
	{
		return isset(self::$s_events[$name]) ? self::$s_events[$name] : false;
	}

	public function unsetPool()
	{
		if( isset($this->pool) )
			unset($this->pool);
		return $this;
	}

	public function setPool(&$pool)
	{
		$this->pool =& $pool;
		return $this;
	}

	public function getPool()
	{
		return isset($this->pool) ? $this->pool : false;
	}

	public function getPoolName()
	{
		return isset($this->pool) && !empty($this->pool) ? $this->pool->getName() : '';
	}

	public function getPoolType()
	{
		return isset($this->pool) && !empty($this->pool) ? $this->pool->getOption('type') : '';
	}

	public function getPoolStatus()
	{
		if( isset($this->pool) && !empty($this->pool) )
		{
			if( $this->pool->getOption('public') )
				return 'publish';
			else if( $this->pool->getOption('private') )
				return 'private';
			else
				return 'draft';
		}
		return '';
	}

	public function getStackName()
	{
		return isset($this->pool) && !empty($this->pool) ? $this->pool->getStackId() : '';
	}

	public function getId()
	{
		return isset($this->id) ? intval($this->id) : false;
	}

	public function detach()
	{
		if( isset($this->id) )
			unset($this->id);
	}

	/** @param $classname full class with namespace. */
	public static function formatType($classname=false)
	{
		if( $classname === false )
			$classname = \get_called_class();
		return strtolower(str_replace('\\', '_', trim($classname, '\\')));
	}

	public function getType()
	{
		return static::formatType($this->getClassname());
	}

	function getClassname()
	{
		return \get_class($this);
	}

	/** Multiplier is registered by Pool, it is applied to the points generated by the event. */
	public function getMultiplier($context='edit')
	{
		return isset($this->multiplier) ? $this->multiplier : 1;
	}

	public function setMultiplier($multiplier)
	{
		$this->multiplier = $multiplier;
		return $this;
	}

	public function setName($name)
	{
		$this->name = $name;
	}

	public function getName($pool=null)
	{
		if( isset($this->name) )
			return $this->name;
		else if( !empty($this->getPool()) )
			return $this->getPool()->getName() . '-' . $this->getType();
		else if( !empty($pool) )
			return $pool->getName() . '-' . $this->getType();
		else
			return $this->getType();
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_STANDARD  => __("Standard", LWS_WOOREWARDS_DOMAIN),
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING => __("Levelling", LWS_WOOREWARDS_DOMAIN),
			'achievement' => __("Achievement", LWS_WOOREWARDS_DOMAIN),
			'custom'      => __("Events", LWS_WOOREWARDS_DOMAIN)
		);
	}
}

?>