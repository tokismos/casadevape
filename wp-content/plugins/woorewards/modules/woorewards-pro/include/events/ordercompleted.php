<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Earn points for each money spend on an order. */
class OrderCompleted extends \LWS\WOOREWARDS\Events\OrderCompleted
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'min_amount'] = $this->getMinAmount();
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Options", LWS_WOOREWARDS_PRO_DOMAIN));

		// min amount
		$label = _x("Minimum order amount", "Coupon Unlockable", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = empty($this->getMinAmount()) ? '' : \esc_attr($this->getMinAmount());
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}min_amount' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input type='text' id='{$prefix}min_amount' name='{$prefix}min_amount' value='$value' placeholder='5' pattern='\\d*(\\.|,)?\\d*' /></div>";
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
				$prefix.'min_amount' => 'f'
			),
			'defaults' => array(
				$prefix.'min_amount' => ''
			),
			'labels'   => array(
				$prefix.'min_amount'   => __("Minimum order amount", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setMinAmount($values['values'][$prefix.'min_amount']);
		}
		return $valid;
	}

	function getDescription($context='backend')
	{
		$descr = parent::getDescription($context);
		if( ($min = $this->getMinAmount()) > 0.0 )
		{
			$dec = \absint(\apply_filters('wc_get_price_decimals', \get_option( 'woocommerce_price_num_decimals', 2)));
			$descr .= sprintf(__(" (amount greater than %s)", LWS_WOOREWARDS_PRO_DOMAIN), \number_format_i18n($min, $dec));
		}
		return $descr;
	}

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Events\OrderCompleted';
	}

	function getMinAmount()
	{
		return isset($this->minAmount) ? $this->minAmount : 0;
	}

	public function setMinAmount($amount=0)
	{
		$this->minAmount = max(0.0, floatval(str_replace(',', '.', $amount)));
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setMinAmount(\get_post_meta($post->ID, 'wre_event_min_amount', true));
		return parent::_fromPost($post);
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_min_amount', $this->getMinAmount());
		return parent::_save($id);
	}

	function orderDone($order)
	{
		if( $order->amount < $this->getMinAmount() )
			return $order;
		return parent::orderDone($order);
	}

	function getPointsForProduct(\WC_Product $product)
	{
		return 0;
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		if( $this->getMinAmount() > 0.0 )
		{
			$amount = floatval($cart->get_subtotal());
			if( !empty(\get_option('lws_woorewards_order_amount_includes_taxes', '')) )
				$amount += floatval($cart->get_cart_tax());

			if( $amount < $this->getMinAmount() )
				return 0;
		}
		return $this->getMultiplier();
	}
}

?>