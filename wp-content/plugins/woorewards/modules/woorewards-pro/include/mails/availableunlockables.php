<?php
namespace LWS\WOOREWARDS\PRO\Mails;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/rewardclaim.php';

/** Setup mail about unlockables available to a user.
 * For each unlockable, a link is provided to unlock it.
 * $data should be an array as:
 *	*	'user' => a WP_User instance
 *	*	'pool' => a LWS\WOOREWARDS\PRO\Core\Pool instance
 *	*	'points' => integer value, remaining points
 *	*	'unlockables' => a LWS\WOOREWARDS\Collections\Unlockables instance */
class AvailableUnlockables
{
	protected $template = 'wr_available_unlockables';

	public function __construct()
	{
		\add_filter( 'lws_woorewards_mails', array($this, 'addTemplate'), 21 );// priority: mail order in settings
		\add_filter( 'lws_mail_settings_' . $this->template, array($this, 'settings'));
		\add_filter( 'lws_mail_body_' . $this->template, array($this, 'body'), 10, 3);
	}

	public function addTemplate($arr)
	{
		$arr[] = $this->template;
		return $arr;
	}

	public function settings( $settings )
	{
		$settings['domain']        = 'woorewards';
		$settings['settings_name'] = __("Reward Choice", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['about']         = __("Inform a customer about available rewards. Available rewards depend on customer point count. This email provide links to pick one and consume points.", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['subject']       = __("Rewards are waiting for you", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['title']         = __("Rewards are waiting for you !", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['header']        = __("Pick a reward in the following list", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['footer']        = __("Powered by WooRewards", LWS_WOOREWARDS_PRO_DOMAIN);
		$settings['css_file_url']  = LWS_WOOREWARDS_PRO_CSS . '/mails/availableunlockables.css';
		$settings['fields']['enabled'] = array(
			'id' => 'lws_woorewards_enabled_mail_' . $this->template,
			'title' => __("Enabled", LWS_WOOREWARDS_PRO_DOMAIN),
			'type' => 'box',
			'extra' => array(
				'default' => 'on'
			)
		);
		return $settings;
	}


	public function body( $html, $data, $settings )
	{
		if( !empty($html) )
			return $html;
		if( $demo = \is_wp_error($data) )
			$data = $this->placeholders();

		$html = "<tr><td class='lws-middle-cell'>";

		$total = sprintf(
			__("You have %s", LWS_WOOREWARDS_PRO_DOMAIN),
			\LWS_WooRewards::formatPointsWithSymbol($data['points'], empty($data['pool']) ? '' : $data['pool']->getName())
		);
		$html .= "<div class='lwss_selectable lws-middle-cell-points' data-type='Points total'>$total</div>";

		$first = true;
		$sep = "<tr><td class='lwss_selectable lws-rewards-sep' data-type='Rewards Separator' colspan='3'></td></tr>";
		$html .= "<table class='lwss_selectable lws-rewards-table' data-type='Rewards Table'>";

		foreach( $data['unlockables']->asArray() as $unlockable )
		{
			if( $unlockable->isPurchasable() )
			{
				if( !$first )
					$html .= $sep;
				$html .= $this->getUnlockableRow($unlockable, $data['points'], $data['user'], $demo);
				$first = false;
			}
		}

		if( !empty($data['pool']) )
		{
			// what a customer could earn with more points
			$floor = $data['points'];
			$forthcoming = $data['pool']->getUnlockables()->filter(function($item)use($floor){return $item->getCost()>$floor;})->sort();
			foreach( $forthcoming->asArray() as $unlockable )
			{
				if( !$first )
					$html .= $sep;
				$html .= $this->getUnlockableRow($unlockable, $data['points'], $data['user'], $demo);
				$first = false;
			}
		}

		$html .= "</table>";
		$html .= "</td></tr>";
		return $html;
	}

	protected function getUnlockableRow($unlockable, $points, $user, $demo=false)
	{
		$html = "<tr><td class='lwss_selectable lws-rewards-cell-img' data-type='Rewards Image'>";
		// unlocable image
		$img = $unlockable->getThumbnailImage();
		$html .= (empty($img) && $demo) ? "<div class='lws-rewards-thumbnail lws-icon-image'></div>" : $img;

		$html .= "</td><td class='lwss_selectable lws-rewards-cell-left' data-type='Rewards Cell' width='100%'>";
		// unlocable details
		$html .= "<div class='lwss_selectable lws-rewards-name' data-type='Reward Name'>".$unlockable->getTitle()."</div>";
		$html .= "<div class='lwss_selectable lws-rewards-desc' data-type='Reward Description'>".$unlockable->getCustomDescription()."</div>"; // purpose
		if( $points >= $unlockable->getCost() )
		{
			$cost = sprintf(
				__("This reward is worth %s", LWS_WOOREWARDS_PRO_DOMAIN),
				\LWS_WooRewards::formatPointsWithSymbol($unlockable->getCost(), $unlockable->getPoolName())
			);
			$html .= "<div class='lwss_selectable lws-rewards-cost' data-type='Reward Cost'>{$cost}</div>"; // cost
		}
		else
		{
			$cost = sprintf(
				__("This reward is worth %s, you need %s more", LWS_WOOREWARDS_PRO_DOMAIN),
				\LWS_WooRewards::formatPointsWithSymbol($unlockable->getCost(), $unlockable->getPoolName()),
				\LWS_WooRewards::formatPointsWithSymbol($unlockable->getCost()-$points, $unlockable->getPoolName())
			);
			$html .= "<div class='lwss_selectable lws-rewards-more' data-type='Need More points'>{$cost}</div>"; // cost
		}

		$html .= "</td><td class='lwss_selectable lws-rewards-cell-right' data-type='Rewards Cell'>";
		// redeem button
		if( $unlockable->isPurchasable($points, $user->ID) )
		{
			$btn = __("Unlock", LWS_WOOREWARDS_PRO_DOMAIN);
			$href = esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs($this->getUrlTarget($demo), $unlockable, $user));
			$html .= "<a href='$href' class='lwss_selectable lws-rewards-redeem' data-type='Redeem button'>{$btn}</a>";
		}
		else
		{
			$btn = _x("Locked", "redeem button need more points", LWS_WOOREWARDS_PRO_DOMAIN);
			$html .= "<div href='#' class='lwss_selectable lws-rewards-redeem-not' data-type='Not Redeemable button'>{$btn}</div>";
		}
		$html .= "</td></tr>";
		return $html;
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

	protected function placeholders()
	{
		$examples = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create()->byCategory(false, array(\LWS\WOOREWARDS\Core\Pool::T_STANDARD));
		$cost = 0;
		$examples->apply(function($item)use(&$cost){
			$cost += 10;
			$item->setCost($cost);
			if( \method_exists($item, 'setTestValues') )
				$item->setTestValues();
		});
		$pts = 42;
		if( $examples->count() > 1 )
			$pts = $cost - 8;

		return array(
			'user'   => \wp_get_current_user(),
			'points' => $pts,
			'pool'   => null,
			'unlockables' => $examples
		);
	}

}
?>