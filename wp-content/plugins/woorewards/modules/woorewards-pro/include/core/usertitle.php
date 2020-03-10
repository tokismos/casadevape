<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add a title around user name display.
 * place filter for WordPress on:
 * * comment author (the only one available in WooRewards < 3.0)
 * * post author
 *
 * use user_meta 'woorewards_special_title' for title text
 * and 'woorewards_special_title_position' for title position: 'left' or 'right'. */
class UserTitle
{
	public function __construct()
	{
		\add_filter( 'get_comment_author', array( $this, 'commentAuthor'), 10, 3);
		\add_filter( 'the_author', array( $this, 'postAuthor'));
	}

	public function commentAuthor($name, $commentId, $comment)
	{
		if( empty($comment->user_id) )
			return $name;
		else
			return self::getDisplayName($comment->user_id, $name, 'comment');
	}

	public function postAuthor($name)
	{
		global $authordata;
		if( !is_object($authordata) || !isset($authordata->ID) || empty($authordata->ID) )
			return $name;
		else
			return self::getDisplayName($authordata->ID, $name, 'unknown');
	}

	/** the title associated to the user or empty string. */
	static function getTitle($user)
	{
		$title = \get_user_meta(\is_a($user, 'WP_User') ? $user->ID : $user, 'woorewards_special_title', true);
		if( empty($title) )
			return '';
		return \apply_filters('wpml_translate_single_string', $title, 'WooRewards User Title', "WooRewards User Title");
	}

	/** format to be used with sprintf('xxx', $name, $title)
	 * default format place title at name's left.
	 * @param $context (string, default:display) if 'display' the title is html decorated. */
	static function getFormat($user, $context='display')
	{
		$pos = \get_user_meta(\is_a($user, 'WP_User') ? $user->ID : $user, 'woorewards_special_title_position', true);
		return self::getPlaceholder($pos, $context);
	}

	/** format to be used with sprintf('xxx', $name, $title) */
	static function getPlaceholder($position, $context='display')
	{
		$name = '%1$s';
		$title = '%2$s';
		if( $context == 'display' )
			$title = "<span class='wr-user-title'>$title</span>";
		return ($position != 'left' ? "$name $title" : "$title $name");
	}

	/** @return the display name formated with title if any.
	 * @param $user (WP_User|int) the user.
	 * @param $displayName if given, use it instead of the user display_name
	 * @param $context (string, default:display) if 'display' the title is html decorated. */
	static function getDisplayName($user, $displayName=false, $context='display')
	{
		if( $displayName === false )
		{
			if( \is_a($user, 'WP_User') || !empty($user = \get_userdata($user)) )
				$displayName = $user->display_name;
			else
				$displayName = '';
		}

		if( empty($title = self::getTitle($user)) )
			return $displayName;
		else
			return sprintf(self::getFormat($user, $context), $displayName, $title);
	}
}

?>
