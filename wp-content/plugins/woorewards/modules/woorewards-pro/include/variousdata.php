<?php
namespace LWS\WOOREWARDS\PRO;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to get various data from the database */
class VariousData
{

	/* Get Available WooCommerce coupons for the provided user */
	public function getCoupons($userId)
	{
		$user = \get_user_by('ID', $userId);
		if( empty($user->user_email) )
			return array();
		$todayDate = strtotime(date('Y-m-d'));
		global $wpdb;
		$query = <<<EOT
SELECT p.ID, p.post_content, p.post_title, p.post_excerpt, e.meta_value AS expiry_date
FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as m ON p.ID = m.post_id AND m.meta_key='customer_email'
LEFT JOIN {$wpdb->postmeta} as l ON p.ID = l.post_id AND l.meta_key='usage_limit'
LEFT JOIN {$wpdb->postmeta} as u ON p.ID = u.post_id AND u.meta_key='usage_count'
LEFT JOIN {$wpdb->postmeta} as e ON p.ID = e.post_id AND e.meta_key='date_expires'
WHERE m.meta_value=%s AND post_type = 'shop_coupon' AND post_status = 'publish'
AND (e.meta_value is NULL OR e.meta_value = '' OR e.meta_value >= '{$todayDate}')
AND (u.meta_value < l.meta_value OR u.meta_value IS NULL OR l.meta_value IS NULL OR l.meta_value=0)
EOT;
		$result = $wpdb->get_results($wpdb->prepare($query, serialize(array($user->user_email))), OBJECT_K);
		if( empty($result) )
			return $result;
		$ids = implode(",", array_map('intval', array_keys($result)));
		$sub = $wpdb->get_results(<<<EOT
SELECT p.ID, v.meta_value AS coupon_amount, o.meta_value AS product_ids, w.meta_value AS discount_type
FROM {$wpdb->posts} as p
LEFT JOIN {$wpdb->postmeta} as w ON p.ID = w.post_id AND w.meta_key='discount_type'
LEFT JOIN {$wpdb->postmeta} as v ON p.ID = v.post_id AND v.meta_key='coupon_amount'
LEFT JOIN {$wpdb->postmeta} as o ON p.ID = o.post_id AND o.meta_key='product_ids'
WHERE p.ID IN ({$ids})
EOT
		, OBJECT_K);

		foreach( $sub as $id => $info )
		{
			foreach( $info as $k => $v )
			$result[$id]->$k = $v;
		}
		return $result;
	}

	/** Get all the loyalty systems information relative to provided user
		* $unlockables = list of unlockable rewards
		* $poolsData = list of active pool informations
	*/
	public function getLoyaltySystemsInfo($userId)
	{
		$unlockables = array();
		$poolsData = array();
		$pools = \LWS_WooRewards_Pro::getBuyablePools()->sort();

		$unlockables = $this->getUnlockables($userId,'avail');
		foreach( $pools->asArray() as $pool )
		{
			if($pool->userCan($userId))
				$poolsData[] = $this->getPoolData($pool, $userId);
		}
		return array($unlockables, $poolsData);
	}

	/** Get a list of unlockables
	 * $auth = all | Provide all unlockables
	 * $auth = avail | Provide only available unlockables */
	public function getUnlockables($userId, $auth= 'all')
	{
		$unlockables = array();
		$pools = \LWS_WooRewards_Pro::getBuyablePools()->sort();
		foreach( $pools->asArray() as $pool )
		{
			if($pool->userCan($userId))
			{
				$type = $pool->getOption('type');
				if($type != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
				{
					$unlockables = array_merge($unlockables, $this->getPoolUnlockables($pool, $userId, 'avail'));
				}
			}
		}
		return $unlockables;
	}

	/** Get the unlockable rewards for the provided user
	 * $auth = all | Provide all unlockables for the pool
	 * $auth = avail | Provide only available rewards */
	public function getPoolUnlockables($pool, $userId, $auth)
	{
		$unlockables = array();
		$user = \get_user_by('ID', $userId);
		$userPoints = $pool->getPoints($userId);
		foreach( $pool->getUnlockables()->asArray() as $item )
		{
			if($auth==='all' || ($auth==='avail' && $item->isPurchasable($userPoints, $userId)))
			{
				$pointName = apply_filters('lws_woorewards_point_symbol_translation', false, 2, $pool->getName());
				$pointName = ($pointName) ? $pointName : __('Points', LWS_WOOREWARDS_PRO_DOMAIN);
				$unlockable = array(
					'thumbnail'		=> $item->getThumbnailImage(),
					'title'			=> $item->getTitle(),
					'description'	=> $item->getCustomDescription(),
					'poolname'		=> $item->getPoolName(),
					'pooltitle'		=> $pool->getOption('display_title'),
					'cost'			=> $item->getCost('front'),
					'userpoints'	=> $userPoints,
					'unlocklink'	=> esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs($this->getUrlTarget(), $item, $user)),
					'pointname'   => $pointName
				);
				$unlockables[] = $unlockable;
			}
		}

		return $unlockables;

	}

