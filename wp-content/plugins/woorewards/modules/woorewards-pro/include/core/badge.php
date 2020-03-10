<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage badge item like a post. */
class Badge
{
	const POST_TYPE = 'lws_badge';
	const WPML_KIND = 'WooRewards Badge'; // first letter uppercase, else it will not work!
	protected $id = '';
	protected $title = '';
	protected $excerpt = '';
	protected $valid = false;
	protected $owners = array();

	/** For dev's sake, use wp_post for start.
	 * It has title, resume and image and that's all we need. */
	static function registerPostType()
	{
		$labels = array(
			'name'                  => _x("Badges", 'post type general name', LWS_WOOREWARDS_PRO_DOMAIN),
			'singular_name'         => _x("Badge", 'post type singular name', LWS_WOOREWARDS_PRO_DOMAIN),
			'add_new'               => _x("Add New", 'Episodes', LWS_WOOREWARDS_PRO_DOMAIN),
			'add_new_item'          => __("Add New Badge", LWS_WOOREWARDS_PRO_DOMAIN),
			'edit_item'             => __("Edit Badge", LWS_WOOREWARDS_PRO_DOMAIN),
			'new_item'              => __("New Badge", LWS_WOOREWARDS_PRO_DOMAIN),
			'all_items'             => __("All Badges", LWS_WOOREWARDS_PRO_DOMAIN),
			'view_item'             => __("View Badge", LWS_WOOREWARDS_PRO_DOMAIN),
			'search_items'          => __("Search Badges", LWS_WOOREWARDS_PRO_DOMAIN),
			'not_found'             => __("No Badge found", LWS_WOOREWARDS_PRO_DOMAIN),
			'not_found_in_trash'    => __("No Badges found in the Trash", LWS_WOOREWARDS_PRO_DOMAIN),
			'parent_item_colon'     => '',
			'menu_name'             => _x("Badges", 'menu name', LWS_WOOREWARDS_PRO_DOMAIN),
			'featured_image'        => __("Badge image", LWS_WOOREWARDS_PRO_DOMAIN),
			'set_featured_image'    => __("Set Badge image", LWS_WOOREWARDS_PRO_DOMAIN),
			'remove_featured_image' => __("Remove Badge image", LWS_WOOREWARDS_PRO_DOMAIN),
		);
		$args = array(
			'labels'        => $labels,
			'description'   => __("Congrat your customers with badges", LWS_WOOREWARDS_PRO_DOMAIN),
			'public'        => !empty(\get_option('lws_woorewards_manage_badge_enable', 'on')),
			'hierarchical'	=> false,
			'exclude_from_search' => true,
			'menu_position' => 58, // just after WooCommerce Products or WooRewards main menu
			'menu_icon'     => 'dashicons-awards',
			'supports'      => array('title', 'thumbnail', 'excerpt'),
			'taxonomies'    => array('category', 'post_tag'),
			'has_archive'   => false,
		);
		if( defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED )
			register_post_type(self::POST_TYPE, $args);
	}

	/** @param $refresh (bool, defult false) force a fresh loading from database.
	 * @return array of Badge instances */
	static function loadByUser($userId, $refresh=false)
	{
		static $badges = array();
		if( !isset($badges[$userId]) || $refresh )
		{
			$badges[$userId] = array();
			global $wpdb;
			$sql = <<<EOT
SELECT badge_id, post_title, post_excerpt, assign_date
FROM {$wpdb->posts}
INNER JOIN {$wpdb->prefix}lws_wr_userbadge ON badge_id=ID AND user_id=%d
WHERE post_type=%s
EOT;
			$results = $wpdb->get_results($wpdb->prepare($sql, $userId, self::POST_TYPE));

			if( is_array($results) )
			{
				foreach( $results as $result )
				{
					$badge = new self();
					$badge->setData($result->badge_id, $result->post_title, $result->post_excerpt);
					$badge->owners[$userId] = $result->assign_date;
					$badges[$userId][$badge->getId()] = $badge;
				}
			}
		}
		return $badges[$userId];
	}

