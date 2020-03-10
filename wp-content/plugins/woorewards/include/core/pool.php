<?php
namespace LWS\WOOREWARDS\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage set of Event/Unlockable with PointStack. */
class Pool
{
	const POST_TYPE = 'lws-wre-pool';

	const T_STANDARD = 'standard';
	const T_LEVELLING = 'levelling';

	protected $events = null;
	protected $unlockables = null;
	protected $points = null;

	protected $name                = '';        /// to get it by code (should be unique).
	protected $status              = 'draft';
	protected $title               = '';        /// unfiltered display name
	protected $displayName         = '';        /// to display to user
	protected $pointStackId        = false;     /// usually a pool has its own stack but some can share
	private   $deletable           = true;      /// the user can delete it (prefabs are not deletable)
	protected $type                = self::T_STANDARD;/// @enum {'standard', 'levelling'}
	protected $categoriesWhitelist = false;     /// to authorise only a set of Event and Unlockable in this pool
	protected $categoriesBlacklist = false;     /// to forbid few of Event and Unlockable in this pool
	protected $options             = null;      /// a basic class to store custom options

	/** A pool is active if set as activated and, if period is defined, today is included in. */
	public function isActive()
	{
		$active = in_array($this->status, array('publish', 'private'));
		return \apply_filters('lws_woorewards_core_pool_is_active', $active, $this);
	}

	/** Add points to the pool point stack of a user.
	 *	@param $user (int) the user earning points.
	 *	@param $value (int) final number of point earned.
	 *	@param $reason (string) optional, the cause of the earning.
	 *	@param $origin (Event) optional, the source Event. */
	public function addPoints($userId, $value, $reason='', \LWS\WOOREWARDS\Abstracts\Event $origin=null)
	{
		$value = \apply_filters('lws_woorewards_core_pool_point_add', $value, $userId, $reason, $this, $origin);
		$this->getStack($userId)->add($value, $reason);
		return $this;
	}

	/** Define the point amount of the pool point stack of a user.
	 *	@param $user (int) the user earning points.
	 *	@param $value (int) the point amount of a user to set.
	 *	@param $reason (string) optional, the cause of the earning.
	 *	@param $origin (Event) optional, the source Event. */
	public function setPoints($userId, $value, $reason='')
	{
		$value = \apply_filters('lws_woorewards_core_pool_point_set', $value, $userId, $reason, $this);
		$this->getStack($userId)->set($value, $reason);
		return $this;
	}

	/** Remove points from the pool point stack of a user.
	 *	@param $userId (int) the user earning points.
	 *	@param $value (int) the point amount of a user to substract.
	 *	@param $reason (string) optional, the cause of the earning. */
	public function usePoints($userId, $value, $reason='')
	{
		$value = \apply_filters('lws_woorewards_core_pool_point_sub', $value, $userId, $reason, $this);
		$this->getStack($userId)->sub($value, $reason);
		return $this;
	}

	/** Read the point amount of the pool point stack of a user.
	 *	@param $user (int) the user earning points.
	 * @return int */
	public function getPoints($userId)
	{
		return \apply_filters('lws_woorewards_core_pool_point_get', $this->getStack($userId)->get(), $this);
	}

	/** Based on user point, check possible unlockable.
	 *	Based on pool setting, apply it or mail user about a choice.
	 *	@param $user (int) the user who consume its points.
	 *	@return (int) the count of unlock. */
	public function tryUnlock($userId)
	{
		$uCount = 0;
		if( empty($user = \get_user_by('ID', $userId)) )
		{
			error_log("Unlock reward attempt ($unlockableName) for unknown user ($userId).");
			return $uCount;
		}

		$tryUnlock = true;
		while( $tryUnlock )
		{
			$tryUnlock = false;
			$points = $this->getPoints($userId);
			$availables = $this->_getGrantedUnlockables($points, $userId);

			if( $availables->count() >= 1 )
			{
				// immediate unlock
				$unlockable = $availables->last();
				if( $this->_applyUnlock($user, $unlockable) )
				{
					$tryUnlock = $this->_payAndContinue($userId, $unlockable);
					$uCount++;
				}
			}
			else
				return $uCount;
		}
		return $uCount;
	}

	/** @return collection of unlockable sorted by cost ASC. */
	public function getGrantedUnlockablesForUser($userId)
	{
		$points = $this->getPoints($userId);
		return $this->_getGrantedUnlockables($points, $userId);
	}

