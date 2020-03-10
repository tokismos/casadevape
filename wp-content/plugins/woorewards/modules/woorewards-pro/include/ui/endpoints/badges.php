<?php
namespace LWS\WOOREWARDS\PRO\Ui\Endpoints;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/endpoints/endpoint.php';

/** Create an endpoint in frontpage.
 * Show customer owned badges. */
class BadgesEndpoint extends \LWS\WOOREWARDS\PRO\Ui\Endpoints\Endpoint
{
	function __construct()
	{
		if( $this->isActive('lws_woorewards_wc_my_account_endpoint_badges', 'on') ){
			$libPage = \lws_get_option('lws_woorewards_wc_my_account_badges_label', __("My Badges", LWS_WOOREWARDS_PRO_DOMAIN));
			parent::__construct('lws_badges', $libPage);
		}
		\add_action('wp_enqueue_scripts', array( $this , 'scripts' ), 100);
		\add_filter('lws_adminpanel_themer_content_get_'.'wc_badges_endpoint', array($this, 'template'));

	}

	public function scripts()
	{
		\wp_enqueue_style('woorewards-badges-endpoint', LWS_WOOREWARDS_PRO_CSS.'/badges-endpoint.css?themer=lws_wre_myaccount_badges_view', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function defaultLabels()
	{
		return array(
			'boverview' 	=> __("Your Badges", LWS_WOOREWARDS_PRO_DOMAIN),
			'rarity' 	=> __("Rarity", LWS_WOOREWARDS_PRO_DOMAIN),
			'unlock' 	=> __("Unlock Date", LWS_WOOREWARDS_PRO_DOMAIN),
		);
	}

	function template($snippet)
	{
		$badges = array(
			array(
				'thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/cat.png',
				'title' => 'The Cat',
				'description' => "Look at me. You know I'm cute even when I break your furniture",
				'unlockDate' => date("Y-m-d H:i:s"),
				'rarityPercent' => '54.3',
				'rarityLabel' => 'Common',
			),
			array(
				'thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/horse.png',
				'title' => 'The White Horse',
				'description' => "Arya Stark : I'm out of this s***",
				'unlockDate' => date("Y-m-d H:i:s"),
				'rarityPercent' => '9.6',
				'rarityLabel' => 'Epic',
			),
			array(
				'thumbnail' => LWS_WOOREWARDS_PRO_IMG.'/chthulu.png',
				'title' => 'Chtulhu rules',
				'description' => "You unleashed the power of Chthulu over the world",
				'unlockDate' => date("Y-m-d H:i:s"),
				'rarityPercent' => '1.2',
				'rarityLabel' => 'Legendary',
			),
		);
		return $this->getContent($badges);
	}


	function getPage()
	{
		$badges = array();
		$userId = \get_current_user_id();
		$user_badges = \LWS\WOOREWARDS\PRO\Core\Badge::loadByUser($userId, true);
		foreach( $user_badges as $user_badge )
		{
			$badge['thumbnail'] = $user_badge->getThumbnailUrl();
			$rarity_info = $user_badge->getBadgeRarity();
			$badge['title'] = $user_badge->getTitle();
			$badge['description'] = $user_badge->getMessage();
			$badge['rarityPercent'] = $rarity_info['percentage'];
			$badge['rarityLabel'] = $rarity_info['rarity'];
			$badge['unlockDate'] = $user_badge->ownedBy($userId);
			$badges[] = $badge;
		}
		return $this->getContent($badges);
	}

	function getContent($badges)
	{
		$labels = $this->defaultLabels();
		$content = <<<EOT
		<div class="be-main-container flcol">
			<div class="be-title-container flcol">
				<div class="be-title-line flrow">
					<div class="be-title-text flexooa">{$labels['boverview']}</div>
					<div class="flexiia"></div>
				</div>
			</div>
EOT;

		$content.= "<div class='be-badges-container'>";
		foreach( $badges as $badge )
		{
			$content.= "<div class='be-badge-container'>";
			$content.= "<div class='be-badge-imgcol'><img class='be-badge-img' src='{$badge['thumbnail']}'/></div>";
			$content.= "<div class='be-badge-contentcol'>";
			$content.= "<div class='be-badge-title'>{$badge['title']}</div>";
			$content.= "<div class='be-badge-text'>{$badge['description']}</div>";
			$content.= "<div class='be-badge-extraInfo'>";
			$content.= "<div class='be-badge-rarity'>{$badge['rarityLabel']} - {$badge['rarityPercent']}%</div>";
			$content.= "<div class='be-badge-date'>{$labels['unlock']} : {$badge['unlockDate']}</div>";
			$content.= "</div>";
			$content.= "</div>";
			$content.= "</div>";
		}
		$content .= "</div></div>";
		return $content;
	}

}
