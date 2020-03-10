<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage badge item like a post. */
class Pool extends \LWS\WOOREWARDS\Core\Pool
{
	protected $dateBegin           = false;     /// event starting period date (included) @see DateTime
	protected $allowDates          = false;     /// allows date edition, set to false will reset any date.
	protected $dateMid             = false;     /// event earning points period end (included) @see DateTime
	protected $dateEnd             = false;     /// event last day of period (included) @see DateTime
	protected $pointLifetime       = false;     /// delay before set user point to zero @see Conveniences\Duration
	protected $clampLevel          = false;     /// earning points are clamped at each level, so only one can be passed at a time
	private $waiters = array(); /// @see tryUnlock

	public function getData()
	{
		return array(
			'id' => $this->getName(),
			'name' => $this->getOption('display_title'),
			'points' => $this->getStackId(),
			'active' => $this->isActive() ? 'on' : 'off',
		);
	}

	public function __construct($name='')
	{
		parent::__construct($name);
		$this->pointLifetime = \LWS\WOOREWARDS\Conveniencies\Duration::void();
	}

	/** Register all the Hooks required to run points gain events and unlockables.
	 *	Must be called only once per active pool. */
	public function install()
	{
		parent::install();
		if( $this->isActive() )
		{
			\add_action('lws_woorewards_daily_event', array($this, 'checkPointsTimeout'));
		}
		return $this;
	}

	/** Some configuration sets are relevant as specific pool kind.
	 *	@return array of option */
	public function getDefaultConfiguration($type)
	{
		$config = array(
			'whitelist' => array($type)
		);
		return \apply_filters('lws_woorewards_core_pool_default_configuration', $config, $type);
	}

	/** reset point if timeout */
	public function checkPointsTimeout()
	{
		if( !$this->pointLifetime->isNull() )
		{
			$confiscate = $this->getOption('type') == self::T_LEVELLING && $this->getOption('confiscation');
			$users = $this->getStack(0)->timeout(\date_create()->sub($this->pointLifetime->toInterval()), $confiscate);

			if( $confiscate && !empty($users) )
			{
				$c = new \LWS\WOOREWARDS\PRO\Core\Confiscator();
				$c->setByPool($this);
				$c->revoke($users);
			}
		}
	}

	/** In pro version, an active pool is a buyable one but could be limited by an additionnal date.
	 * After that date, the pool stil lives but not points can be earned anymore. */
	public function isActive()
	{
		if( !isset($this->_isActive) )
		{
			if( !$this->isBuyable() )
				return ($this->_isActive = false);

			if( $this->getOption('type') != self::T_LEVELLING )
			{
				if( !empty($this->dateMid)   && \date_create()->setTime(0,0,0) > $this->dateMid ) // dateMid is include, so take care now is computed without time
					return ($this->_isActive = false);
			}

			$this->_isActive = true;
		}
		return $this->_isActive;;
	}

	/** A buyable pool is enabled but limited by two extrem dates.
	 * If period is defined, today is included in. */
	public function isBuyable()
	{
		if( !parent::isActive() )
			return false;

		// if( !\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) && !$this->userCan() )
		//	return false; // bad idea since some plugin (especially for shipping) could change order status via ajax and so on

		if( !empty($this->dateBegin) && \date_create() < $this->dateBegin )
			return false;
		if( !empty($this->dateEnd)   && \date_create()->setTime(0,0,0) > $this->dateEnd ) // dateEnd is include, so take care now is computed without time
			return false;

		return true;
	}

	/** override to check user role. */
	public function userCan($user=false)
	{
		if( !empty($roles = $this->getOption('roles')) )
		{
			if( !$user )
				$user = \wp_get_current_user();
			else if( !is_a($user, 'WP_User') && is_numeric($user) )
				$user = \get_user_by('ID', $user);

			if( !$user || !$user->ID )
				return false;

			if( empty(array_intersect($user->roles, $roles)) )
				return false;
		}
		return true;
	}