	/** @return collection of unlockable sorted by cost ASC. */
	public function getGrantedUnlockablesWithPoints($points)
	{
		return $this->_getGrantedUnlockables($points);
	}

	/** @return sorted by cost ASC unlockable available with given point amount. */
	protected function _getGrantedUnlockables($points, $userId=null)
	{
		$availables = $this->unlockables->filter(function($item)use($points, $userId){
			return $item->isPurchasable($points, $userId);
		});
		return $availables->sort();
	}

	/** apply the unlockable and keep a trace.
	 * @return (bool) if really unlocked */
	protected function _applyUnlock($user, &$unlockable)
	{
		if( $unlockable->apply($user) )
		{
			\do_action('lws_woorewards_core_pool_single_unlocking', $unlockable, $user, $this);
			$userId = is_numeric($user) ? $user : $user->ID;
			if( !empty($userId) && !empty($unlockable->getId()) && !in_array($unlockable->getId(), \get_user_meta($userId, 'lws_wre_unlocked_id', false)) )
			{
				\add_user_meta($userId, 'lws_wre_unlocked_id', $unlockable->getId(), false);
			}
			return true;
		}
		return false;
	}

	/**	Consume any required points.
	 *	@return if we can try to unlock more. */
	protected function _payAndContinue($userId, &$unlockable)
	{
		$cost = $unlockable->getCost('pay');
		if( $cost > 0 && !$this->getOption('point_add_only', false) )
			$this->usePoints($userId, $cost, $unlockable->getReason('raw'));
		return ($cost > 0);
	}

	/** Some configuration sets are relevant as specific pool kind.
	 *	@return array of option */
	public function getDefaultConfiguration($type)
	{
		$config = array();
		if( $type == \LWS\WOOREWARDS\Core\Pool::T_STANDARD )
		{
			$config = array(
				'public'         => true
			);
		}
		return \apply_filters('lws_woorewards_core_pool_default_configuration', $config, $type);
	}

	/** @param (string) option name.
	 * For option list @see getOption()
	 *
	 * Options are:
	 * * public        : (bool) enabled and visible to customer (public, private and disabled are exclusive).
	 * * private       : (bool) enable but not visible for customer (public, private and disabled are exclusive).
	 * * disabled      : (bool) not visible and not activated (public, private and disabled are exclusive).
	 * * title         : (string) unfiltered title.
	 * * stack         : (string) name of point stack, usually same as pool name. Ignored if new value is empty.
	 * * type          : (string) [standard, levelling] Apply a set of default configuration (use it carefully).
	 * * whitelist     : (array of string) Event and Unlockable category that could be used with this pool.
	 * * whitelist+    : same as 'whitelist' but append to existant.
	 * * whitelist-    : same as 'whitelist' but remove from existant.
	 * * blacklist     : (array of string) Event and Unlockable category that could not be used with this pool.
	 * * blacklist+    : same as 'blacklist' but append to existant.
	 * * blacklist-    : same as 'blacklist' but remove from existant.
	 **/
	public function setOption($option, $value)
	{
		switch($option)
		{
			case 'public':
				$this->status = (boolval($value) ? 'publish' : 'draft');
				break;
			case 'private':
				$this->status = (boolval($value) ? 'private' : 'draft');
				break;
			case 'disabled':
				$this->status = (boolval($value) ? 'draft' : 'publish');
				break;
			case 'enabled':
				if( boolval($value) )
				{
					if( !in_array($this->status, array('publish', 'private')) )
						$this->status = 'publish';
				}
				else
					$this->status = 'draft';
				break;
			case 'title':
				$this->title = \esc_html($value);
				if( isset($this->display_title) )
					unset($this->display_title);
				break;
			case 'stack':
				$this->setStackId($value);
				break;
			case 'type':
				$config = $this->getDefaultConfiguration($value);
				if( isset($config['type']) )
					unset($config['type']); // sure someone will have fun to make infinite loop som day
				$this->setOptions($config);
				$this->type = $value;
				break;
			case 'whitelist':
				$this->categoriesWhitelist = (empty($value) ? false : (is_array($value) ? $value : array($value)));
				break;
			case 'whitelist+':
				$this->categoriesWhitelist = array_unique(array_merge($this->categoriesWhitelist, is_array($value) ? $value : array($value)), SORT_REGULAR);
				break;
			case 'whitelist-':
				$this->categoriesWhitelist = array_diff($this->categoriesWhitelist, is_array($value) ? $value : array($value));
				break;
			case 'blacklist':
				$this->categoriesBlacklist = (empty($value) ? false : (is_array($value) ? $value : array($value)));
				break;
			case 'blacklist+':
				$this->categoriesBlacklist = array_unique(array_merge($this->categoriesBlacklist, is_array($value) ? $value : array($value)), SORT_REGULAR);
				break;
			case 'blacklist-':
				$this->categoriesBlacklist = array_diff($this->categoriesBlacklist, is_array($value) ? $value : array($value));
				break;
			default:
				if( !$this->_setCustomOption($option, $value) )
					$this->options = \apply_filters('lws_woorewards_core_pool_custom_option_value_set', $this->options, $option, $value, $this);
		}
		\do_action('lws_woorewards_core_pool_option_set', $this, $option, $value);
		return $this;
	}

