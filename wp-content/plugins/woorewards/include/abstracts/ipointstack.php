<?php
namespace LWS\WOOREWARDS\Abstracts;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage user points and point history.
 *	Few functions have a force argument to reset the buffered amount by reading the database again. */
interface IPointStack
{
	const MetaPrefix = 'lws_wre_points_';

	function __construct($name, $userId);
	function get($force = false);
	function getHistory($force = false);
	function set($points, $reason='');
	function add($points, $reason='', $force = false);
	function sub($points, $reason='', $force = false);

	public function timeout(\DateTime $threshold); /// not user related
	public function delete(); /// not user related
	public function isUsed(); /// not user related
}

?>