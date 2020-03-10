<?php
namespace LWS\WOOREWARDS\PRO\Collections;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** A collection of achievememt. */
class Achievements extends \LWS\WOOREWARDS\Collections\Pools
{
	protected function loadArgs()
	{
		$args = parent::loadArgs();
		$args['post_type'] = \LWS\WOOREWARDS\PRO\Core\Achievement::A_POST_TYPE;
		return $args;
	}

	static function register($fullclassname=false)
	{
		static $s_fullclassname = '\LWS\WOOREWARDS\PRO\Core\Achievement';
		if( $fullclassname !== false )
			$s_fullclassname = $fullclassname;
		return $s_fullclassname;
	}
}
