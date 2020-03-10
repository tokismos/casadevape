<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Achievement is a specific Pool. */
class Achievement extends \LWS\WOOREWARDS\PRO\Core\Pool
{
	const A_POST_TYPE = 'lws-wre-achievement';

	public function getPostType()
	{
		return self::A_POST_TYPE;
	}

	protected function getSimilarPostTypes()
	{
		return array(self::POST_TYPE, self::A_POST_TYPE);
	}

	/** @return false|\LWS\WOOREWARDS\PRO\Unlockables\Badge */
	public function createTheReward()
	{
		return $this->getUnlockables()->create('lws_woorewards_pro_unlockables_badge')->last();
	}

	/** @return false|\LWS\WOOREWARDS\PRO\Unlockables\Badge */
	public function getTheReward()
	{
		return $this->getUnlockables()->filter(function($item){
			return is_a($item, '\LWS\WOOREWARDS\PRO\Unlockables\Badge');
		})->last();
	}

	/** @see https://wpml.org/documentation/support/string-package-translation
	 * Known wpml bug: kind first letter must be uppercase */
	function getPackageWPML($full=false)
	{
		$pack = array(
			'kind' => 'WooRewards Achievement',//strtoupper(self::POST_TYPE),
			'name' => $this->getId(),
		);
		if( $full )
		{
			$pack['title'] = $this->getOption('title');
			$pack['edit_link'] = \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.achievement'), admin_url('admin.php'));
		}
		return $pack;
	}

	/** override since achievements are not pool, so not 'buyable' */
	protected function getSharablePools()
	{
		return \LWS_WooRewards_Pro::getLoadedAchievements();
	}

	/** apply the unlockable and keep a trace.
	 * @return (bool) if really unlocked */
	protected function _applyUnlock($user, &$unlockable)
	{
		if( $unlockable->apply($user, 'wr_achieved') )
		{
			\do_action('lws_woorewards_core_pool_single_unlocking', $unlockable, $user, $this);
			$userId = is_numeric($user) ? $user : $user->ID;
			if( !empty($userId) && !empty($unlockable->getId()) && !in_array($unlockable->getId(), \get_user_meta($userId, 'lws_wre_unlocked_id', false)) )
			{
				\add_user_meta($userId, 'lws_wre_unlocked_id', $unlockable->getId(), false);
			}
			$this->saveUserUnlockState($user, true);
			return true;
		}
		return false;
	}

	public function getBadge()
	{
		$badge = $this->getTheReward();
		return $badge ? $badge->getBadge() : false;
	}

	public function getBadgeId()
	{
		$badge = $this->getTheReward();
		return $badge ? $badge->getBadgeId() : false;
	}

	/** use product image by default if any but can be override by user */
	public function getThumbnailUrl()
	{
		$badge = $this->getTheReward();
		return $badge ? $badge->getThumbnailUrl() : false;
	}

	/** use product image by default if any but can be override by user */
	public function getThumbnailImage($size='lws_wr_thumbnail')
	{
		$badge = $this->getTheReward();
		return $badge ? $badge->getThumbnailImage($size) : false;
	}

	/** @return float [0..1] or false if no user given nor log off
	 * @param $user (false|int|WP_User) user ID, user instance or false (we look for current user) */
	public function getProgress($user=false)
	{
		$userId = false;
		if( $user === false )
			$userId = \get_current_user_id();
		else if( \is_a($user, '\WP_User') )
			$userId = $user->ID;
		else if( is_numeric($user) )
			$userId = intval($user);

		if($userId)
		{
			$pts = $this->getPoints($userId);
			$reward = $this->getTheReward();
			if( $reward )
			{
				$cost = $reward->getCost();
				if( $cost && $cost > 0.0 )
					return (floatval($pts) / floatval($cost));
			}
		}
		return false;
	}

}
