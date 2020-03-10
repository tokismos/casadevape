<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Admin badges display. */
class Badges
{
	function __construct()
	{
		if( !empty(\get_option('lws_woorewards_manage_badge_enable', 'on')) )
		{
			\add_filter('lws_adminpanel_topbars', array($this, 'adminTopBar'));
			\add_action('add_meta_boxes_'.\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE, array($this, 'addMetaBoxes'), 10, 1);
			\add_action('do_meta_boxes', array($this, 'removeOriginalMetabox'));
			\add_action('save_post', array($this, 'savePost'), 999, 1);
			\add_action('admin_enqueue_scripts', array($this , 'scripts'), 10, 1);
			\add_action('lws_adminpanel_register', array($this, 'addSubmenuPages'));
			\add_filter('custom_menu_order', array($this, 'filterSubmenuOrder'));
			\add_action('lws_adminpanel_stygen_content_echo_lws_wr_badge_style', array($this, 'badgeTemplate'));
			\add_filter('lws_wre_badge_css', array($this, 'getBadgeCSSUrl'));

			\add_filter('manage_edit-lws_badge_columns', array($this, 'listColumns'), 20);
			\add_filter('manage_edit-lws_badge_sortable_columns', array($this, 'listSortableColumns'));
			\add_filter('manage_lws_badge_posts_custom_column', array($this, 'listSingleRowValue'), 10, 2);
			\add_action('restrict_manage_posts', array($this, 'byUserFilter'), 10, 1);
			\add_filter('query_vars', array($this, 'addQueryVars'));
			\add_action('posts_join', array($this, 'parseQuery'));
		}

		\add_image_size(\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE, 60, 60);
		\add_filter('image_size_names_choose', array($this, 'filterImageSizeNames'));
		\add_filter('admin_post_thumbnail_size', array($this, 'filterThumbnailSize'), 10, 3);

		\add_action('delete_post', array($this, 'syncBadgeDeletion'), 10, 1);
		\add_action('delete_user', array($this, 'syncUserDeletion'), 10, 2);
	}

	/** break link between any user and the deleted badge. */
	function syncBadgeDeletion($postId)
	{
		global $wpdb;
		$table = \LWS\WOOREWARDS\PRO\Core\Badge::getLinkTable();
		$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE badge_id=%d", $postId));

		if( \function_exists('\icl_object_id') && \get_post_type($postId) == \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE )
			\do_action('wpml_delete_package_action', $postId, \LWS\WOOREWARDS\PRO\Core\Badge::WPML_KIND);
	}

	/** break link between any badge and the deleted user
	 * or change user owner if $reassign */
	function syncUserDeletion($userId, $reassign)
	{
		global $wpdb;
		$table = \LWS\WOOREWARDS\PRO\Core\Badge::getLinkTable();
		if( $reassign && $userId != $reassign )
		{
			// update owner id
			$wpdb->query($wpdb->prepare("UPDATE {$table} SET user_id=%d, assign_date=CURRENT_TIMESTAMP WHERE user_id=%d", $reassign, $userId));
			// check badge owned only once
			$sub = "SELECT MAX(ub_id) as todel, COUNT(ub_id) as total FROM {$table} WHERE user_id=%d GROUP BY badge_id";
			$todel = $wpdb->get_col($wpdb->prepare("SELECT todel FROM ({$sub}) as dup WHERE total > 1", $reassign));
			if( !empty($todel) )
			{
				$ids = "('" . implode("','", array_map('esc_sql', $todel)) . "')";
				$wpdb->query("DELETE FROM {$table} WHERE ub_id IN {$ids}");
			}
		}
		else
		{
			// delete link
			$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE user_id=%d", $userId));
		}
	}

	function adminTopBar($bars)
	{
		$settings = array(
			'title'    => 'WooRewards',
			'version'  => LWS_WOOREWARDS_PRO_VERSION,
			'doc'      => __("https://plugins.longwatchstudio.com/docs/woorewards/", LWS_WOOREWARDS_DOMAIN), // use translation from free version
			'licence'  => add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.settings', 'tab'=>'license'), admin_url('admin.php')),
			'activated'=> true,
			'exact_id' => true
		);
		$bars[\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE] = $settings;
		$bars['edit-' . \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE] = $settings;
		return $bars;
	}

