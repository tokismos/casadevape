<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Sponsorship helper. */
class Sponsorship
{

	/* register hook to react about:
	 * * add sponsored (ajax).
	 * * sponsored register.
	 * * sponsored first order. */
	public function register()
	{
		\add_action('wp_ajax_lws_woorewards_add_sponsorship', array($this, 'request'));
		if( !empty(\get_option('lws_woorewards_sponsorship_allow_unlogged', '')) )
			\add_action('wp_ajax_nopriv_lws_woorewards_add_sponsorship', array($this, 'request'));
		\add_filter('wpml_user_language', array($this, 'guessSponsoredLanguage'), 10, 2);

		if( !empty(\get_option('lws_woorewards_event_enabled_sponsorship', 'on')) )
		{
			\add_action('user_register', array($this, 'onCustomerRegister'), 999999, 1 );

			$status = \apply_filters('lws_woorewards_order_events', array('processing', 'completed'));
			foreach (array_unique($status) as $s)
				\add_action('woocommerce_order_status_' . $s, array($this, 'onOrder') , 999999, 2);
		}
	}

	/** if email does not belong to a register user, see for a sponsored guy.
	 * Then look for sponsor language. */
	function guessSponsoredLanguage($lang, $email)
	{
		if( !\get_user_by('email', $email) )
		{
			if( $sponsor = $this->getSponsorFor($email) )
			{
				$l = get_user_meta($user->ID, 'icl_admin_language', true);
				if( $l )
					$lang = $l;
			}
		}
		return $lang;
	}

	protected function createReward($email, $sponsor=false)
	{
		$unlockable = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->load(array(
			'numberposts' => 1,
			'meta_query'  => array(
				array(
					'key'     => 'wre_sponsored_reward',
					'value'   => 'yes',
					'compare' => 'LIKE'
				)
			)
		))->last();

		if( empty($unlockable) )
			return array('sponsor' => $sponsor, 'type' => '', 'unlockable' => null, 'reward' => array());

		$user = new \WP_User(0);
		$user->user_email = $email;
		$reward = $unlockable->createReward($user);

		if( false === $reward )
			return array('sponsor' => $sponsor, 'type' => '', 'unlockable' => null, 'reward' => array());
		else
			return array('sponsor' => $sponsor, 'type' => $unlockable->getType(), 'unlockable' => $unlockable, 'reward' => $reward);
	}

	/** parse request then addRelationship, then send mail to sponsor.
	 * @return request result as json. */
	function request()
	{
		if( isset($_REQUEST['sponsored_email']) && isset($_REQUEST['sponsorship_nonce']) )
		{
			if( \wp_verify_nonce($_REQUEST['sponsorship_nonce'], 'lws_woorewards_sponsorship_email') )
			{
				$user = \wp_get_current_user();
				if( !$user || !$user->ID )
				{
					// find user by email
					if( isset($_REQUEST['sponsor_email']) && ($email = \sanitize_email($_REQUEST['sponsor_email'])) )
						$user = \get_user_by('email', $email);

					if( !$user || !$user->ID )
					{
						$redirect = \get_permalink(\get_option('lws_woorewards_sponsorhip_user_notfound', false));
						\wp_send_json(array(
							'succes'   => false,
							'error'    => __("Unknown user.", LWS_WOOREWARDS_PRO_DOMAIN),
							'redirect' => ($redirect ? $redirect : ''),
						));
					}
				}

				$result = $this->addRelationship($user, $_REQUEST['sponsored_email']);
				if( \is_wp_error($result) )
				{
					\wp_send_json(array(
						'succes' => false,
						'error'  => $result->get_error_message()
					));
				}
				else if( !$result )
				{
					\wp_send_json(array(
						'succes' => false,
						'error'  => __("Unexpected error. Please retry later.", LWS_WOOREWARDS_PRO_DOMAIN)
					));
				}
				else
				{
					\wp_send_json(array(
						'succes'  => true,
						'message' => \lws_get_option('lws_wooreward_sponsorship_success',__("A mail has been sent to your friend about us.", LWS_WOOREWARDS_PRO_DOMAIN))
					));
				}
			}
		}
	}