	protected function _customLoad(\WP_Post $post, $load=true)
	{
		$this->allowDates    = boolval(\get_post_meta($post->ID, 'wre_pool_happening', true));
		$this->dateBegin     = $this->get_meta_datetime($post->ID, 'wre_pool_date_begin');
		$this->dateMid       = $this->get_meta_datetime($post->ID, 'wre_pool_date_mid');
		$this->dateEnd       = $this->get_meta_datetime($post->ID, 'wre_pool_date_end');
		$this->pointLifetime = \LWS\WOOREWARDS\Conveniencies\Duration::postMeta($post->ID, 'wre_pool_point_deadline');
		$this->forceChoice   = boolval(\get_post_meta($post->ID, 'wre_pool_force_choice', true));
		$this->clampLevel    = boolval(\get_post_meta($post->ID, 'wre_pool_clamp_level', true));
		$this->confiscation  = boolval(\get_post_meta($post->ID, 'wre_pool_rewards_confiscation', true));
		$this->roles         = \get_post_meta($post->ID, 'wre_pool_roles', true);
		if( !is_array($this->roles) )
			$this->roles = empty($this->roles) ? array() : array($this->roles);
		$this->symbol        = intval(\get_post_meta($post->ID, 'wre_pool_symbol', true));
		$this->pointName     = \get_post_meta($post->ID, 'wre_pool_point_name', true);
		$this->pointFormat   = \get_post_meta($post->ID, 'wre_pool_point_format', true);

		return parent::_customLoad($post, $load);
	}

	protected function _customSave($withEvents=true, $withUnlockables=true)
	{
		if( !$this->isDeletable() || !$this->allowDates )
		{
			$this->allowDates = false;
			$this->dateBegin = false;
			$this->dateMid   = false;
			$this->dateEnd   = false;
		}
		if( $this->getOption('type') == self::T_LEVELLING )
			$this->dateMid   = false;
		else
			$this->clampLevel = false;

		\update_post_meta($this->id, 'wre_pool_happening', $this->allowDates ? 'on' : '');
		\update_post_meta($this->id, 'wre_pool_date_begin', empty($this->dateBegin) ? '' : $this->dateBegin->format('Y-m-d'));
		\update_post_meta($this->id, 'wre_pool_date_mid',   empty($this->dateMid)   ? '' : $this->dateMid->format('Y-m-d'));
		\update_post_meta($this->id, 'wre_pool_date_end',   empty($this->dateEnd)   ? '' : $this->dateEnd->format('Y-m-d'));
		\update_post_meta($this->id, 'wre_pool_force_choice', (isset($this->forceChoice) && $this->forceChoice) ? 'on' : '');
		\update_post_meta($this->id, 'wre_pool_clamp_level', $this->clampLevel ? 'on' : '');
		\update_post_meta($this->id, 'wre_pool_rewards_confiscation', (isset($this->confiscation) && $this->confiscation) ? 'on' : '');
		\update_post_meta($this->id, 'wre_pool_roles', isset($this->roles) ? $this->roles : array());
		\update_post_meta($this->id, 'wre_pool_symbol', isset($this->symbol) ? $this->symbol : '');
		\update_post_meta($this->id, 'wre_pool_point_format', isset($this->pointFormat) ? $this->pointFormat : '');

		$pn = (isset($this->pointName) && $this->pointName) ? $this->pointName : array('singular'=>'', 'plural'=>'');
		\update_post_meta($this->id, 'wre_pool_point_name', $pn);

		$wpml = $this->getPackageWPML(true);
		\do_action('wpml_register_string', $pn['singular'], 'point_name_singular', $wpml, __("Point display name", LWS_WOOREWARDS_PRO_DOMAIN), 'LINE');
		\do_action('wpml_register_string', $pn['plural'], 'point_name_plural', $wpml, __("Point display name (plural)", LWS_WOOREWARDS_PRO_DOMAIN), 'LINE');

		if( $this->pointLifetime->isNull() )
			$this->pointLifetime->deletePostMeta($this->id, 'wre_pool_point_deadline');
		else
			$this->pointLifetime->updatePostMeta($this->id, 'wre_pool_point_deadline');

		return parent::_customSave($withEvents, $withUnlockables);
	}