	/** @return the option is accepted. */
	protected function _setCustomOption($option, $value)
	{
		return false;
	}

	/** @param (string) option name
	 * @param $default return that value if option does not exists.
	 *
	 * Options are:
	 * * public        : (bool) enabled and visible to customer (public, private and disabled are exclusive).
	 * * private       : (bool) enable but not visible for customer (public, private and disabled are exclusive).
	 * * disabled      : (bool) not visible and not activated (public, private and disabled are exclusive).
	 * * title         : (string) unfiltered title.
	 * * display_title : (string) filtered title (see get_title hook from WP_Post) cannot be active after recording.
	 * * stack         : (string) name of point stack, usually same as pool name.
	 * * type          : (string) [standard, levelling] define a set of default option (read-only for not advanced user).
	 * * whitelist     : (array of string) Event and Unlockable category that could be used with this pool.
	 * * blacklist     : (array of string) Event and Unlockable category that could not be used with this pool.
	 **/
	public function getOption($option, $default=null)
	{
		$value = $default;
		switch($option)
		{
			case 'public':
				$value = ($this->status == 'publish');
				break;
			case 'private':
				$value = ($this->status == 'private');
				break;
			case 'disabled':
				$value = !in_array($this->status, array('publish', 'private'));
				break;
			case 'enabled':
				$value = in_array($this->status, array('publish', 'private'));
				break;
			case 'title':
				$value = $this->title;
				break;
			case 'display_title':
				if( isset($this->display_title) )
					$value = $this->display_title;
				else
				{
					$this->display_title = (empty($this->displayName) ? $this->title : $this->displayName);
 					$this->display_title = \apply_filters('wpml_translate_string', $this->display_title, 'title', $this->getPackageWPML());
					$this->display_title = \apply_filters('the_title', $this->display_title, $this->getId());
					$value = $this->display_title;
				}
				break;
			case 'stack':
				$value = $this->getStackId();
				break;
			case 'type':
				$value = $this->type;
				break;
			case 'whitelist':
				$value = empty($this->categoriesWhitelist) ? array() : $this->categoriesWhitelist;
				break;
			case 'blacklist':
				$value = empty($this->categoriesBlacklist) ? array() : $this->categoriesBlacklist;
				break;
			default:
				$value = $this->_getCustomOption($option, $default);
		}
		return \apply_filters('lws_woorewards_core_pool_option_value_get', $value, $option, $this);
	}

	/** @param $accepted (in/out bool) the option is recognized.
	 * @return $default if $option is unknown. */
	protected function _getCustomOption($option, $default)
	{
		return $default;
	}

	public function setOptions($options)
	{
		foreach($options as $option => $value)
			$this->setOption($option, $value);
		return $this;
	}

	/** @return associative array of option values.
	 * One entry for each item in $options, that item is used as key for returned array.
	 * @param $options array of option name.
	 * @param $default associative array [option_name => default_value] */
	public function getOptions($options, $default=array())
	{
		$values = array();
		foreach($options as $option)
		{
			$values[$option] = $this->getOption($option, isset($default[$option]) ? $default[$option] : null);
		}
		return $values;
	}

	/** @return the added item or false on failure.
	 * Take care name could be changed to avoid duplication. */
	public function addEvent(\LWS\WOOREWARDS\Abstracts\Event $event, $multiplier=false)
	{
		$event->setPool($this);
		if( $multiplier !== false )
			$event->setMultiplier($multiplier);
		return $this->events->add($event);
	}

	/** @return the added item or false on failure.
	 * Take care name could be changed to avoid duplication. */
	public function addUnlockable(\LWS\WOOREWARDS\Abstracts\Unlockable $unlockable, $cost=false)
	{
		$unlockable->setPool($this);
		if( $cost !== false )
			$unlockable->setCost($cost);
		return $this->unlockables->add($unlockable);
	}

