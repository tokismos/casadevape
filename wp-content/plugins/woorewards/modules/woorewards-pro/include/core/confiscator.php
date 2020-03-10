<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Reward Confiscation.
 * Unlockable previewsly attributed rewards to users.
 * That class helps to revoke that rewards.
 * Use case: levelling system points expiration. */
class Confiscator
{
	public $references = array();
	public $users = array();
	public $pool = false;

	function setByPool(\LWS\WOOREWARDS\Core\Pool $pool)
	{
		$this->pool = $pool;
		foreach( $pool->getUnlockables()->asArray() as $unlockable )
			$this->addRef($unlockable);
	}

	function addRef(\LWS\WOOREWARDS\Abstracts\Unlockable $unlockable)
	{
		$key = $unlockable->getId();
		$ref = array('id' => $unlockable->getId());
		if( \is_a($unlockable, '\LWS\WOOREWARDS\PRO\Unlockables\UserTitle') )
			$ref['title'] = $unlockable->getUserTitle();
		if( \is_a($unlockable, '\LWS\WOOREWARDS\PRO\Unlockables\Role') )
			$ref['role'] = $unlockable->getRoleId();

		$this->references[$key] = \apply_filters('lws_woorewards_conscator_reference_get', $ref, $unlockable, $this);
	}

	function revoke($userIds)
	{
		if( !is_array($userIds) )
			$userIds = array($userIds);
		$helpers = array(
			'rewards' => array_keys($this->references),
			'titles'  => implode("','", array_map('esc_sql', array_column($this->references, 'title', 'title'))),
			'roles'   => array_column($this->references, 'role', 'role'),
		);
		if( !empty($helpers['titles']) )
			$helpers['titles'] = "('{$helpers['titles']}')";
		$helpers = \apply_filters('lws_woorewards_conscator_helpers', $helpers, $this);

		foreach( $userIds as $userId )
		{
			$user = false;
			if( \is_a($userId, '\WP_User') )
			{
				$user = $userId;
				$userId = $user->ID;
			}
			$done = \get_user_meta($userId, 'lws-loyalty-done-steps', false);
			$done = array_intersect($done, $helpers['rewards']);

			if( !empty($done) && ($user || ($user = \get_user_by('ID', $userId))) )
			{
				$helpers['origins'] = "('" . implode("','", array_map('esc_sql', $done)) . "')";

				if( \apply_filters('lws_woorewards_core_pool_rewards_confiscate', true, $user, $done, $helpers, $this) )
				{
					$this->removeCoupons($user, $helpers);
					$this->removeBadges($user, $helpers);
					$this->removeTitles($user, $helpers);

					$this->forgetRedeems($user, $helpers);
				}
			}
		}

		\do_action('lws_woorewards_core_rewards_confiscated', $userIds, $helpers, $this);
		$this->warnAboutRoles();
	}

	protected function warnAboutRoles()
	{
		if( isset($this->usersWithoutRole) && $this->usersWithoutRole )
		{
			\wp_mail(
				\get_option('admin_email'),
				\get_bloginfo('name') . __(": Some users have no role", LWS_WOOREWARDS_PRO_DOMAIN),
				implode("\n\n", array(
					__("This message is generated by WooRewards because some users lost their points and levels due to inactivity.", LWS_WOOREWARDS_PRO_DOMAIN),
					__("Revoking level rewards results in removing a role from some users.", LWS_WOOREWARDS_PRO_DOMAIN),
					__("You should check that all users have a role again (at least Subscriber [or Customer if WooCommerce is installed]).", LWS_WOOREWARDS_PRO_DOMAIN),
				)),
				array('Content-Type: text/plain; charset=UTF-8')
			);
		}
	}

	/** To allow a user passing level again.
	 * @param $user (WP_User)
	 * @param $helpers (array) contains
	 * * 'origins' entry with a (string) mysql list to use with IN clause */
	function forgetRedeems($user, $helpers)
	{
		global $wpdb;
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key='lws-loyalty-done-steps' AND meta_value IN {$helpers['origins']}",
			$user->ID
		));
	}

	/** remove user role
	 * @param $user (WP_User)
	 * @param $helpers (array) contains
	 * * 'roles' (array) role keys */
	function removeRoles($user, $helpers)
	{
		if( !empty($helpers['roles']) )
		{
			\LWS_WooRewards_Pro::isRoleChangeLocked();

			foreach( $helpers['roles'] as $role )
			{
				if( in_array($role, $user->roles) )
				{
					$user->remove_role($role);
					if( empty($user->roles) )
					{
						$bkp = \get_user_meta($user->ID, 'lws_woorewards_user_role_backup', true);
						if( $bkp && is_array($bkp) )
						{
							foreach( array_diff($bkp, $helpers['roles']) as $restore )
								$user->add_role($restore);
						}
						if( empty($user->roles) )
							$this->usersWithoutRole = true;

						break;
					}
				}
			}

			\LWS_WooRewards_Pro::isRoleChangeLocked(true);
		}
	}

	/** remove title
	 * @param $user (WP_User)
	 * @param $helpers (array) contains
	 * * 'titles' entry with a (string) mysql list to use with IN clause */
	function removeTitles($user, $helpers)
	{
		if( !empty($helpers['titles']) )
		{
			global $wpdb;
			$wpdb->query($wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key='woorewards_special_title' AND meta_value IN {$helpers['titles']}",
				$user->ID
			));
		}
	}

	/** remove badges
	 * @param $user (WP_User)
	 * @param $helpers (array) contains a 'origins' entry with a (string) mysql list to use with IN clause */
	function removeBadges($user, $helpers)
	{
		global $wpdb;
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}lws_wr_userbadge WHERE user_id=%d AND origin IN {$helpers['origins']}",
			$user->ID
		));
	}

	/** remove coupons (inc. freeproduct, free shipping, variable discount)
	 * @param $user (WP_User)
	 * @param $helpers (array) contains a 'origins' entry with a (string) mysql list to use with IN clause */
	function removeCoupons($user, $helpers)
	{
		global $wpdb;
		if( !empty($user) && !empty($user->user_email) )
		{
			// trash shop_coupon (fix, percent, product) and CustomReward, all at once
			$trash = <<<EOT
UPDATE {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as o ON p.ID=o.post_id AND o.meta_key='reward_origin_id' AND o.meta_value IN {$helpers['origins']}
INNER JOIN {$wpdb->postmeta} as m ON p.ID=m.post_id AND m.meta_key='customer_email' AND m.meta_value=%s
SET p.post_status='trash'
EOT;
			$wpdb->query($wpdb->prepare($trash, serialize(array($user->user_email))));
		}
	}
}