	/** new customer, look for sponsor. */
	function onCustomerRegister($user_id)
	{
		if( empty($user = \get_user_by('ID', $user_id)) )
			return;
		if( empty($email = trim($user->user_email)) )
			return;
		if( \get_user_meta($user->ID, 'lws_woorewards_at_registration_sponsorship', true) == 'done' )
			return;
		\update_user_meta($user->ID, 'lws_woorewards_at_registration_sponsorship', 'done');

		// is customer sponsored and by who?
		if( !empty($sponsor = $this->getSponsorFor($email)) )
			\do_action('lws_woorewards_sponsored_registration', $sponsor, $user);
	}

	/** new order, look for sponsor. */
	function onOrder($order_id, $order)
	{
		$userEmail = $order->get_billing_email();
		$userId = $order->get_customer_id();
		if( empty($userEmail) && !$userId )
			return;

		// order already done?
		if( \get_post_meta($order_id, 'lws_woorewards_event_points_sponsorship', true) == 'done' )
			return;
		\update_post_meta($order_id, 'lws_woorewards_event_points_sponsorship', 'done');

		$user = false;
		if( $userId )
		{
			$user = \get_user_by('ID', $userId);
			if( $user && $user->ID && $user->user_email /*&& empty($userEmail)*/ )
				$userEmail = $user->user_email;
		}

		if( empty($userEmail) )
			return;

		if( !$user )
			$user = new \WP_User();
		if( !$user->ID )
			$user->user_email = $userEmail;

		// is customer sponsored and by who?
		if( empty($sponsor = $this->getSponsorFor($userEmail)) )
			return;

		\do_action('lws_woorewards_sponsored_order', $sponsor, $user, $order);
	}

	/** @param $sponsored (string) email
	 *	@return (false|WP_User) */
	protected function getSponsorFor($sponsored)
	{
		global $wpdb;
		$sql ="SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='lws_wooreward_used_sponsorship' AND meta_value=%s";
		$sponsor_id = $wpdb->get_var($wpdb->prepare($sql, $sponsored));
		if( empty($sponsor_id) )
			return false;

		$sponsor = \get_user_by('ID', $sponsor_id);
		if( empty($sponsor) )
			error_log("Sponsorship defined for '$sponsored' but sponsor user cannot be found: $sponsor_id");
		return $sponsor;
	}

	/**	Bind sponsor and sponsored for later use.
	 *	Create a reward waiting for sponsored and send him a mail about it.
	 *	@param $sponsor (int|WP_User)
	 *	@param $sponsored (string) email (or several emails, comma or semicolon separated)
	 * @return (bool|WP_Error) */
	public function addRelationship($sponsor, $sponsored)
	{
		if( empty($sponsor) )
			return new \WP_Error('unauthorized', __("You must be logged in.", LWS_WOOREWARDS_PRO_DOMAIN));
		if( !is_a($sponsor, 'WP_User') && empty($sponsor = \get_user_by('ID', $sponsor)) )
			return new \WP_Error('unknown', __("Unknown user.", LWS_WOOREWARDS_PRO_DOMAIN));
		if( empty($sponsor->ID) )
			return new \WP_Error('unauthorized', __("You must be logged in.", LWS_WOOREWARDS_PRO_DOMAIN));
		if( empty(trim(str_replace(array(',', ';'), '', $sponsored))) )
			return new \WP_Error('bad-argument', __("Sponsored email is empty.", LWS_WOOREWARDS_PRO_DOMAIN));

		if( empty(\get_option('lws_woorewards_event_enabled_sponsorship', 'on')) )
			return new \WP_Error('disabled', __("Sponsorship has been temporary disabled.", LWS_WOOREWARDS_PRO_DOMAIN));

		if( !($max = $this->userCan($sponsor->ID)) )
			return new \WP_Error('locked', __("Maximum sponsorship reached.", LWS_WOOREWARDS_PRO_DOMAIN));

		$emails = \preg_split('/\s*[;,]\s*/', $sponsored, -1, PREG_SPLIT_NO_EMPTY);
		if( !$emails )
			return new \WP_Error('split', __("An error occured during sponsored emails reading.", LWS_WOOREWARDS_PRO_DOMAIN));

		foreach( $emails as $email )
		{
			if( \is_wp_error($email = $this->isEligible($email)) )
				return $email;
			if( $email == $sponsor->user_email )
				return new \WP_Error('forbidden', __("You cannot sponsor yourself.", LWS_WOOREWARDS_PRO_DOMAIN));
			if( $max !== true && --$max < 0 )
				return new \WP_Error('locked', __("Some emails was omitted, maximum sponsorship reached.", LWS_WOOREWARDS_PRO_DOMAIN));

			\add_user_meta($sponsor->ID, 'lws_wooreward_used_sponsorship', $email, false);
			\do_action('lws_woorewards_sponsorship_done', $sponsor, $email);

			\do_action('lws_mail_send', $email, 'wr_sponsored', $this->createReward($email, $sponsor));
		}
		return true;
	}