	/** Return the rarity information of a badge */
	function getBadgeRarity()
	{
		$defaults = array(
			'100' => array(
				'percentage'=> 100,
				'rarity'	=> __("Common", LWS_WOOREWARDS_PRO_DOMAIN),
			),
			'50' => array(
				'percentage'=> 50,
				'rarity'	=> __("Uncommon", LWS_WOOREWARDS_PRO_DOMAIN),
			),
			'20' => array(
				'percentage'=> 20,
				'rarity'	=> __("Rare", LWS_WOOREWARDS_PRO_DOMAIN),
			),
			'10' => array(
				'percentage'=> 10,
				'rarity'	=> __("Epic", LWS_WOOREWARDS_PRO_DOMAIN),
			),
			'2' => array(
				'percentage'=> 2,
				'rarity'	=> __("Legendary", LWS_WOOREWARDS_PRO_DOMAIN),
			),
		);
		$raritys = \lws_get_option("lws_woorewards_rarity_levels", $defaults);
		$rarity_info = array();
		$users_count = \count_users();
		$users_total = $users_count['total_users'];
		$users_own = $this->ownerCount();
		$rarity_perc = round(($users_own / $users_total) *100,1);
		foreach($raritys as $rarity){
			if($rarity_perc <= intval($rarity['percentage']))
			$rarity_info['rarity'] = $rarity['rarity'];
		}
		$rarity_info['percentage']= $rarity_perc;
		return $rarity_info;
	}

	/** @param $refresh (bool, default false) force a fresh loading from database.
	 * @param $args @see WP_Query
	 * @return array of Badge instances */
	static function loadBy($args=array(), $refresh=false)
	{
		static $badges = array();
		$key = \md5(json_encode($args));
		if( !isset($badges[$key]) || $refresh )
		{
			$badges[$key] = array();

			$cache = false;
			if( isset($args['cache']) )
			{
				$cache = $args['cache'];
				unset($args['cache']);
			}
			$args = \wp_parse_args($args, array(
				'numberposts' => -1,
				'post_type' => self::POST_TYPE,
				'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
				'order' => 'DESC',
				'orderby' => 'ID'
			));
			if( $cache === true || $cache === false )
			{
				$args = array_merge($args, array(
					'update_post_meta_cache' => $cache,
					'update_post_term_cache' => $cache,
					'cache_results'  => $cache
				));
			}

			$posts = \get_posts($args);
			if( !is_array($posts) )
			{
				error_log("Fail to load badges, check posts for :".print_r($args, true));
			}
			else foreach( $posts as $post )
			{
				if( !\wp_is_post_revision($post) )
				{
					$badge = new self();
					if( $badge->fromPost($post)->isValid() )
						$badges[$key][$badge->getId()] = $badge;
				}
			}
		}
		return $badges[$key];
	}

	/** @return $this */
	function load($id)
	{
		$this->owners = array();
		$this->valid = false;
		if( $post = \get_post($id) )
			$this->fromPost($post);
		return $this;
	}

	function __construct($id=0, $load=true)
	{
		$this->id = \absint($id);
		if( $this->id && $load )
			$this->load($id);
	}

	/** Is loaded from database.
	 * @return bool */
	function isValid()
	{
		return $this->valid;
	}

	function getAchievementOptions($userId=false)
	{
		$options = array(
			'title'   => $this->getTitle(),
			'message' => $this->getMessage(),
			'image'   => $this->getThumbnailUrl(),
			'origin'  => array(self::POST_TYPE, $this->getId())
		);
		if( $userId )
			$options['user'] = $userId;
		return $options;
	}

	/** register to Achievement popup */
	function schedulePopup($userId)
	{
		\LWS_WooRewards::achievement($this->getAchievementOptions($userId));
	}

	/** Set ownership of this badge.
	 * A badge can only be assigned once per user.
	 * If the user already got that badge,
	 * the function only return false.
	 * @return (false|Badge) $this on success. */
	function assignTo($userId, $origin='')
	{
		if( !$this->ownedBy($userId) )
		{
			$this->owners[$userId] = \date_create();
			if( $this->getId() )
			{
				global $wpdb;
				$wpdb->insert(self::getLinkTable(), array(
					'user_id' => $userId,
					'badge_id' => $this->getId(),
					'origin' => $origin
				), array('%d', '%d', '%s'));
			}

			$this->schedulePopup($userId);
			\do_action('lws_wooreward_badge_assigned', $userId, $this);
			return $this;
		}
		return false;
	}

