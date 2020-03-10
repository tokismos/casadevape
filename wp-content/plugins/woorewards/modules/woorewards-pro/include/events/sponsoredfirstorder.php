<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Sponsor Earns points for the first time sponsored places an order. */
class SponsoredFirstOrder extends \LWS\WOOREWARDS\Abstracts\Event
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
		$label   = _x("First order only", "Sponsored Order Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$tooltip = \lws_get_tooltips_html(__("If checked, only the first order placed by each sponsored customer will give points.", LWS_WOOREWARDS_PRO_DOMAIN));
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
		return _x("Sponsored order", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_sponsored_order', array($this, 'orderDone'), 10, 3);
	}

	function orderDone($sponsor, $user, $order)
	{
		if( !($order->get_customer_id('edit') || $this->isGuestAllowed()) )
		{
			return $order;
		}

		if( $this->isFirstOrderOnly() )
		{
			if( $user->ID && \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountById($user->ID, $order->get_id()) > 0 )
				return $order;

			if( \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountByEMail($user->user_email, $order->get_id()) > 0 )
				return $order;
		}

		$reason = sprintf(__("Sponsored friend %s order #%s", LWS_WOOREWARDS_PRO_DOMAIN), $user->user_email, $order->get_order_number());
		$this->addPoint($sponsor->ID, $reason);

		return $order;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", LWS_WOOREWARDS_PRO_DOMAIN),
			'sponsorship' => __("Available for sponsored", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}

	/** @param $sponsored (string) email
	 *	@return (bool) */
	protected function hasSponsorFor($sponsored)
	{
		global $wpdb;
		$sql ="SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='lws_wooreward_used_sponsorship' AND meta_value=%s";
		$sponsor_id = $wpdb->get_var($wpdb->prepare($sql, $sponsored));
		return !empty($sponsor_id);
	}

	function getPointsForProduct(\WC_Product $product)
	{
		return 0;
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		$user = \wp_get_current_user();
		if( empty($user->ID) || empty($user->user_email) || !$this->hasSponsorFor($user->user_email) )
			return 0;
		if( $this->isFirstOrderOnly() )
		{
			if( \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountById($user->ID) > 0 )
				return $order;
		}
		return $this->getMultiplier();
	}
}

?>