	function scripts($hook)
	{
		$screen = false;
		if ( \is_admin() && function_exists( 'get_current_screen' ) )
			$screen = \get_current_screen();
		if( $screen && $screen->id == \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE )
		{
			\wp_enqueue_script('post');
			\add_thickbox();
			\wp_enqueue_media(array('post' => \get_the_ID()));
			\wp_enqueue_style('lws_wre_badge-metabox', LWS_WOOREWARDS_PRO_CSS.'/badge-metabox.css', array(), LWS_WOOREWARDS_PRO_VERSION);
			\wp_enqueue_script('lws_wre_badge-metabox', LWS_WOOREWARDS_PRO_JS.'/badge-metabox.js', array('lws-wre-badge'), LWS_WOOREWARDS_PRO_VERSION, true);
		}
	}

	function getBadgeCSSUrl($css)
	{
		return LWS_WOOREWARDS_PRO_CSS . '/templates/badge.css?stygen=lws_wr_badge_style';
	}

	function badgeTemplate()
	{
		$starUrl = LWS_WOOREWARDS_IMG . '/badge-star.png';
		$badgeUrl = LWS_WOOREWARDS_IMG . '/badge-reward.png';
		$title = __('Demo Badge Title', LWS_WOOREWARDS_PRO_DOMAIN);
		$text = __('Demo Badge Description. This is the description of the badge awarded to customers', LWS_WOOREWARDS_PRO_DOMAIN);
		$badge = <<<EOT
		<div class='lwss_selectable lws-badge-wrapper' data-type='Badge Container'>
			<div class='lwss_selectable lws-badge-img-container' data-type='Image Container'>
				<div class='lws-badge-star'><img src='{$starUrl}'/></div>
				<div class='lwss_selectable lws-badge-img' data-type='Badge Image'><img src='{$badgeUrl}'/></div>
			</div>
			<div class='lwss_selectable lws-badge-content-container' data-type='Content Container'>
				<div class='lwss_selectable lws-badge-content-title' data-type='Badge Title'>{$title}</div>
				<div class='lwss_selectable lws-badge-content-sep' data-type='Separator'></div>
				<div class='lwss_selectable lws-badge-content-text' data-type='Badge Description'>{$text}</div>
			</div>
		</div>
EOT;

		echo $badge;
	}

