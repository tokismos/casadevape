<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Expect user follow a unlock link to generate a reward.
 * This class is able to generate url argument to create a reward redeem lin.
 * and answer that kind of link.
 * Then, found back unlockable generate the reward, then provide a simple feedback to user.
 * Should produce redirection. */
class RewardClaim
{
	/** add arguments to url to redeem an unlockable and generate the reward.
	 * The Unlockable must belong to a pool. */
	static public function addUrlUnlockArgs($url, $unlockable, $user)
	{
		if( empty($pool = $unlockable->getPool()) || empty($pool->getId()) )
			return $url;

		static $lastUserKey = '';
		static $lastUser = null;
		if( $lastUser != $user || empty($lastUserKey) )
		{
			$lastUser = $user;
			$lastUserKey = \get_user_meta($user->ID, 'lws_woorewards_user_key', true);
			if( empty($lastUserKey) )
			{
				\update_user_meta($user->ID, 'lws_woorewards_user_key', $lastUserKey = \sanitize_key(\wp_hash(implode('*', array(
					$user->ID,
					$user->user_email,
					rand()
				)))));
			}
		}

		static $lastPoolKey = '';
		static $lastPool = null;
		if( empty($lastPool) || $lastPool->getId() != $pool->getId() || empty($lastPoolKey) )
		{
			$lastPool = &$pool;
			$lastPoolKey = \get_post_meta($lastPool->getId(), 'lws_woorewards_pool_rkey', true);
			if( empty($lastPoolKey) )
			{
				\update_post_meta($lastPool->getId(), 'lws_woorewards_pool_rkey', $lastPoolKey = \sanitize_key(\wp_hash(implode('*', array(
					$pool->getId(),
					$pool->getStackId(),
					rand()
				)))));
			}
		}

		$key = \sanitize_key(\wp_hash(implode('*', array(
			$pool->getId(),
			$pool->getStackId(),
			$unlockable->getId(),
			$unlockable->getType(),
			$user->ID,
			$user->user_email
		))));

		return \add_query_arg(array(
			'lwsrewardclaim' => $lastUserKey,
			'lwstoken1' => $lastPoolKey,
			'lwstoken2' => self::getUnlockableKey($user, $lastPool, $unlockable)
		), $url);
	}

	static public function getUnlockableKey($user, $pool, $unlockable)
	{
		return \sanitize_key(\wp_hash(implode('*', array(
			$pool->getId(),
			$pool->getStackId(),
			$unlockable->getId(),
			json_encode($unlockable->getData(true)),
			$user->ID,
			$user->user_email
		))));
	}

	function __construct()
	{
		\add_action('query_vars', array($this, 'queryVars'));
		\add_action('parse_request', array($this, 'parse'));
		\add_action('lws_adminpanel_stygen_content_get_lws_reward_claim', array($this, 'popupTemplate'));

		\add_action('wp_enqueue_scripts', array($this, 'feedbackScripts'));
		\add_action('wp_footer', array($this, 'feedbackDom'));
	}


	/** Tell wordpress to look for our url argument (then readable in $query_vars) */
	public function queryVars($query_vars=array())
	{
		$query_vars[] = 'lwsrewardclaim';
		$query_vars[] = 'lwstoken1';
		$query_vars[] = 'lwstoken2';
		return $query_vars;
	}