	/** Remove ownership of this badge.
	 * If the user does not have that badge,
	 * the function only return false.
	 * @return (false|Badge) $this on success. */
	function removeFrom($userId)
	{
		$this->owners[$userId] = false;

		global $wpdb;
		$deleted = $wpdb->delete(self::getLinkTable(), array(
			'user_id' => $userId,
			'badge_id' => $this->getId()
		), array('%d', '%d'));

		if( $deleted )
		{
			\do_action('lws_wooreward_badge_removed', $userId, $this);
			return $this;
		}
		return false;
	}

	/** @return false|datetime if owned, the assignation date. */
	function ownedBy($userId)
	{
		if( !isset($this->owners[$userId]) )
		{
			if( !$this->getId() )
				return false;

			global $wpdb;
			$d = $wpdb->get_var($wpdb->prepare(
				"SELECT MAX(assign_date) FROM {$wpdb->prefix}lws_wr_userbadge WHERE user_id=%d AND badge_id=%d",
				intval($userId),
				$this->getId()
			));
			$this->owners[$userId] = (empty($d) ? false : \date_create($d));
		}
		return $this->owners[$userId];
	}

	/** @return int */
	function ownerCount()
	{
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(user_id) FROM {$wpdb->prefix}lws_wr_userbadge WHERE badge_id=%d",
			$this->getId()
		));
	}

	/** @return int */
	static function countByUser($userId)
	{
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(badge_id) FROM {$wpdb->prefix}lws_wr_userbadge WHERE user_id=%d",
			$userId
		));
	}

	function getId()
	{
		return $this->id;
	}

	function getTitle()
	{
		$value = $this->title;
		if( $value && !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
			$value = \apply_filters('wpml_translate_string', $value, 'title', array('kind' => self::WPML_KIND, 'name' => $this->getId()));
		return \apply_filters('the_title', $value, $this->getId());
	}

	function getMessage()
	{
		$value = $this->excerpt;
		if( $value && !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
			$value = \apply_filters('wpml_translate_string', $value, 'excerpt', array('kind' => self::WPML_KIND, 'name' => $this->getId()));
		return \apply_filters('the_excerpt', $value);
	}

	function getThumbnailId()
	{
		if( !isset($this->thumbnailId) )
		{
			if( !$this->getId() )
				return false;
			$this->thumbnailId = \get_post_thumbnail_id($this->getId());
			if( $this->thumbnailId && !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
				$this->thumbnailId = \apply_filters('wpml_object_id', $this->thumbnailId, 'attachment', true);
		}
		return $this->thumbnailId;
	}

	/** @return string url */
	function getThumbnailUrl()
	{
		if( !($thumb = $this->getThumbnailId()) )
			return '';
		else
			return \wp_get_attachment_url($thumb);
	}

	/** @return html <img> */
	function getThumbnailImage($size=\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE)
	{
		if( !($thumb = $this->getThumbnailId()) )
			return '';
		else
			return \wp_get_attachment_image($thumb, $size, false, array('class'=>'lws-wr-thumbnail lws-wr-badge-thumbnail'));
	}

	function getEditLink($esc_attr=false)
	{
		$url = '';
		if( $id = $this->getId() )
		{
			$url = \get_edit_post_link($id, 'raw');
			if( $esc_attr )
				$url = \esc_attr($url);
		}
		return $url;
	}

	/** @return $this */
	protected function fromPost(\WP_Post $post)
	{
		return $this->setData($post->ID, $post->post_title, $post->post_excerpt);
	}

	/** @return $this */
	protected function setData($id, $title, $excerpt)
	{
		$this->valid = true;
		$this->id = $id;
		$this->title = $title;
		$this->excerpt = $excerpt;
		unset($this->thumbnailId);
		return $this;
	}

	static function getLinkTable()
	{
		global $wpdb;
		return ($wpdb->prefix.'lws_wr_userbadge');
	}

}

?>