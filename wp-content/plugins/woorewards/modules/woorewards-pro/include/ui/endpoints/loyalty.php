<?php
namespace LWS\WOOREWARDS\PRO\Ui\Endpoints;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/endpoints/endpoint.php';
//require_once LWS_WOOREWARDS_PRO_INCLUDES . '/variousdata.php';

/** Create an endpoint in frontpage.
 * Show customer loyalty systems and rewards. */
class LoyaltyEndpoint extends \LWS\WOOREWARDS\PRO\Ui\Endpoints\Endpoint
{
	function __construct()
	{
		if( $this->isActive('lws_woorewards_wc_my_account_endpont_loyalty', 'on') ){
			$libPage = \lws_get_option('lws_woorewards_wc_my_account_lar_label', __("Loyalty and Rewards", LWS_WOOREWARDS_PRO_DOMAIN));
			parent::__construct('lws_woorewards', $libPage);
		}
		\add_action('wp_enqueue_scripts', array( $this , 'scripts' ), 100);
		\add_filter('lws_adminpanel_themer_content_get_'.'wc_loyalty_and_rewards', array($this, 'template'));

	}

	public function scripts()
	{
		if( \lws_get_option('lws_woorewards_wc_myaccount_expanded') == 'on')
		{
			\wp_enqueue_style('woorewards-lar-ewpanded', LWS_WOOREWARDS_PRO_CSS.'/lar-expanded.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		}
		\wp_enqueue_script('woorewards-lar-endpoint', LWS_WOOREWARDS_PRO_JS . '/loyalty-and-rewards.js', array('jquery', 'jquery-ui-core','jquery-ui-widget'), LWS_WOOREWARDS_PRO_VERSION, true);
		\wp_enqueue_style('woorewards-lar-endpoint', LWS_WOOREWARDS_PRO_CSS.'/loyalty-and-rewards.css?themer=lws_wre_myaccount_lar_view', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function defaultLabels()
	{
		return array(
			'roverview' 	=> __("Rewards Overview", LWS_WOOREWARDS_PRO_DOMAIN),
			'lsoverview' 	=> __("Loyalty Systems Overview", LWS_WOOREWARDS_PRO_DOMAIN),
			'lsdetails' 	=> __("Loyalty System Details", LWS_WOOREWARDS_PRO_DOMAIN),
			'curcoup' 		=> __("Your current coupons", LWS_WOOREWARDS_PRO_DOMAIN),
			'ccode' 		=> __("Coupon code", LWS_WOOREWARDS_PRO_DOMAIN),
			'cdesc'		 	=> __("Coupon Description", LWS_WOOREWARDS_PRO_DOMAIN),
			'unlockables' 	=> __("Unlockable rewards", LWS_WOOREWARDS_PRO_DOMAIN),
			'lsystem' 		=> __("Loyalty System", LWS_WOOREWARDS_PRO_DOMAIN),
			'unlock' 		=> __("Unlock", LWS_WOOREWARDS_PRO_DOMAIN),
			'sure' 			=> __("Are you sure ?", LWS_WOOREWARDS_PRO_DOMAIN),
			'yes' 			=> __("Yes", LWS_WOOREWARDS_PRO_DOMAIN),
			'cancel' 		=> __("Cancel", LWS_WOOREWARDS_PRO_DOMAIN),
			'notavail' 		=> __("Won't be available anymore", LWS_WOOREWARDS_PRO_DOMAIN),
			'start'	 		=> __("Start date", LWS_WOOREWARDS_PRO_DOMAIN),
			'end'	 		=> __("End date", LWS_WOOREWARDS_PRO_DOMAIN),
			'rewards' 		=> __("Rewards", LWS_WOOREWARDS_PRO_DOMAIN),
			'level'			=> __("Level", LWS_WOOREWARDS_PRO_DOMAIN),
			'levels'		=> __("Levels", LWS_WOOREWARDS_PRO_DOMAIN),
			'info'	 		=> __("Your information", LWS_WOOREWARDS_PRO_DOMAIN),
			'ctotal'		=> __("Current Total", LWS_WOOREWARDS_PRO_DOMAIN),
			'clevel'		=> __("Current Level", LWS_WOOREWARDS_PRO_DOMAIN),
			'crank'			=> __("Current Rank", LWS_WOOREWARDS_PRO_DOMAIN),
			'history' 		=> __("Recent history", LWS_WOOREWARDS_PRO_DOMAIN),
			'sname'	 		=> __("System name", LWS_WOOREWARDS_PRO_DOMAIN),
			'perform'		=> __("Action to perform", LWS_WOOREWARDS_PRO_DOMAIN),
			'redet' 		=> __("Rewards details", LWS_WOOREWARDS_PRO_DOMAIN),
		);
	}

	function template($snippet)
	{
		if( \lws_get_option('lws_woorewards_wc_myaccount_expanded') == 'on')
		{
			\wp_enqueue_style('woorewards-lar-ewpanded', LWS_WOOREWARDS_PRO_CSS.'/lar-expanded.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		}else{
			\wp_enqueue_script('woorewards-lar-endpoint', LWS_WOOREWARDS_PRO_JS . '/loyalty-and-rewards.js', array('jquery', 'jquery-ui-core','jquery-ui-widget'), LWS_WOOREWARDS_PRO_VERSION, true);
		}
		$coupons = array(
			(object)['post_title' => 'CODETEST1', 'post_excerpt' => _x("A fake coupon", "stygen", LWS_WOOREWARDS_PRO_DOMAIN)],
			(object)['post_title' => 'CODETEST2', 'post_excerpt' => _x("Another fake coupon", "stygen", LWS_WOOREWARDS_PRO_DOMAIN).' - '._x("valid for 7 days", "stygen", LWS_WOOREWARDS_PRO_DOMAIN)]
		);
		$unlockables = array(
			array(
				'thumbnail' => '','title' => 'The Woo Reward','description' => 'This is not a real reward - But it looks cool anyway','poolname' => 'default',
				'pooltitle' => 'Standard System','cost' => '50','userpoints' => '254','unlocklink' => '#', 'pointname' => 'points'
			),
			array(
				'thumbnail' => '','title' => 'The New Woo Reward','description' => 'This is not a real reward - But it looks cool too','poolname' => 'standard',
				'pooltitle' => 'Standard System','cost' => '90','userpoints' => '254','unlocklink' => '#', 'pointname' => 'points'
			),
		);
		$poolsData = array(
			array(
				'userData' => array(
					'currentPoints' => '254',
					'symbolPoints' => '254 Points',
					'history' => array(
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '-50', 'op_result' => '254', 'op_reason' => 'Generated the coupon LWS123456'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '10', 'op_result' => '254', 'op_reason' => 'This is a test history for 10 points'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '20', 'op_result' => '254', 'op_reason' => 'This is a test history for 20 points'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '30', 'op_result' => '254', 'op_reason' => 'This is a test history for 30 points'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '40', 'op_result' => '254', 'op_reason' => 'This is a test history for 40 points'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '50', 'op_result' => '254', 'op_reason' => 'This is a test history for 50 points'),
					)
				),
				'poolInfo' => array(
					'type' => 'standard',
					'title' => 'Standard System',
					'start' => '',
					'end' => '',
					'singular' => 'point',
					'plural' => 'points',
					'events' => array(
						array('desc' => 'Buy the product <a href="#">WooRewards</a>', 'earned' => '123'),
						array('desc' => 'Spend money', 'earned' => '5 Points/1 &#36;'),
						array('desc' => 'Review a product', 'earned' => '5'),
						array('desc' => 'Recurrent visit', 'earned' => '1'),
					),
					'rewards' => array(
						array(
							'img' => '','title' => 'The Woo Reward','cost' => '50','owned' => '1',
							'desc' => '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&#36;</span>10.00</span> discount on an order'
						),
						array(
							'img' => '','title' => 'Free Prduct','cost' => '100','owned' => '0',
							'desc' => "<a target='_blank' href='#' class='lws-woorewards-free-product-link'>Beanie</a> offered with an order"
						),
					)
				)
			),
			array(
				'userData' => array(
					'currentPoints' => '272',
					'symbolPoints' => '272 Points',
					'history' => array(
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '-50', 'op_result' => '254', 'op_reason' => 'Generated the coupon LWS123456'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '10', 'op_result' => '254', 'op_reason' => 'This is a test history for 10 points'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '20', 'op_result' => '254', 'op_reason' => 'This is a test history for 20 points'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '30', 'op_result' => '254', 'op_reason' => 'This is a test history for 30 points'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '40', 'op_result' => '254', 'op_reason' => 'This is a test history for 40 points'),
						array('op_date' => date("Y-m-d H:i:s"), 'op_value' => '50', 'op_result' => '254', 'op_reason' => 'This is a test history for 50 points'),
					),
					'currentLevel' => '2',
					'currentLevelName' => 'VIP 2'
				),
				'poolInfo' => array(
					'type' => 'levelling',
					'title' => 'Levelling System',
					'start' => '',
					'end' => '',
					'singular' => 'point',
					'plural' => 'points',
					'events' => array(
						array('desc' => 'Spend money', 'earned' => '2 Points/1 &#36;'),
					),
					'levels' => array(
						array(
							'cost' => '100',
							'title' => 'VIP 1',
							'points' => '100 Points',
							'passed' => '1',
							'rewards' => array(
								array(
									'img' => '','title' => 'The VIP 1 Reward','owned' => '1',
									'desc' => '5% discount on an order (<i>permanent</i>)'
								),
								array(
									'img' => '','title' => 'Hey, that&rsquo;s yours','owned' => '1',
									'desc' => "<a target='_blank' href='#' class='lws-woorewards-free-product-link'>Polo</a> offered with an order"
								),
							)
						),
						array(
							'cost' => '200',
							'title' => 'VIP 2',
							'points' => '200 Points',
							'passed' => '1',
							'rewards' => array(
								array(
									'img' => '','title' => 'The VIP 2 Reward','owned' => '1',
									'desc' => '10% discount on an order (<i>permanent</i>)'
								),
							)
						),
						array(
							'cost' => '500',
							'title' => 'Ultimate VIP',
							'points' => '500 Points',
							'passed' => '',
							'rewards' => array(
								array(
									'img' => '','title' => 'The Special Diner','owned' => '',
									'desc' => 'A diner with Andre'
								),
							)
						),
					)
				)
			),
		);
		return $this->getContent($coupons, $unlockables, $poolsData);
	}


	function getPage()
	{
		$dataGet = new \LWS\WOOREWARDS\PRO\VariousData();
		$coupons = array();
		$unlockables = array();
		$poolsData = array();
		$userId = \get_current_user_id();
		$coupons = $dataGet->getCoupons($userId);
		list($unlockables, $poolsData) = $dataGet->getLoyaltySystemsInfo($userId);
		return $this->getContent($coupons, $unlockables, $poolsData, $userId);
	}

	function getContent($coupons, $unlockables, $poolsData)
	{
		$content = '';
		$labels = $this->defaultLabels();
		$extended = (\lws_get_option('lws_woorewards_wc_myaccount_expanded') == 'on') ? ' extended' : '';
		$content .= <<<EOT
		<div class="lar_main_container flcol{$extended}">
			<div class="lar_accordeon_container flcol">
				<div class="lar-accordeon-title-line flrow">
					<div class="lar-accordeon-title-text flexooa">{$labels['roverview']}</div>
					<div class="flexiia"></div>
				</div>
EOT;

		if(!empty($coupons))
			$content .= $this->showCoupons($coupons,$labels);


		if(!empty($unlockables))
			$content .= $this->showUnlockables($unlockables,$labels);

		$content .= <<<EOT
		</div>
		<div class="lar-hor-sep"></div>
		<div class="lar_accordeon_container flcol">
			<div class="lar-accordeon-title-line flrow">
				<div class="lar-accordeon-title-text flexooa">{$labels['lsoverview']}</div>
				<div class="flexiia"></div>
			</div>
EOT;

		if(!empty($poolsData))
			$content .= $this->showPoolsData($poolsData,$labels);

		$content .= "</div>";
		return $content;
	}

	function showCoupons($coupons,$labels)
	{
		$compte = count($coupons);

		$retour = <<<EOT
		<div class="lar-accordeon-item">
		<div class="lar-accordeon-not-expanded-cont flcol">
			<div class="flrow lar_overflow">
				<div class="flexooa lar-line-header">{$labels['curcoup']}</div>
				<div class="flexiia lar-line-header"></div>
				<div class="flexooa lar-line-header hlast">{$compte}</div>
			</div>
		</div>
		<div class="lar-accordeon-expanded-cont flcol flexoia">
		<div class="flrow lar-expanded-line lar-overcolors">
			<div class="flexooa lar-line-header">{$labels['curcoup']}</div>
			<div class="flexiia lar-line-header"></div>
			<div class="flexooa lar-line-header lar-acc-icon lws-icon-circle-up"></div>
		</div>
		<div class="flrow">
		<div class="flex00a"></div>
		<div class="flexiia">
			<table class="lar-coupons-list" cellpadding='0' cellspacing='0'>
				<thead><tr><td>{$labels['ccode']}</td><td>{$labels['cdesc']}</td></tr></thead>
				<tbody>
EOT;
		foreach( $coupons as $coupon )
		{
			$code = \esc_attr($coupon->post_title);
			$descr = $coupon->post_excerpt;
			$retour.= "<tr><td class='lar-coupons-list-code'>{$code}</td>";
			$retour.= "<td class='lar-coupons-list-description lar-main-color'>{$descr}</td></tr>";
		}
		$retour .= "</tbody></table></div></div></div></div>";
		return $retour;
	}

	function showUnlockables($unlockables, $defaultlabels)
	{
		$compte = count($unlockables);
		$labels = $defaultlabels;

		$retour = <<<EOT
		<div class="lar-accordeon-item">
		<div class="lar-accordeon-not-expanded-cont flcol">
			<div class="flrow lar_overflow">
				<div class="flexooa lar-line-header">{$labels['unlockables']}</div>
				<div class="flexiia lar-line-header"></div>
				<div class="flexooa lar-line-header hlast">{$compte}</div>
			</div>
		</div>
		<div class="lar-accordeon-expanded-cont flcol flexoia">
		<div class="flrow lar-expanded-line lar-overcolors">
			<div class="flexooa lar-line-header">{$labels['unlockables']}</div>
			<div class="flexiia lar-line-header"></div>
			<div class="flexooa lar-line-header lar-acc-icon lws-icon-circle-up"></div>
		</div>
		<div class="flrow">
		<div class="lar_unlockables_list flexiia">
EOT;
		foreach( $unlockables as $unlockable )
		{
			// and we extract the point name
			$labels = $defaultlabels;
			$pointName = $unlockable['pointname'];
			$labels['ypoints'] = sprintf(__("Your %s", LWS_WOOREWARDS_PRO_DOMAIN), $pointName);
			$labels['cost'] = sprintf(__("%s cost", LWS_WOOREWARDS_PRO_DOMAIN), $pointName);

			$lsys = \esc_attr($unlockable['poolname']);
			$retour .= <<<EOT
		<div class='lar-unlockable flrow' data-lsys='{$lsys}' data-cpoints='{$unlockable['userpoints']}' data-cost='{$unlockable['cost']}'>
			<div class='lar-unlockable-imgcol flcol flexooa'>{$unlockable['thumbnail']}</div>
			<div class='lar-unlockable-detcol flcol flexiia'>
				<div class='lar-unlockable-detcol-title'>{$unlockable['title']}</div>
				<div class='lar-unlockable-detcol-description'>{$unlockable['description']}</div>
			</div>
			<div class='lar-unlockable-infocol flexooa'><table class='lar-unlockable-infotable'>
				<tr><th>{$labels['lsystem']}</th><td>{$unlockable['pooltitle']}</td></tr>
				<tr><th>{$labels['ypoints']}</th><td>{$unlockable['userpoints']}</td></tr>
				<tr><th>{$labels['cost']}</th><td>{$unlockable['cost']}</td></tr>
			</table></div>
			<div class="lar-unlockable-unlockcol flcol">
				<div class="lar-unlockable-unlock-line flrow flexiia">
					<div class="lar-unlockable-unlock-btn">{$labels['unlock']}</div>
				</div>
				<div class="lar-unlockable-confirm-line">
					<div class="lar-unlockable-confirm-text">{$labels['sure']}</div>
					<div class="lar-unlockable-confirm-btns">
						<div class="lar-unlockable-confirm-no">{$labels['cancel']}</div>
						<a href="{$unlockable['unlocklink']}" class="lar-unlockable-confirm-yes">{$labels['yes']}</a>
					</div>
				</div>
			</div>
			<div class='lar-unlockable-not'>{$labels['notavail']}</div>
		</div>
EOT;
		}
		$retour .= "</div></div></div>";
		return $retour;
	}

	function showPoolsData($poolsData, $defaultlabels)
	{
		$retour = '';
		foreach ($poolsData as $poolData)
		{
			if( empty($poolData['userData']['currentLevel']) )
				$libelle = '<b>' . $poolData['userData']['symbolPoints'] .'</b>';
			else
				$libelle = ($poolData['userData']['symbolPoints'] . ' | ' . $labels['level'] . ' ' . $poolData['userData']['currentLevel'] . ' | <b>' . $poolData['userData']['currentLevelName'] . '</b>');

			$startDate = '';$startDateWithDiv = '';
            if (!empty($poolData['poolInfo']['start'])) {
				$startDate = \date_i18n(\get_option('date_format'), $poolData['poolInfo']['start']->getTimestamp());
                $startDateWithDiv = <<<EOT
				<div class="flex00a lar-lsov-sline-couple flrow">
				<div class="flex00a lar-lsov-sline-label">{$labels['start']}</div>
				<div class="flex00a lar-lsov-sline-value lar-main-color">{$startDate}</div>
			</div>
EOT;
            }

			$endDate = '';$endDateWithDiv = '';
            if (!empty($poolData['poolInfo']['end'])) {
                $endDate = \date_i18n(\get_option('date_format'), $poolData['poolInfo']['end']->getTimestamp());
                $endDateWithDiv = <<<EOT
				<div class="flex00a lar-lsov-sline-couple flrow">
				<div class="flex00a lar-lsov-sline-label">{$labels['end']}</div>
				<div class="flex00a lar-lsov-sline-value lar-main-color">{$endDate}</div>
			</div>
EOT;
            }
			$eventCount = count($poolData['poolInfo']['events']);
			$type = ($poolData['poolInfo']['type'] == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING ? 'levels' : 'rewards');
			$rlcount = count($poolData['poolInfo'][$type]);
			$labels = $defaultlabels;
			$labels['ways'] = sprintf(__("Ways to earn %s", LWS_WOOREWARDS_PRO_DOMAIN), $poolData['poolInfo']['plural']);
			$extraclass = strtolower(str_replace(' ','',$poolData['poolInfo']['title']));
			$retour .= <<<EOT
		<div class="lar-accordeon-item {$extraclass}">
			<div class="lar-accordeon-not-expanded-cont flcol">
				<div class="lar-lsov-top-title flrow lar_overflow">
					<div class="flexooa lar-line-header">{$poolData['poolInfo']['title']}</div>
					<div class="flexiia lar-line-header"></div>
					<div class="flexooa lar-line-header hlast">{$libelle}</div>
				</div>
				<div class="lar-lsov-sline flrow">
					<div class="flexiia lar-lsov-sline-filler"></div>
					{$startDateWithDiv}
					{$endDateWithDiv}
					<div class="flex00a lar-lsov-sline-couple flrow">
						<div class="flex00a lar-lsov-sline-label">{$labels['ways']}</div>
						<div class="flex00a lar-lsov-sline-value lar-main-color">{$eventCount}</div>
					</div>
					<div class="flex00a lar-lsov-sline-couple flrow">
						<div class="flex00a lar-lsov-sline-label">{$labels[$type]}</div>
						<div class="flex00a lar-lsov-sline-value lar-main-color">{$rlcount}</div>
					</div>
				</div>
			</div>
			<div class="lar-accordeon-expanded-cont flcol flexoia">
				<div class="flrow lar-expanded-line lar-overcolors">
					<div class="flexooa lar-line-header">{$poolData['poolInfo']['title']}</div>
					<div class="flexiia lar-line-header"></div>
					<div class="flexooa lar-line-header lar-acc-icon lws-icon-circle-up"></div>
				</div>
				<div class="lar-lsov-det-cont flrow padtl5">
EOT;

			/* User Information */
			if( $this->isActive('lws_woorewards_wc_my_account_endpoint_history', 'on') ){
				$retour .= $this->showUserPoolInfo($poolData,$labels);
			}

			/* pool Details */
			$retour .= $this->showPoolInfo($poolData,$labels, $startDate, $endDate);

			$retour .= "</div></div></div>";
		}
		return $retour;
	}

	function showUserPoolInfo($poolData, $labels)
	{
		$retour = <<<EOT
		<div class="lar-lsov-customer-info flcol flexiia">
			<div class="lar-lsov-det-top flrow">
				<div class="lar-lsov-stitle lar-main-color flexooa">{$labels['info']}</div>
				<div class="flexiia"></div>
			</div>
			<div class="lar-lsov-det-body flcol">
				<div class="lar-lsov-det-body-line flrow flexooa">
					<div class="lar-lsov-det-title flexiia">{$labels['ctotal']}</div>
					<div class="lar-lsov-det-info flexooa">{$poolData['userData']['symbolPoints']}</div>
				</div>
EOT;

		if($poolData['poolInfo']['type'] == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
		{
			$retour .= <<<EOT
			<div class="lar-lsov-det-body-line flrow flexooa">
				<div class="lar-lsov-det-title flexiia">{$labels['clevel']}</div>
				<div class="lar-lsov-det-info flexooa">{$poolData['userData']['currentLevel']}</div>
			</div>
			<div class="lar-lsov-det-body-line flrow flexooa">
				<div class="lar-lsov-det-title flexiia">{$labels['crank']}</div>
				<div class="lar-lsov-det-info flexooa">{$poolData['userData']['currentLevelName']}</div>
			</div>
EOT;
		}

		$retour .= 	"<div class='lar-lsov-det-body-line flrow flexooa'><div class='lar-lsov-det-title flexiia'>{$labels['history']}</div></div>";
		foreach($poolData['userData']['history'] as $item)
		{
			$retour .= "<div class='lar-lsov-det-body-line flrow flexooa'>";
			$retour .= "<div class='lar-lsov-det-histline flexiia'>{$item['op_reason']}</div>";
			$retour .= "<div class='lar-lsov-det-histvalue flexooa'>{$item['op_value']}</div></div>";
		}

		$retour .= "</div></div>";
		return $retour;
	}

	function showPoolInfo($poolData, $labelsDefault, $startDate, $endDate)
	{
		$labels = $labelsDefault;
		$labels['earned'] = sprintf(__("Earned %s", LWS_WOOREWARDS_PRO_DOMAIN), $poolData['poolInfo']['plural']);
		$labels['cost'] = sprintf(__("%s cost", LWS_WOOREWARDS_PRO_DOMAIN), $poolData['poolInfo']['plural']);
		$retour = <<<EOT
		<div class="lar-lsov-ls-info flcol flexdia">
			<div class="lar-lsov-det-top flrow">
				<div class="lar-lsov-stitle lar-main-color flexooa">{$labels['lsdetails']}</div>
				<div class="flexiia"></div>
			</div>
			<div class="lar-lsov-det-bodyr flcol">
				<div class="lar-lsov-ls-top flrow">
					<div class="lar-lsov-ls-cell flexiia flrow">
						<div class="lar-lsov-ls-cell-title flexiia">{$labels['sname']}</div>
						<div class="lar-lsov-ls-cell-value flexooa">{$poolData['poolInfo']['title']}</div>
					</div>
					<div class="lar-lsov-ls-cell-sep"></div>
					<div class="lar-lsov-ls-cell flexiia flrow">
						<div class="lar-lsov-ls-cell-title flexiia">{$labels['start']}</div>
						<div class="lar-lsov-ls-cell-value flexooa">{$startDate}</div>
					</div>
					<div class="lar-lsov-ls-cell-sep"></div>
					<div class="lar-lsov-ls-cell flexiia flrow">
						<div class="lar-lsov-ls-cell-title flexiia">{$labels['end']}</div>
						<div class="lar-lsov-ls-cell-value flexooa">{$endDate}</div>
					</div>
				</div>
				<div class="lar-lsov-ls-body flcol">
					<div class="lar-lsov-ls-earn-cont flcol flexiia">
						<div class="lar-lsov-ls-title-line flrow">
							<div class="lar-lsov-ls-title flexooa">{$labels['ways']}</div>
							<div class="flexiia"></div>
						</div>
						<div class="lar-lsov-ls-table-title flrow">
							<div class="flexiia">{$labels['perform']}</div>
							<div class="flexooa">{$labels['earned']}</div>
						</div>
EOT;

		foreach($poolData['poolInfo']['events'] as $item)
		{
			$retour .= "<div class='lar-lsov-ls-table-line flrow'>";
			$retour .= "<div class='flexiia'>{$item['desc']}</div>";
			$retour .= "<div class='lar-lsov-ls-table-line-value flexooa'>{$item['earned']}</div></div>";
		}

		$retour .= "</div>";

		if($poolData['poolInfo']['type']==\LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
			$retour .= $this->showLevelPoolInfo($poolData,$labels);
		else
			$retour .= $this->showStandardPoolInfo($poolData,$labels);

		$retour .= "</div></div></div>";

		return $retour;
	}

	function showStandardPoolInfo($poolData, $labels)
	{
		$retour = <<<EOT
		<div class="lar-lsov-ls-reward-cont flexiia">
			<div class="lar-lsov-ls-title-line flrow">
				<div class="lar-lsov-ls-title flexooa">{$labels['rewards']}</div>
				<div class="flexiia"></div>
			</div>
			<div class="lar-lsov-ls-table-title flrow">
				<div class="flexiia">{$labels['redet']}</div>
				<div class="flexooa">{$labels['cost']}</div>
			</div>
EOT;

		foreach($poolData['poolInfo']['rewards'] as $reward)
		{
			$cost = $reward['cost'];
			if( $reward['owned'] )
				$cost = "<div class='lar-lsov-ls-passed flexooa lws-icon-checkmark'></div>";

			$retour .= "<div class='lar-lsov-ls-table-line flrow'>";
			$retour .= "<div class='flexiia'>{$reward['desc']}</div>";
			$retour .= "<div class='lar-lsov-ls-table-line-value flexooa'>{$cost}</div></div>";
		}
		$retour .= "</div>";

		return $retour;
	}



	function showLevelPoolInfo($poolData, $labels)
	{
		$retour = '';
		$levelcount = 0;

		foreach($poolData['poolInfo']['levels'] as $level)
		{
			if(!empty($level)){
				$levelcount += 1;
				$rowtitle = $labels['level'].' '.$levelcount;
				$retour .= "<div class='lar-lsov-ls-reward-cont flexiia'>";
				$retour .= "<div class='lar-lsov-ls-title-line flrow'>";
				$retour .= "<div class='lar-lsov-ls-title flexooa'>{$rowtitle}</div>";
				$retour .= "<div class='flexiia'></div>";
				$retour .= "<div class='lar-lsov-ls-tinfo flexooa'>{$level['title']}</div>";
				$retour .= "<div class='lar-lsov-ls-tinfo flexooa'>{$level['points']}</div>";
				if($level['passed']==true)
					$retour .= "<div class='lar-lsov-ls-passed flexooa lws-icon-checkmark'></div>";
				$retour .= "</div>";
				foreach($level['rewards'] as $reward){
					$libelle = '<b>' . $reward['title'] . '</b> - ' . $reward['desc'];
					$retour .= "<div class='lar-lsov-ls-table-line flrow'><div class='flexiia'>{$libelle}</div></div>";
				}
				$retour .= "</div>";
			}
		}
		return $retour;
	}


}