	/** Check arguments, then generate the reward and register a user notification.
	 * Finally redirect to erase the argument from url. */
	public function parse($query)
	{
		if( $this->backwardCompatibility() )
			return;

		$userKey = isset($query->query_vars['lwsrewardclaim']) ? trim($query->query_vars['lwsrewardclaim']) : '';
		$poolKey = isset($query->query_vars['lwstoken1']) ? trim($query->query_vars['lwstoken1']) : '';
		$key = isset($query->query_vars['lwstoken2']) ? trim($query->query_vars['lwstoken2']) : '';

		if( !(empty($userKey) || empty($poolKey) || empty($key)) )
		{
			global $wpdb;
			// find claimer user
			$user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='lws_woorewards_user_key' AND meta_value=%s", $userKey));
			$user = empty($user_id) ? false : \get_user_by('ID', $user_id);

			// find claimed pool
			$pool_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='lws_woorewards_pool_rkey' AND meta_value=%s", $poolKey));
			$pool = empty($pool_id) ? false : \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p'=>$pool_id))->last();

			$claimed = false;
			if( !(empty($user) || empty($pool)) )
			{
				// check current user if any
				$loggedUserId = \get_current_user_id();
				if( !empty($loggedUserId) && $loggedUserId != $user->ID )
				{
					$this->registerNotice(
						__("User conflict", LWS_WOOREWARDS_PRO_DOMAIN),
						__("Operation abort since the connected user is not the owner of the requested reward.", LWS_WOOREWARDS_PRO_DOMAIN),
						$user->ID, $pool->getId()
					);
					\wp_redirect(\home_url());
					exit;
				}

				// find the claimed unlockable
				foreach( $pool->getUnlockables()->asArray() as $unlockable )
				{
					if( $key == self::getUnlockableKey($user, $pool, $unlockable) )
					{
						$claimed = $unlockable;
						break;
					}
				}

				if( $claimed )
				{
					if( $pool->unlock($user, $claimed) )
					{
						// success
						$this->registerNotice(__("You just got a new Reward", LWS_WOOREWARDS_PRO_DOMAIN), sprintf(
							"<h1 class='lws-wr-claim-title'>%s</h1><p class='lws-wr-claim-description'>%s</p>",
							$claimed->getTitle(),
							$claimed->getReason('frontend')
						), $user->ID, $pool->getId());
					}
					else if( !$pool->isBuyable() ) // fail, pool passed away
						$this->registerNotice(
							__("The requested reward cannot be unlocked", LWS_WOOREWARDS_PRO_DOMAIN),
							__("The requested reward cannot be unlocked. The loyalty system has expired.", LWS_WOOREWARDS_PRO_DOMAIN),
							$user->ID, $pool->getId()
						);
					else // fail, perhaps insufisent point
						$this->registerNotice(
							__("The requested reward cannot be unlocked", LWS_WOOREWARDS_PRO_DOMAIN),
							__("The requested reward cannot be unlocked, perhaps have you already spent the required point amount? Please, have a look at the rewards list on the site.", LWS_WOOREWARDS_PRO_DOMAIN),
							$user->ID, $pool->getId()
						);
				}
				else // no reward found
					$this->registerNotice(
						__("The requested reward cannot be found", LWS_WOOREWARDS_PRO_DOMAIN),
						__("Rewards should have been updated. Please, have a look at the reward list on the site.", LWS_WOOREWARDS_PRO_DOMAIN),
							$user->ID, $pool->getId()
					);
			}
			else // user or pool not found
				$this->registerNotice(
					__("User or loyalty system cannot be found", LWS_WOOREWARDS_PRO_DOMAIN),
					__("The link you followed seems obsolete. But don't worry, your loyalty points are still there. Please have a look at the rewards list on the site.", LWS_WOOREWARDS_PRO_DOMAIN)
				);

			if( !$claimed )
			{
				if( \LWS_WooRewards::isWC() )
				{
					\wp_redirect(\wc_get_endpoint_url('lws_woorewards', '', \wc_get_page_permalink('myaccount')));
					exit;
				}
			}
			// anyway, redirect to remove the url argument
			\wp_redirect(\remove_query_arg($this->queryVars()));
			exit;
		}
	}

	protected function registerNotice($title, $message, $user_id=0, $pool_id=0)
	{
		\update_option('lws_wr_rewardclaim_notice', array(
			'title'   => $title,
			'message' => $message,
			'user_id' => $user_id,
			'pool_id' => $pool_id
		));
	}

	/** Detect a v2 claim link.
	 *	@return true if an old claim is detected */
	protected function backwardCompatibility()
	{
		$key = isset($_REQUEST['lws-wr-claim']) ? trim($_REQUEST['lws-wr-claim']) : false;
		if( !empty($key) )
		{
			// register a notification
			$this->registerNotice(
					__("Out-of-date link detected", LWS_WOOREWARDS_PRO_DOMAIN),
					__("The link you followed seems obsolete. But don't worry, your loyalty points are still there. Please have a look at the rewards list on the site.", LWS_WOOREWARDS_PRO_DOMAIN)
				);

			if( \LWS_WooRewards::isWC() )
			{
				\wp_redirect(\wc_get_endpoint_url('lws_woorewards', '', \wc_get_page_permalink('myaccount')));
				exit;
			}
			return true;
		}
		return false;
	}