	function addSubmenuPages()
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/rarity.php';
		$menuKey = 'edit.php?post_type='.\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE;
		$pages = array(
			array(
				'id' => $menuKey,
				'prebuild' => '1'
			),
			array(
				'id' => 'badge_settings',
				'title' => __("Badges", LWS_WOOREWARDS_PRO_DOMAIN),
				'subtitle' => __("Settings", LWS_WOOREWARDS_PRO_DOMAIN),
				'rights' => 'manage_options',
				'groups' => array(
					'badge' => array(
						'id' => 'badge',
						'title' => __("Badge Popup", LWS_WOOREWARDS_PRO_DOMAIN),
						'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/badges-and-achievements/create-and-edit-badges/'),
						'fields' => array(
							'stygen' => array(
								'id' => 'lws_wr_badge_style',
								'title' => '',
								'type' => 'stygen',
								'extra' => array(
									'purpose' => 'action',
									'template' => 'lws_wr_badge_style',
									'html'=> false,
									'css'=>LWS_WOOREWARDS_PRO_URL.'/css/templates/badge.css',
								)
							)
						)
								),
					'rarity' => array(
						'id' => 'lws_woorewards_rarity_levels',
						'title' => __("Rarity Levels", LWS_WOOREWARDS_PRO_DOMAIN),
						'text' => __("Define the rarity levels of Badges.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>".
						__("The percentage value is the max percentage of users owning the badge to get the corresponding rarity.", LWS_WOOREWARDS_PRO_DOMAIN),
						'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/badges-and-achievements/badges-rarity/'),
						'editlist' => \lws_editlist(
							'Rarity',
							\LWS\WOOREWARDS\PRO\Ui\Editlists\BadgeRarity::ROW_ID,
							new \LWS\WOOREWARDS\PRO\Ui\Editlists\BadgeRarity(),
							\LWS\Adminpanel\EditList::MDA
						)->setPageDisplay(false)->setCssClass('lws-rarity-editlist'),
						'function' => function(){
							\wp_enqueue_style('lws-wre-pro-srarity', LWS_WOOREWARDS_PRO_CSS . '/rarity.css', array('lws-editlist'), LWS_WOOREWARDS_PRO_VERSION);
						}

					)
				)
			)
		);

		\lws_register_pages($pages);
	}

	/** Move categories and tags as last of submenus. */
	function filterSubmenuOrder($menu_order)
	{
		global $submenu;
		$menuKey = 'edit.php?post_type='.\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE;
		if( isset($submenu[$menuKey]) )
		{
			usort($submenu[$menuKey], function($a, $b){
				if( $a[1] == $b[1] )
					return 0;
				if( $a[1] == 'edit_posts' )
					return -1;
				if( $b[1] == 'edit_posts' )
					return 1;
				$aa = (0 === strpos($a[2], 'badge'));
				$bb = (0 === strpos($b[2], 'badge'));
				if( $aa != $bb )
					return $aa ? -1 : 1;
				return 0;
			});
		}
		return $menu_order;
	}

	function listColumns($columns)
	{
		$columns['users'] = __("Owners", LWS_WOOREWARDS_PRO_DOMAIN);
		return $columns;
	}

	function listSortableColumns($sortable)
	{
		$sortable['users'] = 'user_count';
		return $sortable;
	}

	function listSingleRowValue($column_name, $badge_id)
	{
		if( $column_name == 'users' )
		{
			$badge = new \LWS\WOOREWARDS\PRO\Core\Badge($badge_id);
			$count = $badge->ownerCount();
			if( $count )
			{
				$url = \esc_attr(add_query_arg(array(
					'page'=>LWS_WOOREWARDS_PAGE,
					'tab' => 'wr_customers',
					'badge'=>$badge_id
				), admin_url('admin.php')));
				$count = "<a class='lws_wre_customer_link' href='$url' target='_blank'>$count</a>";
			}
			echo $count;
		}
	}

	/** Show coupon custom filter wher applied. */
	function byUserFilter($postType)
	{
		if( $postType == \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE )
		{
			global $wp_query;
			$userId = isset($wp_query->query['user_id']) ? \absint($wp_query->query['user_id']) : '';

			echo "<label for='user' class='lws-wr-badge-filter-user'>" . __("Owner user", LWS_WOOREWARDS_PRO_DOMAIN) . "</label>";
			echo \LWS\Adminpanel\Pages\Field\LacSelect::compose('user_id', array('predefined'=>'user', 'value'=>$userId));
		}
	}

	/** @see parseQuery */
	function addQueryVars($vars)
	{
		$screen = false;
		if ( \is_admin() && function_exists( 'get_current_screen' ) )
			$screen = \get_current_screen();
		if( $screen && $screen->id == ('edit-'.\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE) )
		{
			$vars[] = 'user_id';
		}
		return $vars;
	}

	/** Allow filtering by owner from user_id or any userdata */
	function parseQuery($join)
	{
		$screen = false;
		if ( \is_admin() && function_exists( 'get_current_screen' ) )
			$screen = \get_current_screen();
		if( $screen && $screen->id == ('edit-'.\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE) )
		{
			global $wp_query, $wpdb;
			$userId = isset($wp_query->query['user_id']) ? \absint($wp_query->query['user_id']) : '';
			if( $userId )
			{
				$table = \LWS\WOOREWARDS\PRO\Core\Badge::getLinkTable();
				$join .= $wpdb->prepare(" INNER JOIN {$table} as lws_badges ON {$wpdb->posts}.ID=lws_badges.badge_id AND lws_badges.user_id=%d", $userId);
			}
		}
		return $join;
	}

	/** add a custom metabox to manage excerpt and thumbnail all together,
	 * closer to final success rendering. */
	function addMetaBoxes($post)
	{
		\add_meta_box(
        'lws_badge-excerpt_and_thumbnail-metabox',
        __("Success Content", LWS_WOOREWARDS_PRO_DOMAIN),
        array($this, 'echoExcerptAndThumbnailMetabox'),
        \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE,
        'normal',
        'high'
    );
		\add_meta_box(
        'lws_badge-preview-metabox',
        __("Animation preview", LWS_WOOREWARDS_PRO_DOMAIN),
        array($this, 'echoPreviewMetabox'),
        \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE,
        'side',
        'high'
    );

		if( \function_exists('\icl_object_id') )
		{
			\add_meta_box(
        'lws_badge-wpml',
        __("Language", LWS_WOOREWARDS_PRO_DOMAIN),
        array($this, 'echoWPMLMetabox'),
        \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE,
        'side',
        'default'
			);
		}
	}

	function savePost($postId)
	{
		if( \function_exists('\icl_object_id') && \get_post_type($postId) == \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE )
		{
			if( $post = \get_post($postId) )
			{
				$pack = array(
					'kind' => \LWS\WOOREWARDS\PRO\Core\Badge::WPML_KIND,
					'name' => $postId,
					'title' => $post->post_title,
					'edit_link' => \get_edit_post_link($postId, 'raw'),
				);

				\do_action('wpml_register_string', $post->post_title, 'title', $pack, __("Title", LWS_WOOREWARDS_PRO_DOMAIN), 'LINE');
				\do_action('wpml_register_string', $post->post_excerpt, 'excerpt', $pack, __("Content", LWS_WOOREWARDS_PRO_DOMAIN), 'VISUAL');
			}
		}
	}

	function echoWPMLMetabox($post)
	{
		echo "<div style='width:50%;'>";
		\do_action('wpml_show_package_language_ui', array(
			'kind' => \LWS\WOOREWARDS\PRO\Core\Badge::WPML_KIND,
			'name' => $post->ID,
		));
		echo "</div>";
	}

	function echoPreviewMetabox($post)
	{
		$achievementBg = \esc_attr(\apply_filters('lws_badge_achievement_background', LWS_WOOREWARDS_IMG.'/badge-star.png'));
		$label = __("Run a preview", LWS_WOOREWARDS_PRO_DOMAIN);
		echo "<div class='lws-badge-achievement-preview-metabox'>";
		echo "<div class='button-secondary' id='achievement-preview' data-achievement-bg='{$achievementBg}'>{$label}</div>";
		echo "</div>";

	}

	/** Since we provid a custom metabox for both thumbnail and excerpt,
	 * remove WordPress metaboxes output (but let WordPress save it anyway) */
	function removeOriginalMetabox()
	{
		\remove_meta_box('postexcerpt', \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE, 'normal');
		\remove_meta_box('postimagediv', \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE, 'side');
	}

	function filterImageSizeNames($sizes)
	{
		return array_merge(
			$sizes,
			array(\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE => __("Badge size", LWS_WOOREWARDS_PRO_DOMAIN))
		);
	}

	/** Filters the size used to display the post thumbnail image in the 'Featured Image' meta box. */
	function filterThumbnailSize($size, $thumbnail_id, $post)
	{
		if( $post && $post->post_type == \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE )
			$size = \LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE;
		return $size;
	}

	function echoExcerptAndThumbnailMetabox($post)
	{
		echo "<div id='postimagediv' class='lws-badge-metabox-wrapper lws_badge_main_metabox'>";

		if( \current_user_can('upload_files') )
		{
			echo "<div class='lws-badge-metabox-img-container inside'>";
			$thumbnail_id = \get_post_meta($post->ID, '_thumbnail_id', true);
			echo \_wp_post_thumbnail_html($thumbnail_id, $post);
			echo "</div>";
		}

		echo "<div id='postexcerpt' class='lws-badge-metabox-content-container'><div class='lws-badge-metabox-content-text'>";
		$label = __('Excerpt'); // no domain, get WP translation
		echo "<label class='screen-reader-text' for='excerpt'>{$label}</label>";
		echo \wp_editor(\html_entity_decode(\stripcslashes($post->post_excerpt)), 'excerpt');
		echo "</div></div>";

		echo "</div>";
	}
}