	/** @param (string) option name
	 * @param $default return that value if option does not exists.
	 *
	 * Options are:
	 * * happening     : (bool) allow period edition.
	 * * period_start  : (false|DateTime) If not false, pool activation is restricted in time. Date is included in active period. @see DateTime
	 * * period_mid    : (false|DateTime) If not false, pool point earning is restricted in time. Date is included in active period. @see DateTime
	 * * period_end    : (false|DateTime) If not false, pool activation is restricted in time. Date is included in active period. @see DateTime
	 * * point_add_only: (bool) point are not substracted by unlock cost.
	 * * unlock_once   : (bool) each unlockable can only be done once.
	 * * point_timeout : (Convienencies\Duration instance) delay since last point gain until point reset to zero (Duration::isNull() means no reset). @see \LWS\WOOREWARDS\Conveniences\Duration
	 * * confiscation  : (bool) to use with point_timeout and levelling behavior, remove rewards with points expiry.
	 * * force_choice  : (bool) Force customer choice, never unlock reward automatically, even if single.
	 * * clamp_level   : (bool) Earning points are clamped at each level, so only one can be passed at a time (false if type is not levelling). Only affect the addPoints() method. setPoints() will still pass all available levels.
	 * * roles         : (array of string) user roles restriction
	 * * symbol        : (int) media id used as point symbol.
	 * * symbol_image  : (string) <img> html block
	 * * point_name_singular : (string) point label
	 * * point_name_plural   : (string) point label
	 * * point_format  : (string) sprintf template, expect %1$s for points and %2$s for symbol/label
	 **/
	function _getCustomOption($option, $default)
	{
		$wpml = false;
		$value = $default;
		switch($option)
		{
			case 'happening':
				$value = $this->allowDates;
				break;
			case 'period_start':
				$value = $this->dateBegin;
				break;
			case 'period_mid':
				$value = $this->dateMid;
				break;
			case 'period_end':
				$value = $this->dateEnd;
				break;
			case 'point_timeout':
				$value = $this->pointLifetime; // \LWS\WOOREWARDS\Conveniences\Duration instance
				break;
			case 'force_choice':
				$value = isset($this->forceChoice) ? $this->forceChoice : false;
				break;
			case 'clamp_level':
				$value = $this->clampLevel && (self::T_LEVELLING == $this->type);
				break;
			case 'confiscation':
				$value = isset($this->confiscation) ? $this->confiscation : false;
				break;
			case 'roles':
				$value = isset($this->roles) ? $this->roles : array();
				break;
			case 'symbol':
				$value = isset($this->symbol) ? intval($this->symbol) : false;
				break;
			case 'symbol_image':
				$imgId = isset($this->symbol) ? intval($this->symbol) : false;
				if( $imgId )
				{
					$img = \wp_get_attachment_image(\apply_filters('wpml_object_id', $imgId, 'attachment', true), 'small', false, array('class'=>'lws-woorewards-point-symbol'));
					if( $img )
						$value = $img;
				}
				break;
			case 'disp_point_name_singular':
				$wpml = $this->getPackageWPML();
			case 'point_name_singular':
				if( isset($this->pointName) && $this->pointName )
				{
					if( is_array($this->pointName) )
					{
						$name = (isset($this->pointName['singular']) ? $this->pointName['singular'] : '');
						if( !$name )
							$name = reset($this->pointName);
					}
					else
						$name = $this->pointName;
					if( $name )
						$value = !$wpml ? $name : \apply_filters('wpml_translate_string', $name, 'point_name_singular', $wpml);
				}
				break;
			case 'disp_point_name_plural':
				$wpml = $this->getPackageWPML();
			case 'point_name_plural':
				if( isset($this->pointName) && $this->pointName && is_array($this->pointName) )
				{
					$name = (isset($this->pointName['plural']) ? $this->pointName['plural'] : '');
					if( $name )
						$value = !$wpml ? $name : \apply_filters('wpml_translate_string', $name, 'point_name_plural', $wpml);
				}
				break;
			case 'point_format':
				$value = (isset($this->pointFormat) && $this->pointFormat) ? $this->pointFormat : '%1$s %2$s';
				break;
		}
		return $value;
	}