	function popupTemplate()
	{
		$this->stygen = true;
		$notice = array(
			'title' => 'The Unlocked Reward Title',
			'message' => 'The desscription of the unlocked reward',
		);
		$unlockables = array(
			array(
				'thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/cat.png','title' => 'The Cat Reward','description' => 'This is not a real reward - But it looks cool anyway','poolname' => 'default',
				'pooltitle' => 'Standard System','cost' => '50','userpoints' => '254','unlocklink' => '#', 'pointname' => 'Points',
			),
			array(
				'thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/horse.png','title' => 'The New Woo Reward','description' => 'This is not a real reward - But it looks cool too','poolname' => 'standard',
				'pooltitle' => 'Standard System','cost' => '90','userpoints' => '254','unlocklink' => '#', 'pointname' => 'Points'
			),
		);
		$content = $this->getFeedbackDom($notice,$unlockables);
		unset($this->stygen);
		return $content;
	}

	function feedbackScripts()
	{
		\wp_enqueue_style('lws-wr-rewardclaim-style', LWS_WOOREWARDS_PRO_CSS.'/templates/rewardclaim.css?stygen=lws_woorewards_reward_claim', array(), LWS_WOOREWARDS_PRO_VERSION);

		if( !empty(\get_option('lws_wr_rewardclaim_notice')) )
		{
			\wp_enqueue_script('lws-wr-rewardclaim', LWS_WOOREWARDS_PRO_JS . '/rewardclaim.js', array('jquery', 'jquery-ui-core'), LWS_WOOREWARDS_PRO_VERSION, true);
		}
	}

	function feedbackDom()
	{
		if( !empty($notice = \get_option('lws_wr_rewardclaim_notice')) )
		{
			$unlockables = '';
			// show user available rewards (if option set)
			if( !empty(\get_option('lws_wr_rewardclaim_notice_with_rest', 'on')) && isset($notice['pool_id']) && !empty($notice['pool_id']) )
			{
				$dataGet = new \LWS\WOOREWARDS\PRO\VariousData();
				$unlockables = $dataGet->getUnlockables(\get_current_user_id(),'avail');
			}

			echo $this->getFeedbackDom($notice, $unlockables, 'lws_wooreward_rewardclaimed');
			\update_option('lws_wr_rewardclaim_notice', '');
		}
	}

