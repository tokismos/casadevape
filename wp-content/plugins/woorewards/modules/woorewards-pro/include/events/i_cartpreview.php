<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for each money spend on an order. */
interface I_CartPreview
{
	/** @return float or array if product variation with different prices. */
	function getPointsForProduct(\WC_Product $product);
	/** @return float */
	function getPointsForCart(\WC_Cart $cart);
}