	/** update, replace or add the event in this pool.
	 * Events are looked by id, then name. */
	public function updateEvent(\LWS\WOOREWARDS\Abstracts\Event $event)
	{
		$event->setPool($this);
		$this->events->update($event);
		return $this;
	}

	/** update, replace or add the unlockable in this pool.
	 * Unlockables are looked by id, then name. */
	public function updateUnlockable(\LWS\WOOREWARDS\Abstracts\Unlockable $unlockable)
	{
		$unlockable->setPool($this);
		$this->unlockables->update($unlockable);
		return $this;
	}

	/** @return the registered event collection. */
	public function getUnlockables()
	{
		return $this->unlockables;
	}

	/** @return the registered event collection. */
	public function getEvents()
	{
		return $this->events;
	}

	/** @param $unlockable (Event|string) instance or name.
	 * @return the instance or false if not found. */
	public function findEvent($event)
	{
		return $this->events->find($event);
	}

	/** @param $unlockable (Unlockable|string) instance or name.
	 * @return the instance or false if not found. */
	public function findUnlockable($unlockable)
	{
		return $this->unlockables->find($unlockable);
	}

	/** @param $unlockable (Event|string) instance or name.
	 * @return the removed item or false if not found. */
	public function removeEvent($event)
	{
		return $this->events->remove($event);
	}

	/** @param $unlockable (Unlockable|string) instance or name.
	 * @return the removed item or false if not found. */
	public function removeUnlockable($unlockable)
	{
		return $this->unlockables->remove($unlockable);
	}

	/** @return PointStack instance. */
	public function getStack($userId)
	{
		return $this->points->create($this->getStackId(), $userId, $this);
	}

	public function getStackId()
	{
		static $stackNameLenMax = 230;
		return empty($this->pointStackId) ? substr($this->getName(), 0, $stackNameLenMax) : $this->pointStackId;
	}

	public function setStackId($name)
	{
		$this->pointStackId = $name;
		return $this;
	}

	/** generate a stack id through all the pool.
	 * Do nothing if pointStackId already has a value. */
	protected function initUniqueStackId()
	{
		if( empty($this->pointStackId) )
		{
			// get all existant pointStackId
			global $wpdb;
			$stackIds = $wpdb->get_col("SELECT DISTINCT(meta_value) FROM {$wpdb->postmeta} WHERE meta_key='wre_pool_point_stack'");
			// default name is pool name, but we ensure a not empty name
			if( empty($base = trim($this->getStackId())) )
				$base = 'noname';
			// ensure name unicity
			$this->pointStackId = \LWS\WOOREWARDS\Abstracts\Collection::getNewName($base, $stackIds);
		}
		return $this;
	}

	public function setName($name)
	{
		$this->name = \sanitize_key($name);
		if( empty($this->name) )
			$this->name = substr(md5($name), 0, 8);
		if( empty($this->name) )
			$this->name = 'sys';
		return $this;
	}

	public function getPostType()
	{
		return self::POST_TYPE;
	}

	protected function getSimilarPostTypes()
	{
		return array(self::POST_TYPE);
	}

	/** Ensure current name does not exists in database.
	 * Else increment a counter a end of current name. */
	public function ensureNameUnicity()
	{
		global $wpdb;
		$post_id = intval($this->getId());
		$typeCond = ("post_type IN ('" . implode("','", $this->getSimilarPostTypes()) . "')");
		$names = $wpdb->get_col("SELECT DISTINCT(post_name) FROM {$wpdb->posts} WHERE $typeCond AND ID<>{$post_id}");
		$this->setName(\LWS\WOOREWARDS\Abstracts\Collection::getNewName($this->getName(), $names));
	}

