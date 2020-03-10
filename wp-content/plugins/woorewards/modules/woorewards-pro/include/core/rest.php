<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Rest API routes */
class Rest
{
	const PREFIX = 'woorewards/';
	const VERSION = 'v1';

	static function registerRoutes()
	{
		if( !empty(\get_option('lws_woorewards_rest_api_enabled', '')) )
		{
			\add_action('rest_api_init', function(){
				new self();
			});

			if( !empty(\get_option('lws_woorewards_rest_api_wc_auth', 'on')) )
			{
				\add_filter('woocommerce_rest_is_request_to_rest_api', function($isThirdParty){
					if( !$isThirdParty )
					{
						if( empty($_SERVER['REQUEST_URI']) )
							return false;
						$requestURI = \esc_url_raw(\wp_unslash( $_SERVER['REQUEST_URI']));
						$restPrefix = \trailingslashit(\rest_get_url_prefix());
						$isThirdParty = (false !== strpos($requestURI, $restPrefix.static::PREFIX));
					}
					return $isThirdParty;
				});
			}
		}
	}

	static function getNamespace()
	{
		return static::PREFIX . static::VERSION;
	}

	/** register rest api endpoints about pools @see \get_rest_url() */
	protected function __construct()
	{
		// all pools
		\register_rest_route(
			self::getNamespace(),
			'/pools',
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'getPools'),
				'permission_callback' => array($this, 'permissionGeneral'),
			)
		);

		// one pool
		\register_rest_route(
			self::getNamespace(),
			'/pools/(?P<id>[a-zA-Z0-9()_-]+)',
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'getPool'),
				'permission_callback' => array($this, 'permissionGeneral'),
				'args'     => array(
					'id' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return !empty($param);
						}
					),
				)
			)
		);

		// all events of a pool
		\register_rest_route(
			self::getNamespace(),
			'/pools/(?P<id>[a-zA-Z0-9()_-]+)/methods',
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'getEvents'),
				'permission_callback' => array($this, 'permissionGeneral'),
				'args'     => array(
					'id' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return !empty($param);
						}
					),
				)
			)
		);

		// one event
		\register_rest_route(
			self::getNamespace(),
			'/pools/(?P<id>[a-zA-Z0-9()_-]+)/methods/(?P<method>[0-9]+)',
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'getEvents'),
				'permission_callback' => array($this, 'permissionGeneral'),
				'args'     => array(
					'id' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return !empty($param);
						}
					),
					'method' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return is_numeric($param) && $param > 0;
						}
					),
				)
			)
		);

		// all unlockables of a pool
		\register_rest_route(
			self::getNamespace(),
			'/pools/(?P<id>[a-zA-Z0-9()_-]+)/rewards',
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'getUnlockables'),
				'permission_callback' => array($this, 'permissionGeneral'),
				'args'     => array(
					'id' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return !empty($param);
						}
					),
				)
			)
		);

		// one unlockable
		\register_rest_route(
			self::getNamespace(),
			'/pools/(?P<id>[a-zA-Z0-9()_-]+)/rewards/(?P<reward>[0-9]+)',
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'getUnlockables'),
				'permission_callback' => array($this, 'permissionGeneral'),
				'args'     => array(
					'id' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return !empty($param);
						}
					),
					'reward' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return is_numeric($param) && $param > 0;
						}
					),
				)
			)
		);

		// read points of a user
		\register_rest_route(
			self::getNamespace(),
			'/points/(?P<email>[^\@\s\/]+\@[^\@\s\/]+)',
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'getUserPoints'),
				'permission_callback' => array($this, 'permissionRead'),
				'args'     => array(
					'email' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return \is_email($param);
						},
					),
				)
			)
		);

		// read pool points of a user
		\register_rest_route(
			self::getNamespace(),
			'/points/(?P<email>[^\@\s\/]+\@[^\@\s\/]+)/(?P<id>[a-zA-Z0-9()_-]+)',
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'getUserPoints'),
				'permission_callback' => array($this, 'permissionRead'),
				'args'     => array(
					'email' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return \is_email($param);
						},
					),
					'id' => array(
						'default' => '',
						'validate_callback' => function($param, $request, $key) {
							return true;
						}
					),
				)
			)
		);

		// add points to a user for a pool
		\register_rest_route(
			self::getNamespace(),
			'/points/(?P<email>[^\@\s\/]+\@[^\@\s\/]+)/(?P<id>[a-zA-Z0-9()_-]+)/(?P<add>\d+)',
			array(
				'methods'  => 'PUT',
				'callback' => array($this, 'addUserPoints'),
				'permission_callback' => array($this, 'permissionWrite'),
				'args'     => array(
					'email' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return \is_email($param);
						}
					),
					'id' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return !empty($param);
						}
					),
					'add' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return is_numeric($param) && $param >= 0;
						}
					),
				)
			)
		);

		// try unlock a reward for a user in a pool
		\register_rest_route(
			self::getNamespace(),
			'/pools/(?P<id>[a-zA-Z0-9()_-]+)/rewards/(?P<reward>[0-9]+)/unlock/(?P<email>[^\@\s\/]+\@[^\@\s\/]+)',
			array(
				'methods'  => 'PUT',
				'callback' => array($this, 'unlockReward'),
				'permission_callback' => array($this, 'permissionWrite'),
				'args'     => array(
					'id' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return !empty($param);
						}
					),
					'reward' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return is_numeric($param) && $param > 0;
						}
					),
					'email' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {
							return \is_email($param);
						}
					),
				)
			)
		);
	}

	function permissionGeneral()
	{
		if( !($userId = \get_current_user_id()) )
			return false;
		$allowed = \get_option('lws_woorewards_rest_api_user_info', array());
		return is_array($allowed) && in_array($userId, $allowed);
	}

	function permissionRead()
	{
		if( !($userId = \get_current_user_id()) )
			return false;
		$allowed = \get_option('lws_woorewards_rest_api_user_read', array());
		return is_array($allowed) && in_array($userId, $allowed);
	}

	function permissionWrite()
	{
		if( !($userId = \get_current_user_id()) )
			return false;
		$allowed = \get_option('lws_woorewards_rest_api_user_write', array());
		return is_array($allowed) && in_array($userId, $allowed);
	}

	function getUserPoints($data)
	{
		$user = \get_user_by('email', $data['email']);
		if( !$user || !$user->ID )
			return new \WP_Error('no_user', __('Unknown User', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));

		$points = array();
		if( !isset($data['id']) || empty($data['id']) )
		{
			foreach( \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('deep'=>false))->asArray() as $pool )
			{
				$points[] = array(
					'id' => $pool->getName(),
					'points' => $pool->getStackId(),
					'value'  => $pool->getPoints($user->ID),
				);
			}
		}
		else
		{
			$pool = $this->getThePool($data);
			if( !$pool )
				return new \WP_Error('no_pool', __('Unknown Loyalty System', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));

			$points[] = array(
				'id' => $pool->getName(),
				'points' => $pool->getStackId(),
				'value'  => $pool->getPoints($user->ID),
			);
		}
		return $points;
	}

	function addUserPoints($data)
	{
		$user = \get_user_by('email', $data['email']);
		if( !$user || !$user->ID )
			return new \WP_Error('no_user', __('Unknown User', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));

		$pool = $this->getThePool($data);
		if( !$pool )
			return new \WP_Error('no_pool', __('Unknown Loyalty System', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));

		$pool->addPoints($user->ID, intval($data['add']), \lws_get_option('lws_woorewards_external_reason', __("External action", LWS_WOOREWARDS_PRO_DOMAIN)));
		$unlocked = $pool->tryUnlock($user->ID);
		$points = array();
		$points[] = array(
			'id' => $pool->getName(),
			'points' => $pool->getStackId(),
			'value'  => $pool->getPoints($user->ID),
			'rewards' => $unlocked,
		);
		return $points;
	}

	/** pools endpoint @return all pools */
	function getPools($data)
	{
		$pools = array();
		foreach( \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('deep'=>false))->asArray() as $pool )
			$pools[] = $pool->getData();
		return $pools;
	}

	/** pool endpoint */
	function getPool($data)
	{
		$pool = $this->getThePool($data);
		if( !$pool )
			return new \WP_Error('no_pool', __('Unknown Loyalty System', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));
		return $pool->getData();
	}

	/** get the pool */
	function getThePool($data, $key='id', $deep=false)
	{
		$reference = $data[$key];
		$pool = \LWS_WooRewards_Pro::getLoadedPools()->find($reference);
		if( $pool )
			return $pool;

		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('name'=>$reference, 'deep'=>$deep))->last();
		if( $pool )
			return $pool;

		return false;
	}

	/** try unlock a reward for a given user */
	function unlockReward($data)
	{
		$pool = $this->getThePool($data, 'id', true);
		if( !$pool )
			return new \WP_Error('no_pool', __('Unknown Loyalty System', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));

		$user = \get_user_by('email', $data['email']);
		if( !$user || !$user->ID )
			return new \WP_Error('no_user', __('Unknown User', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));

		foreach( $pool->getUnlockables()->asArray() as $item )
		{
			if( $data['reward'] == $item->getId() )
			{
				if( $pool->unlock($user, $item) )
				{
					return array(
						'points' => array(
							'id' => $pool->getName(),
							'points' => $pool->getStackId(),
							'value'  => $pool->getPoints($user->ID),
							'rewards' => 1,
						),
						'reward' => array_merge(array(
							'id' => $item->getId(),
							'cost' => $item->getCost(),
							'title' => $item->getTitle(),
							'description' => html_entity_decode(\wp_kses($item->getCustomDescription(), array())),
						), $item->getData()),
					);
				}
				else if( !$pool->isBuyable() ) // fail, pool passed away
					return new \WP_Error('reward', __("The requested reward cannot be unlocked. The loyalty system has expired.", LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 410));
				else
					return new \WP_Error('points', __("The requested reward cannot be unlocked. The user does not fulfill the conditions.", LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 409));
			}
		};
		return new \WP_Error('no_reward', __('Unknown Reward', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));
	}

	/** pool unlockables **/
	function getUnlockables($data)
	{
		$pool = $this->getThePool($data, 'id', true);
		if( !$pool )
			return new \WP_Error('no_pool', __('Unknown Loyalty System', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));

		if( !isset($data['reward']) || empty($data['reward']) )
		{
			$children = array();
			foreach( $pool->getUnlockables()->sort()->asArray() as $item )
			{
				$children[] = array_merge(array(
					'id' => $item->getId(),
					'cost' => $item->getCost(),
					'title' => $item->getTitle(),
					'description' => html_entity_decode(\wp_kses($item->getCustomDescription(), array())),
				), $item->getData());
			};
			return $children;
		}
		else
		{
			foreach( $pool->getUnlockables()->asArray() as $item )
			{
				if( $data['reward'] == $item->getId() )
				{
					return array_merge(array(
						'id' => $item->getId(),
						'cost' => $item->getCost(),
						'title' => $item->getTitle(),
						'description' => html_entity_decode(\wp_kses($item->getCustomDescription(), array())),
					), $item->getData());
				}
			};
			return new \WP_Error('no_reward', __('Unknown Reward', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));
		}
	}

	/** pool events **/
	function getEvents($data)
	{
		$pool = $this->getThePool($data, 'id', true);
		if( !$pool )
			return new \WP_Error('no_pool', __('Unknown Loyalty System', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));

		if( !isset($data['method']) || empty($data['method']) )
		{
			$children = array();
			foreach( $pool->getEvents()->sort()->asArray() as $item)
			{
				$children[] = array_merge(array(
					'id' => $item->getId(),
					'gain' => $item->getMultiplier(),
					'title' => $item->getTitle(),
					'description' => $item->getDescription(),
				), $item->getData());
			};
			return $children;
		}
		else
		{
			foreach( $pool->getEvents()->asArray() as $item)
			{
				if( $data['method'] == $item->getId() )
				{
					return array_merge(array(
						'id' => $item->getId(),
						'gain' => $item->getMultiplier(),
						'title' => $item->getTitle(),
						'description' => $item->getDescription(),
					), $item->getData());
				}
			}
			return new \WP_Error('no_method', __('Unknown Method', LWS_WOOREWARDS_PRO_DOMAIN), array('status' => 404));
		}
	}
}