	/** @param $notice array('title'=>'', 'message'=>'', 'user_id'=>0, 'pool_id'=>0)
	 *	@param $unlockables : additional rewards that can still be unlocked
	 *	@param $popupId : additional id that will provoke popup animation
	 *	@return (string) html div */
	public function getFeedbackDom($notice, $unlockables='',$popupId = '')
	{
		$title = \lws_get_option('lws_woorewards_wc_reward_claim_title',__("New reward unlocked !", LWS_WOOREWARDS_PRO_DOMAIN));
		$header = \lws_get_option('lws_woorewards_wc_reward_claim_header',__("You've just unlocked the following reward :", LWS_WOOREWARDS_PRO_DOMAIN));
		$stitle = \lws_get_option('lws_woorewards_wc_reward_claim_stitle',__("Other rewards are waiting for you", LWS_WOOREWARDS_PRO_DOMAIN));
		$notice = \wp_parse_args($notice, array('title'=>'', 'message'=>'', 'user_id'=>0, 'pool_id'=>0));
		if( !isset($this->stygen) )
		{
			$title = \apply_filters('wpml_translate_single_string', $title, 'Widgets', "WooRewards - Reward Claim Popup - Title");
			$header = \apply_filters('wpml_translate_single_string', $header, 'Widgets', "WooRewards - Reward Claim Popup - Header");
			$stitle = \apply_filters('wpml_translate_single_string', $stitle, 'Widgets', "WooRewards - Reward Claim Popup - Subtitle");
		}

		$orcontent ='';
		if(!empty($unlockables) && !empty(\get_option('lws_wr_rewardclaim_notice_with_rest', 'on')))
		{
			$orcontent .= <<<EOT
			<div class='lwss_selectable lws-woorewards-reward-claim-others' data-type='Unlockable rewards'>
				<div class='lwss_selectable lwss_modify lws-wr-reward-claim-stitle' data-id='lws_woorewards_wc_reward_claim_stitle' data-type='Second Title'>
					<span class='lwss_modify_content'>{$stitle}</span>
				</div>
EOT;
			foreach( $unlockables as $unlockable )
			{
				$pointName = isset($unlockable['pointname']) ? $unlockable['pointname'] : 'Points';
				$labels = array(
					'lsystem' 		=> __("Loyalty System", LWS_WOOREWARDS_PRO_DOMAIN),
					'ypoints' 		=> sprintf(__("Your %s", LWS_WOOREWARDS_PRO_DOMAIN), strtolower($pointName)),
					'unlock' 		=> __("Unlock", LWS_WOOREWARDS_PRO_DOMAIN),
					'cost'	 		=> sprintf(__("%s cost", LWS_WOOREWARDS_PRO_DOMAIN), ucfirst(strtolower($pointName)))
				);
					$orcontent .= <<<EOT
			<div class='lwss_selectable lws-woorewards-reward-claim-other' data-type='Unlockable reward'>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-thumb' data-type='Unlockable thumbnail'><img src='{$unlockable['thumbnail']}'/></div>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-cont' data-type='Unlockable details'>
					<div class='lwss_selectable lws-woorewards-reward-claim-other-title' data-type='Unlockable Title'>{$unlockable['title']}</div>
					<div class='lwss_selectable lws-woorewards-reward-claim-other-desc' data-type='Unlockable Description'>{$unlockable['description']}</div>
				</div>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-info' data-type='Unlockable Informations'><table class='lwss_selectable lws-woorewards-reward-claim-other-table' data-type='Information table'>
					<tr><th class='lwss_selectable lws-woorewards-reward-claim-other-th' data-type='Information header'>{$labels['lsystem']}</th><td>{$unlockable['pooltitle']}</td></tr>
					<tr><th class='lwss_selectable lws-woorewards-reward-claim-other-th' data-type='Information header'>{$labels['ypoints']}</th><td>{$unlockable['userpoints']}</td></tr>
					<tr><th class='lwss_selectable lws-woorewards-reward-claim-other-th' data-type='Information header'>{$labels['cost']}</th><td>{$unlockable['cost']}</td></tr>
				</table></div>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-unlock' data-type='Unlockable Action'>
					<a class='lwss_selectable lws-woorewards-reward-claim-other-button' data-type='Unlock Button' href="{$unlockable['unlocklink']}">{$labels['unlock']}</a>
				</div>
			</div>
EOT;
			}
		}

        $content = <<<EOT
            <div id='{$popupId}' class='lwss_selectable lws-woorewards-reward-claim-cont' data-type='Main Container'>
				<div class='lws-wr-reward-claim-titleline'>
					<div class='lwss_selectable lwss_modify lws-wr-reward-claim-title' data-id='lws_woorewards_wc_reward_claim_title' data-type='Title'>
						<span class='lwss_modify_content'>{$title}</span>
					</div>
					<div class='lwss_selectable lws-wr-reward-claim-close lws-icon-close' data-type='Close Button'></div>
				</div>
				<div class='lwss_selectable lwss_modify lws-wr-reward-claim-header' data-id='lws_woorewards_wc_reward_claim_header' data-type='Header'>
					<span class='lwss_modify_content'>{$header}</span>
                </div>
				<div class='lwss_selectable lws-wr-reward-claimed' data-type='Unlocked reward'>
					<div class='lwss_selectable lws-wr-reward-claimed-title' data-type='Reward Title'>{$notice['title']}</div>
					<div class='lwss_selectable lws-wr-reward-claimed-desc' data-type='Reward Description'>{$notice['message']}</div>
                </div>
                $orcontent
			</div>
EOT;

        return $content;
	}

}

?>