	/** Initialise the pool.
	 *	@param $load (bool) try to load events and unlockables too. */
	static public function fromPost(\WP_Post $post, $load=true)
	{
		$pool = new static();
		$pool->name   = $post->post_name;
		$pool->status = $post->post_status;
		$pool->title  = $post->post_title;
		$pool->order  = $post->menu_order;

		if( !empty($post->ID) )
		{
			$pool->id = intval($post->ID);
			$pool->pointStackId        = \get_post_meta($post->ID, 'wre_pool_point_stack', true);
			$pool->deletable           = !boolval(\get_post_meta($post->ID, 'wre_pool_prefab', true));
			$pool->type                = \get_post_meta($post->ID, 'wre_pool_type', true);
			$pool->categoriesWhitelist = \get_post_meta($post->ID, 'wre_pool_whitelist', true);
			$pool->categoriesBlacklist = \get_post_meta($post->ID, 'wre_pool_blacklist', true);
			$options                   = \get_post_meta($post->ID, 'wre_pool_options', true);
			if( !empty($options) )
				$pool->options = $options;

			if( $load )
				$pool->subLoad();
			$pool->_customLoad($post, $load);
		}
		return \apply_filters('lws_woorewards_core_pool_from_post', $pool, $post, $load);
	}

	protected function _customLoad(\WP_Post $post, $load=true)
	{
		return $this;
	}

	/** load events and unlockable. Should be done by fromPost in most cases. */
	public function subLoad()
	{
		$pool = &$this;
		$this->events->load(array('post_parent' => $this->getId()));
		$this->events->apply(function(&$item) use($pool) {$item->setPool($pool);});
		$this->unlockables->load(array('post_parent' => $this->getId()));
		$this->unlockables->apply(function(&$item) use($pool) {$item->setPool($pool);});
	}

	/** @see https://wpml.org/documentation/support/string-package-translation
	 * Known wpml bug: kind first letter must be uppercase */
	function getPackageWPML($full=false)
	{
		$pack = array(
			'kind' => 'WooRewards Loyalty System',//strtoupper(self::POST_TYPE),
			'name' => $this->getId(),
		);
		if( $full )
		{
			$pack['title'] = $this->getOption('title');
			$pack['edit_link'] = \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty', 'tab'=>'wr_loyalty.wr_upool_'.$this->getName()), admin_url('admin.php'));
		}
		return $pack;
	}

	/** Save this pool in database. */
	public function save($withEvents=true, $withUnlockables=true)
	{
		if( !isset($this->id) || empty($this->id) )
			$this->initUniqueStackId();

		$data = array(
			'ID'          => isset($this->id) ? intval($this->id) : 0,
			'post_name'   => $this->name,
			'post_title'  => $this->title,
			'post_status' => $this->status,
			'post_type'   => $this->getPostType(),
			'menu_order'  => isset($this->order) ? $this->order : ($this->isDeletable() ? 1024 : 0),
			'meta_input'  => array(
				'wre_pool_point_stack' => $this->getStackId(),
				'wre_pool_type' => $this->type,
				'wre_pool_whitelist' => $this->categoriesWhitelist,
				'wre_pool_blacklist' => $this->categoriesBlacklist,
				'wre_pool_options'  => $this->options
			)
		);

		$postId = \wp_insert_post($data, true);
		if( \is_wp_error($postId) )
		{
			error_log("Error occured during pool saving: " . $postId->get_error_message());
			\lws_admin_add_notice_once('lws-wre-pool-save', __("Error occured during reward system saving.", LWS_WOOREWARDS_DOMAIN), array('level'=>'error'));
			return $this;
		}
		$this->id = intval($postId);

		\do_action('wpml_register_string', $this->title, 'title', $this->getPackageWPML(true), __("Title", LWS_WOOREWARDS_DOMAIN), 'LINE');

		if( $withEvents )
			$this->events->save($this);
		if( $withUnlockables )
			$this->unlockables->save($this);

		$this->_customSave($withEvents, $withUnlockables);
		\do_action('lws_woorewards_core_pool_saved', $this, $withEvents, $withUnlockables);

		return $this;
	}

	protected function _customSave($withEvents=true, $withUnlockables=true)
	{
		return $this;
	}

	/** @param $user (false|int|WP_User)
	 * @return true if the user can interact with pool. */
	public function userCan($user=false)
	{
		return true;
	}

	public function isDeletable()
	{
		return $this->deletable;
	}