	/** user can be sponsor and still have room for it.
	 * @param $sponsor (int|WP_User)
	 * @return (bool|int) true if user can and no sponsorship limit.
	 * else return number of email the user can sponsored. */
	public function userCan($sponsor, $requestedCount=1)
	{
		if( empty($sponsor) )
			return false;
		if( empty($user_id = is_a($sponsor, 'WP_User') ? $sponsor->ID : intval($sponsor)) )
			return false;

		$max_sponsorship = intval(\get_option('lws_wooreward_max_sponsorship_count', '0'));
		if( empty($max_sponsorship) )
			return true;

		$used = count(\get_user_meta($user_id, 'lws_wooreward_used_sponsorship', false));
		return max(0, $max_sponsorship - $used);
	}

	/** never sponsored or registered customer.
	 * @param $sponsored (string) email
	 * @return (WP_Error|string) the cleaned email if ok. */
	public function isEligible($sponsored)
	{
		$email = trim($sponsored);
		if( !\is_email($email) )
			return new \WP_Error('bad-format', sprintf(__("'%s' Email address is not valid.", LWS_WOOREWARDS_PRO_DOMAIN), $email));

		global $wpdb;
		if( !empty($wpdb->get_results($wpdb->prepare("SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_key='lws_wooreward_used_sponsorship' AND meta_value=%s LIMIT 0, 1", $email))) > 0 )
			return new \WP_Error('already-sponsored', sprintf(__("%s is already sponsored.", LWS_WOOREWARDS_PRO_DOMAIN), $email));

		if( self::getOrderCountByEMail($email) > 0 )
			return new \WP_Error('already-customer', sprintf(__("%s is already an active customer.", LWS_WOOREWARDS_PRO_DOMAIN), $email));

		return $email;
	}

	static function getOrderCountByEMail($email, $excludedOrderId=false)
	{
		global $wpdb;

		$args = array($email);
		$billing = "SELECT COUNT(p.ID) FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} AS e ON e.post_id=p.ID AND e.meta_key='_billing_email' AND e.meta_value=%s
WHERE p.post_type='shop_order'";
		if( !empty($excludedOrderId) )
		{
			$billing .= " AND p.ID<>%d";
			$args[] = $excludedOrderId;
		}

		$args[] = $email;
		$customer = "SELECT COUNT(p.ID) FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} AS c ON c.post_id=p.ID AND c.meta_key='_customer_user'
INNER JOIN {$wpdb->users} as u ON c.meta_value=u.ID AND u.user_email=%s
WHERE p.post_type='shop_order'";
		if( !empty($excludedOrderId) )
		{
			$customer .= " AND p.ID<>%d";
			$args[] = $excludedOrderId;
		}

		$sql = "SELECT ($billing) as billing, ($customer) as customer";
		$counts = $wpdb->get_row($wpdb->prepare($sql, $args), ARRAY_N);
		if( empty($counts) )
			return 0;
		else
			return (intval($counts[0]) + intval($counts[1]));
	}

	static function getOrderCountById($userId, $exceptOrderId=false)
	{
		$args = array($userId);
		global $wpdb;

		$sql = "SELECT COUNT(ID) FROM {$wpdb->posts}
INNER JOIN {$wpdb->postmeta} ON ID=post_id AND meta_key='_customer_user' AND meta_value=%d
WHERE post_type='shop_order'";

		if( !empty($exceptOrderId) )
		{
			$args[] = $exceptOrderId;
			$sql .= " AND ID<>%d";
		}

		return $wpdb->get_var($wpdb->prepare($sql, $args));
	}

}

?>