	/* Get the pool data for the provided user */
	public function getPoolData($pool, $userId)
	{
		$poolData = array(
			'userData' => array(),
			'poolInfo' => array()
		);
		$user = \get_user_by('ID', $userId);

		/* Actual points for the current user */
		$poolData['userData']['currentPoints'] = $pool->getPoints($userId);
		$poolData['userData']['symbolPoints'] = \LWS_WooRewards::formatPointsWithSymbol($poolData['userData']['currentPoints'], $pool ->getName());

		/* User History */
		$stack = $pool->getStack($userId);
		$poolData['userData']['history'] = array_slice($stack->getHistory(),0,10);

		/* Pool General Information */
		$poolData['poolInfo']['type'] = $pool->getOption('type');
		$poolData['poolInfo']['title'] = $pool->getOption('display_title');
		$poolData['poolInfo']['start'] = $pool->getOption('period_start','');
		$poolData['poolInfo']['end'] = $pool->getOption('period_end','');
		$poolData['poolInfo']['singular'] = apply_filters('lws_woorewards_point_symbol_translation', false, 1 , $pool->getName());
		$poolData['poolInfo']['singular'] = $poolData['poolInfo']['singular'] ? $poolData['poolInfo']['singular'] : __('Point', LWS_WOOREWARDS_PRO_DOMAIN);
		$poolData['poolInfo']['plural'] = apply_filters('lws_woorewards_point_symbol_translation', false, 2 , $pool->getName());;
		$poolData['poolInfo']['plural'] = $poolData['poolInfo']['plural'] ? $poolData['poolInfo']['plural'] : __('Points', LWS_WOOREWARDS_PRO_DOMAIN);

		/* Ways to earn points */
		$poolData['poolInfo']['events'] = array();
		foreach( $pool->getEvents()->asArray() as $item )
		{
			$eventInfo = array();
			$eventInfo['desc'] = $item->getTitle(false);
			if( !$eventInfo['desc'] )
				$eventInfo['desc'] = $item->getDescription('frontend');
			$eventInfo['earned'] = $item->getMultiplier('view');
			$poolData['poolInfo']['events'][] = $eventInfo;
		}

		/* If levelling system, get current level */
		$done = \get_user_meta($userId, 'lws-loyalty-done-steps', false);
		if($poolData['poolInfo']['type'] == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
		{
			$currentLevel = 0;
			$cost = -9999;
			$level = array();
			$poolData['userData']['currentLevel'] = $currentLevel;
			$poolData['userData']['currentLevelName'] = _x("-", "User current level: No level unlocked yet", LWS_WOOREWARDS_PRO_DOMAIN);
			$poolData['poolInfo']['levels'] = array();
			foreach( $pool->getUnlockables()->sort()->asArray() as $item )
			{
				if( $item->getCost() != $cost )
				{
					$currentLevel += 1;
					if(!empty($level))
						$poolData['poolInfo']['levels'][] = $level;
					$level = array();
					$cost = $item->getCost();
					$level['cost'] = $cost;
					$level['title'] = $item->getGroupedTitle('view');
					$level['points'] = \LWS_WooRewards::formatPointsWithSymbol($item->getCost('front'), $item->getPoolName());
					$level['passed'] = false;
					$level['rewards'] = array();
					if($poolData['userData']['currentPoints']>=$level['cost'])
					{
						$level['passed'] = true;
						$poolData['userData']['currentLevel'] = $currentLevel;
						$poolData['userData']['currentLevelName'] = $level['title'];
					}
				}
				$level['rewards'][] = array( // reward
					'img'   => $item->getThumbnailImage(),
					'title' => $item->getTitle(),
					'desc'  => $item->getCustomDescription(),
					'owned' => (in_array($item->getId(), $done) || $item->noMorePurchase($userId))
				);
			}
			$poolData['poolInfo']['levels'][] = $level;
		}
		else
		{
			$poolData['poolInfo']['rewards'] = array();
			foreach( $pool->getUnlockables()->sort()->asArray() as $item )
			{
				$poolData['poolInfo']['rewards'][] = array(
					'img'   => $item->getThumbnailImage(),
					'title' => $item->getTitle(),
					'cost'  => $item->getCost(),
					'desc'  => $item->getCustomDescription(),
					'owned' => $item->noMorePurchase($userId),
				);
			}
		}
		return $poolData;
	}


	protected function getUrlTarget($demo=false)
	{
		if( $demo )
		{
			return '#';
		}
		else
		{
			if( !isset($this->urlTarget) )
			{
				if( !empty($page = get_option('lws_woorewards_reward_claim_page', '')) )
					$this->urlTarget = \get_permalink($page);
				else if( \LWS_WooRewards::isWC() && !empty(\get_option('lws_woorewards_wc_my_account_endpont_loyalty', 'on')) )
					$this->urlTarget = \wc_get_endpoint_url('lws_woorewards', '', \wc_get_page_permalink('myaccount'));
				else
					$this->urlTarget = \home_url();
			}
			return $this->urlTarget;
		}
	}

}

?>