	/** @param (string) option name.
	 * For option list @see getOption()
	 *
	 * Options are:
	 * * happening     : (bool) allow period edition.
	 * * period_start  : (false|DateTime) If not false, pool activation is restricted in time. Date is included in active period. @see DateTime
	 * * period_mid    : (false|DateTime) If not false, pool point earning is restricted in time. Date is included in active period. @see DateTime
	 * * period_end    : (false|DateTime) If not false, pool activation is restricted in time. Date is included in active period. @see DateTime
	 * * point_add_only: (bool) point are not substracted by unlock cost.
	 * * unlock_once   : (bool) each unlockable can only be done once.
	 * * point_timeout : (false|string|DateInterval|Convienencies\Duration) delay since last point gain until point reset to zero. false, void() or empty means no reset. @see DateInterval, @see \LWS\WOOREWARDS\Conveniences\Duration
	 * * confiscation  : (bool) to use with point_timeout and levelling behavior, remove rewards with points expiry.
	 * * force_choice  : (bool) Force customer choice, never unlock reward automatically, even if single.
	 * * clamp_level   : (bool) Earning points are clamped at each level, so only one can be passed at a time. Only affect the addPoints() method. setPoints() will still pass all available levels.
	 * * roles         : (string|array) user roles restriction
	 * * symbol        : (int) media id used as point symbol.
	 * * point_name_singular : (string) point label
	 * * point_name_plural   : (string) point label
	 * * point_format  : (string) sprintf template, expect %1$s for points and %2$s for symbol/label
	 **/
	protected function _setCustomOption($option, $value)
	{
		switch($option)
		{
			case 'happening':
				$this->allowDates = boolval($value);
				if( !$this->allowDates )
				{
					$this->dateBegin = false;
					$this->dateMid = false;
					$this->dateEnd = false;
				}
				break;
			case 'period_start':
				if( \is_a($value, 'DateTime') )
					$this->dateBegin = $value;
				else if( !empty($value) && \is_string($value) )
				{
					$d = \date_create($value);
					$this->dateBegin = empty($d) ? false : $d->setTime(0,0,0);
				}
				else
					$this->dateBegin = false;
				if( $this->dateBegin )
					$this->allowDates = true;
				break;
			case 'period_mid':
				if( \is_a($value, 'DateTime') )
					$this->dateMid = $value;
				else if( !empty($value) && \is_string($value) )
				{
					$d = \date_create($value);
					$this->dateMid = empty($d) ? false : $d->setTime(0,0,0);
				}
				else
					$this->dateMid = false;
				if( $this->dateMid )
					$this->allowDates = true;
				break;
			case 'period_end':
				if( \is_a($value, 'DateTime') )
					$this->dateEnd = $value;
				else if( !empty($value) && \is_string($value) )
				{
					$d = \date_create($value);
					$this->dateEnd = empty($d) ? false : $d->setTime(0,0,0);
				}
				else
					$this->dateEnd = false;
				if( $this->dateEnd )
					$this->allowDates = true;
				break;
			case 'point_timeout':
				if( empty($value) )
					$this->pointLifetime = \LWS\WOOREWARDS\Conveniencies\Duration::void();
				else if( is_a($value, 'LWS\WOOREWARDS\Conveniencies\Duration') )
					$this->pointLifetime = $value;
				else if( is_a($value, 'DateInterval') )
					$this->pointLifetime = \LWS\WOOREWARDS\Conveniencies\Duration::fromInterval($value);
				else if( is_string($value) )
					$this->pointLifetime = \LWS\WOOREWARDS\Conveniencies\Duration::fromString($value);
				else if( is_numeric($value) )
					$this->pointLifetime = \LWS\WOOREWARDS\Conveniencies\Duration::days($value);
				else
					$this->pointLifetime = \LWS\WOOREWARDS\Conveniencies\Duration::void();
				break;
			case 'force_choice':
				$this->forceChoice = boolval($value);
				break;
			case 'clamp_level':
				$this->clampLevel = boolval($value);
				break;
			case 'confiscation':
				$this->confiscation = boolval($value);
				break;
			case 'roles':
				$this->roles = (is_array($value) ? $value : (empty($value) ? array() : array($value)));
				break;
			case 'symbol':
				$this->symbol = intval($value);
				break;
			case 'point_name_singular':
				if( !isset($this->pointName) || !is_array($this->pointName) )
					$this->pointName = array('singular' => $value, 'plural' => '');
				else
					$this->pointName = array_merge($this->pointName, array('singular' => $value));
				break;
			case 'point_name_plural':
				if( !isset($this->pointName) || !is_array($this->pointName) )
					$this->pointName = array('singular' => '', 'plural' => $value);
				else
					$this->pointName = array_merge($this->pointName, array('plural' => $value));
				break;
			case 'point_format':
				$this->pointFormat = trim($value);
				break;
			default:
				return false;
		}
		return true;
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
			error_log("Unlock reward attempt for unknown user ($userId). Pool ".$this->getId());
			return $uCount;
		}
		if( !$this->userCan($user) )
		{
			error_log("Unlock reward attempt for user ($userId) without permission. Pool ".$this->getId());
			return $uCount;
		}
		$type = $this->getOption('type');
		if( $type == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING ) // standard will test buyable later
		{
			if( !$this->isBuyable() )
			{
				error_log("Unlock reward attempt for user ($userId) but pool is disabled or period is passed. Pool ".$this->getId());
				return $uCount;
			}
		}

