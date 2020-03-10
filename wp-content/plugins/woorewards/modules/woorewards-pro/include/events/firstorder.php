<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Earn point the first time a customer complete an order. */
class FirstOrder extends \LWS\WOOREWARDS\Events\FirstOrder
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	function getClassname()
	{
		return 'LWS\WOOREWARDS\Events\FirstOrder';
	}

	function getPointsForProduct(\WC_Product $product)
	{
		return 0;
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		if( $this->getOrderCount(\get_current_user_id()) > 0 )
			return 0;
		return $this->getMultiplier();
	}
}
