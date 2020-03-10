<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Earn points on a visitor first order after following a referral link. */
class ReferralFirstOrder extends \LWS\WOOREWARDS\Abstracts\Event
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'first_order_only'] = $this->isFirstOrderOnly() ? 'on' : '';
		$data[$prefix.'guest'] = $this->isGuestAllowed() ? 'on' : '';
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Options", LWS_WOOREWARDS_PRO_DOMAIN), 'col30');

		// First Order Only
		$label   = _x("First order only", "Referral Order Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("If checked, only the first order placed by each customer using the referral link will give points.", LWS_WOOREWARDS_PRO_DOMAIN));
		$checked = $this->isFirstOrderOnly() ? 'checked' : '';
		$form .= "<tr class=''><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}first_order_only' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input class='lws_checkbox' type='checkbox' id='{$prefix}first_order_only' name='{$prefix}first_order_only' $checked/></div>";
		$form .= "</td></tr>";

		// Allow guest order
		$label   = _x("Guest order", "Sponsored Order Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("By default, customer must be registered. Check that option to accept guests. Customer will be tested on billing email.", LWS_WOOREWARDS_PRO_DOMAIN));
		$checked = $this->isGuestAllowed() ? 'checked' : '';
		$form .= "<tr class=''><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}guest' class='lws-$context-opt-title'>$label</label>$tooltip</td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input class='lws_checkbox' type='checkbox' id='{$prefix}guest' name='{$prefix}guest' $checked/></div>";
		$form .= "</td></tr>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'first_order_only' => 's',
				$prefix.'guest' => 's',
			),
			'defaults' => array(
				$prefix.'first_order_only' => '',
				$prefix.'guest' => '',
			),
			'labels'   => array(
				$prefix.'first_order_only' => __("First order only", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'guest' => __("Guest order", LWS_WOOREWARDS_PRO_DOMAIN),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setFirstOrderOnly($values['values'][$prefix.'first_order_only']);
			$this->setGuestAllowed($values['values'][$prefix.'guest']);
		}
		return $valid;
	}

	public function setFirstOrderOnly($yes=false)
	{
		$this->firstOrderOnly = boolval($yes);
		return $this;
	}

	function isFirstOrderOnly()
	{
		return isset($this->firstOrderOnly) ? $this->firstOrderOnly : true;
	}

	public function setGuestAllowed($yes)
	{
		$this->guestAllowed = boolval($yes);
		return $this;
	}

	function isGuestAllowed()
	{
		return isset($this->guestAllowed) ? $this->guestAllowed : false;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$firstOnly = \get_post_meta($post->ID, 'wre_event_first_order_only', false); // backward compatibility, option introduced on 3.6.0
		$this->setFirstOrderOnly( empty($firstOnly) ? 'on' : reset($firstOnly) );
		$this->setGuestAllowed(\get_post_meta($post->ID, 'wre_event_guest', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_first_order_only', $this->isFirstOrderOnly() ? 'on' : '');
		\update_post_meta($id, 'wre_event_guest', $this->isGuestAllowed() ? 'on' : '');
		return $this;
	}

	/** @return a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Referral Order", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), 102);
	}

	function orderDone($order)
	{
		$email = $order->order->get_billing_email('edit');
		if( empty($userId = $order->order->get_customer_id('edit')) )
		{
			if( !$this->isGuestAllowed() )
				return $order;

			if( !$email )
				return $order;

			$user = \get_user_by('email', $email);
			if( $user && $user->ID )
				$userId = $user->ID;
		}

		if( empty($refId = $this->getReferral($userId)) )
			return $order;

		if( !$userId )
		{
			$ref = \get_user_by('ID', $refId);
			if( $ref && $ref->ID && $email == $ref->user_email )
				return $order;
		}

		if( $this->isFirstOrderOnly() )
		{
			if( $userId && \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountById($userId, $order->order->get_id()) > 0 )
				return $order;

			if( \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountByEMail($email, $order->order->get_id()) > 0 )
				return $order;
		}

		$reason = sprintf(__("Order #%s validated with your referral", LWS_WOOREWARDS_PRO_DOMAIN), $order->order->get_order_number());
		$this->addPoint($refId, $reason);

		return $order;
	}

	function getReferral($userId)
	{
		if( !isset($_COOKIE['lws_referral_'.COOKIEHASH]) )
			return false;

		$referral = trim($_COOKIE['lws_referral_'.COOKIEHASH]);
		global $wpdb;
		$refId = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='lws_woorewards_user_referral_token' AND meta_value=%s", $referral));

		if( empty($refId) )
			return false;
		if( $userId == $refId )
			return false;

		return $refId;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", LWS_WOOREWARDS_PRO_DOMAIN),
			'referral' => __("Referral", LWS_WOOREWARDS_PRO_DOMAIN),
			'social' => __("Social network", LWS_WOOREWARDS_PRO_DOMAIN),
			'order' => __("Orders", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}

	function getPointsForProduct(\WC_Product $product)
	{
		return 0;
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		if( empty($userId = \get_current_user_id()) )
			return 0;
		if( empty($refId = $this->getReferral($userId)) )
			return 0;
		if( $this->isFirstOrderOnly() )
		{
			if( \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountById($userId) > 0 )
				return 0;
		}
		return $this->getMultiplier();
	}
}

?>