	public function delete($delEvents=true, $delUnlockables=true, $force=false)
	{
		\do_action('lws_woorewards_core_pool_delete_before', $this);
		if( !($this->deletable || $force) )
		{
			error_log("Try to delete a prefab pool: " . $this->getName());
		}
		else if( isset($this->id) && !empty($this->id) )
		{
			$stack = $this->getStack(0);

			if( $delEvents )
				$this->events->apply(function(&$item){$item->delete();});

			if( $delUnlockables )
				$this->unlockables->apply(function(&$item){$item->delete();});

			if( empty(\wp_delete_post($this->id, true)) )
			{
				error_log("Failed to delete the pool {$this->id}");
				\lws_admin_add_notice_once(
					'lws-wre-pool-delete-error',
					sprintf(
						__("Failed to delete the reward system <b>%s</b>/%s.", LWS_WOOREWARDS_DOMAIN),
						$this->title,
						$this->name
					),
					array('level'=>'error')
				);
			}
			else
			{
				// if stack not used anymore, delete it
				if( !$stack->isUsed() )
					$stack->delete();

				$pack = $this->getPackageWPML();
				\do_action('wpml_delete_package_action', $pack['name'], $pack['kind']);

				unset($this->id);
				\lws_admin_add_notice_once(
					'lws-wre-pool-delete',
					sprintf(
						__("The reward system <b>%s</b>/%s successfully deleted.", LWS_WOOREWARDS_DOMAIN),
						$this->title,
						$this->name
					),
					array('level'=>'success')
				);
			}
		}
		return $this;
	}

	/** Register all the Hooks required to run points gain events and unlockables.
	 *	Must be called only once per active pool. */
	public function install()
	{
		if( $this->isActive() )
		{
			$status = \apply_filters('lws_woorewards_order_events', array('processing', 'completed'));
			foreach (array_unique($status) as $s)
				\add_action('woocommerce_order_status_' . $s, array($this, 'triggerOrderDone'), 99, 2); // priority late to let someone change amount and wc to save order

			\do_action('lws_woorewards_core_pool_install', $this);
			$this->events->filter(function($e){return !empty($e->getMultiplier());})->install();
		}
		return $this;
	}

	public function triggerOrderDone($order_id, $order)
	{
		$onceKey = \LWS\WOOREWARDS\Abstracts\Event::formatType(\get_class()) . '-' . $this->getId();
		if( empty(\get_post_meta($order_id, $onceKey, true)) )
		{
			update_post_meta($order_id, $onceKey, \date(DATE_W3C));

			$action = (object)array(
				'order_id' => $order_id,
				'order'    => $order,
				'items'    => array(),
				'inc_tax'  => !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''))
			);

			$action->amount = 0.0; // compute amount since wc is not able to return tax before discount
			foreach( $order->get_items() as $item )
			{
				$itemAmount = floatval($order->get_line_subtotal($item, $action->inc_tax, false));
				$action->amount += $itemAmount;
				$action->items[] = (object)array(
					'item'   => $item,
					'amount' => $itemAmount,
				);
			}

			\apply_filters('lws_woorewards_wc_order_done_'.$this->getName(), $action);
		}
	}

	public function getId()
	{
		return isset($this->id) ? intval($this->id) : false;
	}

	public function getName()
	{
		return $this->name;
	}

	protected function get_meta_datetime($postId, $metaKey, $default=false)
	{
		$value = \get_post_meta($postId, $metaKey, true);
		return empty($value) ? $default : date_create($value);
	}

	public function __construct($name='')
	{
		$this->options = new \stdClass();
		$this->name = $name;
		$this->points = new \LWS\WOOREWARDS\Collections\PointStacks();
		$this->events = new \LWS\WOOREWARDS\Collections\Events();
		$this->unlockables = new \LWS\WOOREWARDS\Collections\Unlockables();
	}

	public function detach($deep=true)
	{
		if( isset($this->id) )
			unset($this->id);
		if( $deep )
		{
			$this->getEvents()->apply(function($item){$item->detach();});
			$this->getUnlockables()->apply(function($item){$item->detach();});
		}
	}

	/** sort two pools. */
	function cmp(&$a, &$b)
	{
		if( !$a->isDeletable() && $a->type == self::T_STANDARD )
			return -1;
		if( !$b->isDeletable() && $b->type == self::T_STANDARD )
			return 1;

		if( !$a->isDeletable() )
			return -1;
		if( !$b->isDeletable() )
			return 1;

		if( !isset($a->cmpData) )
			$a->cmpData = array(
				'label' => $a->getOption('display_title'),
				'enabled' => in_array($a->status, array('publish', 'private'))
			);
		if( !isset($b->cmpData) )
			$b->cmpData = array(
				'label' => $b->getOption('display_title'),
				'enabled' => in_array($b->status, array('publish', 'private'))
			);

		if( $a->cmpData['enabled'] != $b->cmpData['enabled'] )
			return $a->cmpData['enabled'] ? -1 : 1;

		return strcasecmp($a->cmpData['label'], $b->cmpData['label']);
	}
}

?>