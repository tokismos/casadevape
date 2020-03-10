<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/widget.php';

/** Provide a widget to let display rewards.
 * Can be used as a Widget, a Shortcode [lws_rewards] or a Guttenberg block (soon).
 * Rewards can be filtered by pool
 * For a looged in user, we can filter only the unlockable ones. */
class RewardsWidget extends \LWS\WOOREWARDS\PRO\Ui\Widgets\Widget
{
	public static function install()
	{
		self::register(\get_class());
		$me = new self(false);
		\add_shortcode('lws_rewards', array($me, 'rewards')); // backward compatibility
		\add_shortcode('lws_loyalties', array($me, 'shortcode')); // backward compatibility
		\add_shortcode('wr_show_rewards', array($me, 'shortcode'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'rewards_template', array($me, 'stygenRewards'));
		\add_filter('lws_adminpanel_stygen_content_get_'.'loyalties_template', array($me, 'stygenLoyalties'));

		if( function_exists('register_block_type') )
			add_action( 'init', array(\get_class(), 'gutenberg') );
	}

	static function gutenberg()
	{
		// gutenberg block must be registered in javascript too and define client-side render and form
		$guid = 'woorewards-gutenberg-rewardswidget';
		\wp_register_script(
			$guid,
			LWS_WOOREWARDS_PRO_JS.'/gutenberg/rewardswidget.js',
			array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n', 'woorewards-gutenberg', 'jquery'),
			LWS_WOOREWARDS_PRO_VERSION
    );
    \wp_localize_script($guid, 'lws_wr_rewardlist', array(
			'stygen_href' => \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.settings', 'tab'=>'sty_widgets'), \admin_url('admin.php#lws_group_targetable_rewards')),
			'label' => __("Reward List", LWS_WOOREWARDS_PRO_DOMAIN)
		));

		\register_block_type('woorewards/rewardlist', array(
			'editor_script'   => $guid,
			'attributes'      => array( // shortcode attributes
				'pool'    => array('type'=>'string', 'default'=>\get_option('lws_wr_default_pool_name', 'default')),
				'granted' => array('type'=>'string', 'default'=>'all')
			),
			'render_callback' => function($atts=array(), $content=''){
				return "[lws_loyalties pool='{$atts['pool']}' granted='{$atts['granted']}']";
			}
		));
	}

	function stygenRewards($snippet)
	{
		$this->stygen = true;
		$opts = array(
			'type'          => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			'title'         => __("Example loyalty system", LWS_WOOREWARDS_PRO_DOMAIN),
			'point_timeout' => '6M'
		);
		$start = \date_create()->sub(new \DateInterval('P7D'));
		$opts['period_start'] = $start->format('Y-m-d');
		$opts['period_mid']   = $start->add(new \DateInterval('P1M'))->format('Y-m-d');
		$opts['period_end']   = $start->add(new \DateInterval('P1M'))->format('Y-m-d');
		$pool = $this->getExamplePool($opts);

		$snippet = $this->getContent($pool, array('granted'=>'all'), __("An error occured with that example.", LWS_WOOREWARDS_PRO_DOMAIN));
		unset($this->stygen);
		return $snippet;
	}

	function stygenLoyalties($snippet)
	{
		$this->stygen = true;
		$opts = array(
			'type'         => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
			'title'        => __("Example levelling system", LWS_WOOREWARDS_PRO_DOMAIN)
		);
		$start = \date_create()->sub(new \DateInterval('P4D'));
		$opts['period_start'] = $start->format('Y-m-d');
		$opts['period_end']   = $start->add(new \DateInterval('P72D'))->format('Y-m-d');
		$pool = $this->getExamplePool($opts);

		$snippet = $this->getContent($pool, array('granted'=>'all'), __("An error occured with that example.", LWS_WOOREWARDS_PRO_DOMAIN));
		unset($this->stygen);
		return $snippet;
	}

	protected function getExamplePool($options=array())
	{
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('demo')->last();
		$pool->setOptions($options);
		$examples = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create()->byCategory(false, array($pool->getOption('type')));
		$cost = $min = random_int(0, 128);
		$index = $pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING ? 0 : -1;
		$examples->apply(function($item)use(&$cost, &$pool, &$index){
			if( \method_exists($item, 'setTestValues') )
				$item->setTestValues();
			$item->setTitle($item->getTitle() . __(" (EXAMPLE)", LWS_WOOREWARDS_PRO_DOMAIN));
			if( $index < 0 || ($index % 2) == 0 )
				$cost += 10;
			if( $index >= 0 )
				$item->setGroupedTitle(sprintf(__("Example level %d", LWS_WOOREWARDS_PRO_DOMAIN), ++$index));
			$pool->addUnlockable($item, $cost);
		});
		$this->demoPts = $min + 42;
		if( $examples->count() > 1 )
			$this->demoPts = $cost - 8;
		return $pool;
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_rewardlistwidget',
				__("WooRewards Reward list", LWS_WOOREWARDS_PRO_DOMAIN),
				array(
					'description' => __("Display the rewards awaiting for your customers.", LWS_WOOREWARDS_PRO_DOMAIN)
				)
			);
		}
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		$atts = array_merge($instance, array('no_title'=>true));
		$pool = $this->getPool($atts);
		if( !empty($pool) /*&& (\is_admin() || $pool->userCan())*/ )
		{
			echo $args['before_widget'];
			echo $args['before_title'];
			echo \apply_filters('widget_title', empty($instance['title']) ? _x("Rewards", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN) : $instance['title'], $instance);
			echo $args['after_title'];
			echo $this->getContent($pool, $atts);
			echo $args['after_widget'];
		}
	}

	/** ensure all required fields exist. */
	function update( $new_instance, $old_instance )
	{
		return \wp_parse_args(
			array_merge($old_instance, $new_instance),
			$this->defaultArgs()
		);
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());

		// title
		$this->eFormFieldText(
			$this->get_field_id('title'),
			__("Title", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr(_x("Rewards", "frontend widget", LWS_WOOREWARDS_PRO_DOMAIN))
		);

		$options = array();
		foreach(\LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('deep' => false))->asArray() as $pool)
			$options[$pool->getId()] = $pool->getOption('display_title');

		// pool
		$this->eFormFieldSelect(
			$this->get_field_id('pool'),
			__("Select Loyalty System", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('pool'),
			$options,
			$instance['pool']
		);

		// behavior
		$this->eFormFieldSelect(
			$this->get_field_id('granted'),
			__("Filter rewards", LWS_WOOREWARDS_PRO_DOMAIN),
			$this->get_field_name('granted'),
			array(
				'all'      => __("All", LWS_WOOREWARDS_PRO_DOMAIN),
				'only'     => __("Buyable only (require a logged customer)", LWS_WOOREWARDS_PRO_DOMAIN),
				'excluded' => __("Exclude buyable (same as 'all' if no one logged in)", LWS_WOOREWARDS_PRO_DOMAIN),
				'shared'   => __("All Rewards, including shared systems", LWS_WOOREWARDS_PRO_DOMAIN)
			),
			$instance['granted']
		);
	}

	protected function defaultArgs()
	{
		return array(
			'no_title' => false,
			'title'    => '',
			'pool'     => '',
			'granted'  => 'all'
		);
	}

	/** @brief shortcode [lws_rewards]
	 *	@see shortcode
	 *	Dedicated to Standard System rewards (default pool), available unlockable only.
	 *	Force atts  */
	public function rewards($atts=array(), $content='')
	{
		if( !is_array($atts) )
			$atts = array();
		$atts['pool'] = \get_option('lws_wr_default_pool_name', 'default');
		$atts['granted'] = 'only';
		return $this->shortcode($atts, $content);
	}

	/** @brief shortcode [lws_rewards]
	 *	@see shortcode
	 *	Dedicated to Standard System rewards (default pool), available unlockable only.
	 *	Force atts  */
	public function levellings($atts=array(), $content='')
	{
		if( !is_array($atts) )
			$atts = array();
		$atts['pool'] = '';
		$atts['granted'] = 'all';
		return $this->shortcode($atts, $content);
	}

	/** @param $atts (in/out array) */
	private function getPool(&$atts)
	{
		$pool = false;
		if( !is_array($atts) )
			$atts = array();
		if( !isset($atts['pool']) )
			$atts['pool'] = '';

		if( empty($atts['pool']) ) // v2 compatibilty: no pool specified means 'levelling' prefab
		{
			$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'     => 'wre_pool_prefab',
						'value'   => 'yes', // This cannot be empty because of a bug in WordPress
						'compare' => 'LIKE'
					),
					array(
						'key'     => 'wre_pool_type',
						'value'   => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
						'compare' => 'LIKE'
					)
				)
			))->last();
		}
		else
		{
			$pool = \LWS_WooRewards_Pro::getLoadedPools()->find($atts['pool']);
			if( empty($pool) && is_numeric($atts['pool']) && $atts['pool'] > 0 )
				$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p'=>$atts['pool']))->last();
			if( empty($pool) )
				$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('name'=>$atts['pool']))->last();
		}
		return $pool;
	}

	/** @brief shortcode [loyalties]
	 *	Display a stylable reward list presentation.
	 *	usage: All attributes are optionnal
	 *	@code
	 *	[lws_rewards pool="<pool_name|pool_id>" title="<my Own Title>" granted="<only|excluded|all>"]
	 *	@endcode
	 *
	 *	*	v2 compatibilty: no pool specified means 'levelling' prefab.
	 *	*	stygen snippet is different for standard and levelling type since
	 *		levelling are group by threshold but standard have redeem button. */
	public function shortcode($atts=array(), $content='')
	{
		$pool = $this->getPool($atts);
		if( !empty($pool) /*&& (\is_admin() || $pool->userCan())*/ )
			return $this->getContent($pool, $atts, $content);
		else
			return $content;
	}

	public function getContent($pool, $atts=array(), $content='')
	{
		$atts = \shortcode_atts($this->defaultArgs(), $atts, 'lws_rewards');

		$pools = array($pool); // prepare in case of 'shared'
		$realContent = '';
		/**if we're in shared "granted", we need to get all pools with same pool pointts */
		if($atts['granted'] == 'shared')
		{
			$searchedStackId = $pool->getStackId();
			$excludeOurSelf = $pool->getId();
			foreach(\LWS_WooRewards_Pro::getLoadedPools()->asArray() as $newPool)
			{
				if( $newPool->getId() != $excludeOurSelf && $newPool->getStackId() == $searchedStackId )
					$pools[] = $newPool;
			}
		}
		$globalList = '';
		$mainTitle = '';
		$mainType = '';
		foreach($pools as $pool)
		{
			$title = '';
			$type = $pool->getOption('type');
			$mainType = empty($mainType) ? $type : $mainType;
			if( !boolval($atts['no_title']) )
			{
				$title = "<div class='lwss_selectable lws-rl-title {$type}' data-type='Loyalty system name'>";
				$title .= empty($atts['title']) ? $pool->getOption('display_title') : $atts['title'];
				$title .= "</div>";
			}
			$mainTitle = empty($mainTitle) ? $title : $mainTitle;
			if($type != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
				$list = $this->getRewards($pool, $atts['granted']);
			else
				$list = $this->getLoyalties($pool, $atts['granted']);
			$globalList .= $list;
			if(!empty($list))
				$realContent .= "<div class='lwss_selectable lws-main-conteneur {$type}' data-type='Main Border'>{$title}<div class='lwss_selectable lws-rl-conteneur {$type}' data-type='Rewards List'>{$list}</div></div>";
		}
		if( empty($globalList) )
		{
			$list = $content;
			$realContent = "<div class='lwss_selectable lws-main-conteneur {$mainType}' data-type='Main Border'>{$mainTitle}<div class='lwss_selectable lws-rl-conteneur {$mainType}' data-type='Rewards List'>{$list}</div></div>";
		}
		return $realContent;
	}

	/** Unlockable list with Redeem buttons.
	 * Split buyable or not. */
	protected function getRewards($pool, $granted)
	{
		$oldWay = empty(\get_option('lws_woorewards_rewards_use_grid', 'on'));
		$style = $oldWay ? 'rewards' : 'gridrewards';

		\wp_enqueue_style(
			'woorewards-rewards',
			LWS_WOOREWARDS_PRO_URL."/css/templates/{$style}.css?stygen=lws_woorewards_rewards_template",
			array(),
			LWS_WOOREWARDS_PRO_VERSION
		);

		$threshold = 0;
		$user = \wp_get_current_user();
		if( $user && $user->ID )
			$threshold = $pool->getPoints($user->ID);
		if( isset($this->stygen) && $this->stygen )
			$threshold = isset($this->demoPts) ? $this->demoPts : random_int(36, 42);

		$content = '';
		foreach( $pool->getUnlockables()->asArray() as $item )
		{
			if( !($purchasable = $item->isPurchasable($threshold, $user->ID)) && $granted == 'only' )
				continue;
			if( $granted == 'excluded' && $item->getCost() <= $threshold )
				continue;
			if( !$item->isPurchasable() )
				continue;

			if( empty($img = $item->getThumbnailImage()) && isset($this->stygen) )
				$img = "<div class='lws-reward-thumbnail lws-icon-image'></div>";

			if( $oldWay )
			{
				$content .= "<tr><td class='lwss_selectable lws-rewards-cell-img' data-type='Rewards Image'>$img";
				$content .= "</td><td class='lwss_selectable lws-rewards-cell-left' data-type='Rewards Cell' width='100%'>";
			}
			else
			{
				$content .= "<div class='lwss_selectable lws-rewards-reward' data-type='Reward Grid'>";
				$content .= "<div class='lwss_selectable lws-rewards-cell-img' data-type='Rewards Image'>$img</div>";
			}

			// unlocable details
			$content .= "<div class='lwss_selectable lws-reward-name' data-type='Reward Name'>".$item->getTitle()."</div>";
			$content .= "<div class='lwss_selectable lws-reward-desc' data-type='Reward Description'>".$item->getCustomDescription()."</div>"; // purpose

			if( $user && $user->ID && $item->noMorePurchase($user->ID) )
			{
				$info = __("Already unlocked.", LWS_WOOREWARDS_PRO_DOMAIN);
				$content .= "<div class='lwss_selectable lws-reward-cost' data-type='Reward Cost'>{$info}</div>";
			}
			else if( empty($user) || empty($user->ID) || $threshold >= $item->getCost() )
			{
				$cost = sprintf(
					__("This reward is worth %s", LWS_WOOREWARDS_PRO_DOMAIN),
					\LWS_WooRewards::formatPointsWithSymbol($item->getCost('front'), $item->getPoolName())
				);
				$content .= "<div class='lwss_selectable lws-reward-cost' data-type='Reward Cost'>{$cost}</div>"; // cost
			}
			else
			{
				$cost = sprintf(
					__("This reward is worth %s, you need %s more", LWS_WOOREWARDS_PRO_DOMAIN),
					\LWS_WooRewards::formatPointsWithSymbol($item->getCost('front'), $item->getPoolName()),
					\LWS_WooRewards::formatPointsWithSymbol($item->getCost()-$threshold, $item->getPoolName())
				);
				$content .= "<div class='lwss_selectable lws-reward-more' data-type='Need More points'>{$cost}</div>"; // cost
			}

			if( $oldWay )
				$content .= "</td><td class='lwss_selectable lws-rewards-cell-right' data-type='Rewards Cell'>";
			else
				$content .= "<div class='lwss_selectable lws-rewards-cell-unlock' data-type='Unlock Container'>";

			// redeem button
			if( $purchasable )
			{
				$btn = __("Unlock", LWS_WOOREWARDS_PRO_DOMAIN);
				$href = esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs($this->getUrlTarget(isset($this->stygen)), $item, $user));
				$content .= "<a href='$href' class='lwss_selectable lws-reward-redeem' data-type='Unlock button'>{$btn}</a>";
			}
			else
			{
				$btn = _x("Locked", "redeem button need more points", LWS_WOOREWARDS_PRO_DOMAIN);
				$content .= "<div href='#' class='lwss_selectable lws-reward-redeem-not' data-type='Unlock Unavailable'>{$btn}</div>";
			}

			if( $oldWay )
				$content .= "</td></tr>";
			else
				$content .= "</div></div>";
		}

		if( !empty($content) )
		{
			if( $oldWay )
				$content = "<table class='lwss_selectable lws-sub-conteneur standard' data-type='Rewards Table'><tbody>$content</tbody></table>";
			else
				$content = "<div class='lwss_selectable lws-sub-conteneur standard' data-type='Rewards Grid'>$content</div>";
		}
		return $content;
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

	/** A grouped by cost display.
	 * Split already unlocked or not. */
	protected function getLoyalties($pool, $granted)
	{
		\wp_enqueue_style(
			'woorewards-loyalties',
			LWS_WOOREWARDS_PRO_URL.'/css/templates/loyalties.css?stygen=lws_woorewards_loyalties_template',
			array(),
			LWS_WOOREWARDS_PRO_VERSION
		);

		$done = array();
		if( !empty($user_id = \get_current_user_id()) )
			$done = \get_user_meta($user_id, 'lws-loyalty-done-steps', false);

		$first = true;
		$content = '';
		$cost = -9999;
		foreach( $pool->getUnlockables()->sort()->asArray() as $item )
		{
			$owned = in_array($item->getId(), $done);
			// Already unlocked or not. Since they are no redeem.
			if( $granted == 'only' && !$owned )
				continue;
			if( $granted == 'excluded' && $owned )
				continue;
			if( !$item->isPurchasable() )
				continue;

			if( isset($this->stygen) && $this->stygen && isset($this->demoPts) )
				$owned = $item->getCost() <= $this->demoPts;

			$css = $owned ? 'lws-reward-owned' : 'lws-reward-pending';
			$cssItem = ($owned || $item->noMorePurchase($user_id)) ? 'lws-reward-owned' : 'lws-reward-pending';
			$datatype = $owned ? 'Owned' : 'Pending';

			if( $item->getCost() != $cost )
			{
				if( !$first )
					$content .= "</div><div class='lwss_selectable lws-ly' data-type='Reward Bloc'>";
				$first = false;
				$cost = $item->getCost();
				$content .= "<div class='lwss_selectable lws-ly-title $css' data-type='$datatype Title Line'>";
				$content .= "<div class='lwss_selectable lws-ly-name' data-type='Loyalty Name'>".$item->getGroupedTitle('view')."</div>";
				$content .= "<div class='lwss_selectable lws-ly-points' data-type='Points'>".\LWS_WooRewards::formatPointsWithSymbol($item->getCost('front'), $item->getPoolName())."</div>";
				$content .= "</div>";
			}
			if( empty($img = $item->getThumbnailImage()) && isset($this->stygen) )
				$img = "<div class='lws-ly-thumbnail lws-icon-image'></div>";
			$content .= "<div class='lwss_selectable lws-ly-content $cssItem' data-type='$datatype Loyalty Reward'>";
			$content .= "<div class='lwss_selectable lws-ly-img' data-type='Reward Image'>$img</div><div class='lwss_selectable lws-ly-descr' data-type='Reward Descrition'>";
			$content .= "<div class='lwss_selectable lws-ly-rtitle' data-type='Reward Title'>".$item->getTitle()."</div>";
			$content .= "<div class='lwss_selectable lws-ly-rdetail' data-type='Reward Detail'>".$item->getCustomDescription()."</div>";
			$content .= "</div></div>";
		}

		if( !empty($content) )
			$content = "<div class='lwss_selectable lws-ly' data-type='Reward Bloc'>$content</div>";
		return $content;
	}

}

?>
