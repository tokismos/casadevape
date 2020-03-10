<?php
/** Register all event and unlockables */
if( !defined('LWS_WOOREWARDS_INCLUDES') ) exit();

//if( \apply_filters('lws_woorewards_is_woocommerce_active', false) )
{
	\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\Unlockables\Coupon', LWS_WOOREWARDS_INCLUDES.'/unlockables/coupon.php');
	\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\Events\FirstOrder',       LWS_WOOREWARDS_INCLUDES.'/events/firstorder.php');
	\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\Events\OrderAmount',      LWS_WOOREWARDS_INCLUDES.'/events/orderamount.php');
	\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\Events\OrderCompleted',   LWS_WOOREWARDS_INCLUDES.'/events/ordercompleted.php');
}

?>