		/// used in case an unlockabable->apply triggers an Event,
		/// taht Event gives points and then call tryUnlock again
		/// since current call is not finished.
		if( empty($this->waiters) )
		{
			$this->waiters[] = $user;
			while( !empty($this->waiters) )
			{
				$user = reset($this->waiters);

				if( $type != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING )
					$uCount += $this->tryUnlockStandard($user);
				else
					$uCount += $this->tryUnlockLevelling($user);

				array_shift($this->waiters);
			}
		}
		else
		{
			$this->waiters[] = $user;
		}
		return $uCount;
	}

	protected function tryUnlockStandard($user)
	{
		$uCount = 0;
		$tryUnlock = true;
		while( $tryUnlock )
		{
			$tryUnlock = false;
			$points = $this->getPoints($user->ID);
			$availables = $this->_getGrantedUnlockables($points, $user->ID);

			if( $availables->count() > 0 )
			{
				$c = $this->getSharedUnlockableCount();
				if( $c == 1 && !$this->getOption('force_choice', false) )
				{
					// immediate unlock
					$unlockable = $availables->last();
					if( $this->_applyUnlock($user, $unlockable) )
					{
						$tryUnlock = $this->_payAndContinue($user->ID, $unlockable);
						$uCount++;
					}
				}
				else if( $c > 0 )
				{
					// send mail
					$mailTemplate = 'wr_available_unlockables';
					if( !empty(\get_option('lws_woorewards_enabled_mail_'.$mailTemplate, 'on')) && $this->isUserUnlockStateChanged($user, true) )
					{
						\LWS_WooRewards_Pro::delayedMail($this->getStackId(), $user->user_email, $mailTemplate, array(
								'user'        => $user,
								'points'      => $points,
								'pool'        => $this,
								'unlockables' => $availables
							)
						);
					}
				}
			}
		}
		return $uCount;
	}

	protected function tryUnlockLevelling($user)
	{
		$uCount = 0;
		$points = $this->getPoints($user->ID);
		$done = \get_user_meta($user->ID, 'lws-loyalty-done-steps', false);

		foreach( $this->_getGrantedUnlockables($points, $user->ID)->asArray() as $unlockable )
		{
			// if user not already got it
			if( !in_array($unlockable->getId(), $done) )
			{
				if( $this->_applyUnlock($user, $unlockable) )
				{
					$uCount++;
					// trace
					$this->setPoints($user->ID, $this->getPoints($user->ID), $unlockable->getReason());
					\add_user_meta($user->ID, 'lws-loyalty-done-steps', $unlockable->getId(), false);
				}
			}
		}
		return $uCount;
	}

	protected function getSharedUnlockableCount()
	{
		$stack = $this->getStackId();
		$count = 0;
		foreach( $this->getSharablePools()->asArray() as $pool )
		{
			if( $stack == $pool->getStackId() )
			{
				$count += $pool->unlockables->count();
			}
		}
		return $count;
	}

	/** Override to get the unlockable of all pool sharing the same point stack. */
	protected function _getGrantedUnlockables($points, $userId=null)
	{
		$availables = \LWS\WOOREWARDS\Collections\Unlockables::instanciate();
		$stack = $this->getStackId();

		foreach( $this->getSharablePools()->asArray() as $pool )
		{
			if( $stack == $pool->getStackId() )
			{
				foreach( $pool->unlockables->asArray() as $unlockable )
				{
					if( $unlockable->isPurchasable($points, $userId) )
					{
						$availables->add($unlockable, $unlockable->getId());
					}
				}
			}
		}

		return $availables->sort();
	}

	/** @return a collection of pool that can share the same point stack. */
	protected function getSharablePools()
	{
		return \LWS_WooRewards_Pro::getBuyablePools();
	}

	/** do not pay on levelling mode */
	protected function _payAndContinue($userId, &$unlockable)
	{
		if( $this->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING )
			return parent::_payAndContinue($userId, $unlockable);
		else
			return false;
	}

	/** Try to apply a specific Unlockable.
	 *	User HAVE to have enought point.
	 * @return (bool) if something is unlocked. */
	public function unlock($user, $unlockable)
	{
		if( empty($user) )
		{
			error_log("Unlock reward attempt for unknown user. Pool ".$this->getId());
			return false;
		}
		if( empty($unlockable) )
		{
			error_log("Undefined Unlock reward attempt for user:".$user->ID." / Pool ".$this->getId());
			return false;
		}
		if( false === $this->unlockables->find($unlockable) )
		{
			error_log("Unlock reward attempt for user(".$user->ID.") for unlockable(".$unlockable->getId().") that do not belong to the pool:".$this->getId());
			return false;
		}
		if( !$this->isBuyable() )
			return false;

		$points = $this->getPoints($user->ID);
		if( $unlockable->isPurchasable($points, $user->ID) )
		{
			if( $this->_applyUnlock($user, $unlockable) )
			{
				$this->_payAndContinue($user->ID, $unlockable);
				return true;
			}
		}
		return false;
	}

	/**	Override: could udate value to clamp point total on next level.
	 *	Add points to the pool point stack of a user.
	 *	@param $user (int) the user earning points.
	 *	@param $value (int) final number of point earned.
	 *	@param $reason (string) optional, the cause of the earning.
	 *	@param $origin (Event) optional, the source Event. */
	public function addPoints($userId, $value, $reason='', \LWS\WOOREWARDS\Abstracts\Event $origin=null)
	{
		if( $this->getOption('clamp_level') )
		{
			$current = $this->getPoints($userId);
			$points = $current + $value;
			$done = \get_user_meta($userId, 'lws-loyalty-done-steps', false);

			foreach( $this->_getGrantedUnlockables($points, $userId)->asArray() as $unlockable )
			{
				if( !in_array($unlockable->getId(), $done) )
				{
					$old = $value;
					// it is the first we could unlock, set $value to the exact required amount.
					$value = $unlockable->getCost() - $current;

					if( $old != $value )
					{
						// mark it to be understandable by users
						if( !empty($reason) )
							$reason .= ' ';
						$reason .= sprintf(__("(%+d reduced to level)", LWS_WOOREWARDS_PRO_DOMAIN), $old);
					}
					break;
				}
			}
		}
		return parent::addPoints($userId, $value, $reason, $origin);
	}

	protected function _applyUnlock($user, &$unlockable)
	{
		$done = parent::_applyUnlock($user, $unlockable);
		if( $done )
			$this->saveUserUnlockState($user, true);
		return $done;
	}

	function saveUserUnlockState($user, $reset=false)
	{
		$userId = is_numeric($user) ? $user : $user->ID;
		if( $reset )
			\update_user_meta($userId, 'lws_woorewards_unlock_state_hash_'.$this->getId(), '');
		else
			\update_user_meta($userId, 'lws_woorewards_unlock_state_hash_'.$this->getId(), $this->getUserUnlockState($user));
		return $this;
	}

	/** @param $saveOnChanged (bool) if changed, the state is updated but return as changed anyway. */
	function isUserUnlockStateChanged($user, $saveOnChanged=false)
	{
		$userId = is_numeric($user) ? $user : $user->ID;
		$old = \get_user_meta($userId, 'lws_woorewards_unlock_state_hash_'.$this->getId(), true);
		$new = $this->getUserUnlockState($user);
		if( $old != $new )
		{
			if( $saveOnChanged )
				\update_user_meta($userId, 'lws_woorewards_unlock_state_hash_'.$this->getId(), $new);
			return true;
		}
		return false;
	}

	protected function getUserUnlockState($user)
	{
		$points = $this->getPoints($user->ID);
		$availables = $this->_getGrantedUnlockables($points, $user->ID);
		$options = defined('JSON_PARTIAL_OUTPUT_ON_ERROR') ? JSON_PARTIAL_OUTPUT_ON_ERROR : 0;
		$json = json_encode($availables->asArray(), $options, 3);
		$hash = md5($json);
		return $hash;
	}
}

?>
