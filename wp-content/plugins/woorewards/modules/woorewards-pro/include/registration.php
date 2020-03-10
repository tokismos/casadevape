<?php
/** Register all event and unlockables */
if( !defined('LWS_WOOREWARDS_PRO_INCLUDES') ) exit();
if( !defined('LWS_WOOREWARDS_FILE') ) exit();


\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\PRO\Unlockables\Coupon', LWS_WOOREWARDS_PRO_INCLUDES.'/unlockables/coupon.php', false, 'lws_woorewards_unlockables_coupon');
\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\PRO\Unlockables\UserTitle', LWS_WOOREWARDS_PRO_INCLUDES.'/unlockables/usertitle.php');
\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\PRO\Unlockables\FreeProduct', LWS_WOOREWARDS_PRO_INCLUDES.'/unlockables/freeproduct.php');
\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\PRO\Unlockables\CustomReward', LWS_WOOREWARDS_PRO_INCLUDES.'/unlockables/customreward.php');
\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\PRO\Unlockables\FreeShipping', LWS_WOOREWARDS_PRO_INCLUDES.'/unlockables/freeshipping.php');
\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\PRO\Unlockables\VariableDiscount', LWS_WOOREWARDS_PRO_INCLUDES.'/unlockables/variablediscount.php');
\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\PRO\Unlockables\Role', LWS_WOOREWARDS_PRO_INCLUDES.'/unlockables/role.php');

\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\OrderAmount', LWS_WOOREWARDS_PRO_INCLUDES.'/events/orderamount.php', false, 'lws_woorewards_events_orderamount');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\OrderCompleted', LWS_WOOREWARDS_PRO_INCLUDES.'/events/ordercompleted.php', false, 'lws_woorewards_events_ordercompleted');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\FirstOrder', LWS_WOOREWARDS_PRO_INCLUDES.'/events/firstorder.php', false, 'lws_woorewards_events_firstorder');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\Sponsorship', LWS_WOOREWARDS_PRO_INCLUDES.'/events/sponsorship.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\SponsoredRegistration', LWS_WOOREWARDS_PRO_INCLUDES.'/events/sponsoredregistration.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\SponsoredFirstOrder', LWS_WOOREWARDS_PRO_INCLUDES.'/events/sponsoredfirstorder.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\ProductReview', LWS_WOOREWARDS_PRO_INCLUDES.'/events/productreview.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\EasterEgg', LWS_WOOREWARDS_PRO_INCLUDES.'/events/easteregg.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\BuyInCategory', LWS_WOOREWARDS_PRO_INCLUDES.'/events/buyincategory.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\BuySpecificProduct', LWS_WOOREWARDS_PRO_INCLUDES.'/events/buyspecificproduct.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\Register', LWS_WOOREWARDS_PRO_INCLUDES.'/events/register.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\Visit', LWS_WOOREWARDS_PRO_INCLUDES.'/events/visit.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\PostComment', LWS_WOOREWARDS_PRO_INCLUDES.'/events/postcomment.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\ReferralRegister', LWS_WOOREWARDS_PRO_INCLUDES.'/events/referralregister.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\ReferralFirstOrder', LWS_WOOREWARDS_PRO_INCLUDES.'/events/referralfirstorder.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\Anniversary', LWS_WOOREWARDS_PRO_INCLUDES.'/events/anniversary.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\Birthday', LWS_WOOREWARDS_PRO_INCLUDES.'/events/birthday.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\RestrictedVisit', LWS_WOOREWARDS_PRO_INCLUDES.'/events/restrictedvisit.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\SocialSharing', LWS_WOOREWARDS_PRO_INCLUDES.'/events/socialsharing.php');
\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\SocialBacklink', LWS_WOOREWARDS_PRO_INCLUDES.'/events/socialbacklink.php');

if( !empty(\get_option('lws_woorewards_manage_badge_enable', 'on')) )
{
	\LWS\WOOREWARDS\Abstracts\Unlockable::register('\LWS\WOOREWARDS\PRO\Unlockables\Badge', LWS_WOOREWARDS_PRO_INCLUDES.'/unlockables/badge.php');
	\LWS\WOOREWARDS\Abstracts\Event::register('\LWS\WOOREWARDS\PRO\Events\Badge', LWS_WOOREWARDS_PRO_INCLUDES.'/events/badge.php');
}

\do_action('lws_woorewards_registration');
