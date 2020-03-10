<?php
namespace LWS\WOOREWARDS\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for each money spend on an order. */
class OrderAmount extends \LWS\WOOREWARDS\Abstracts\Event
{
	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'denominator'] = $this->getDenominator();
		$data[$prefix.'include_shipping'] = $this->getShipping() ? 'on' : '';
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();

		$str   = $this->getFieldsetPlaceholder(true, 1);
		$label = sprintf(_x("Money spent (%s)", "Order Amount Event money diviser", LWS_WOOREWARDS_DOMAIN), \LWS_WooRewards::isWC() ? \get_woocommerce_currency_symbol() : '?');
		$value = \esc_attr($this->getDenominator());
		$str .= "<tr><td class='lcell' nowrap><label for='{$prefix}denominator' class='lws-$context-opt-title'>$label</label></td>";
		$str .= "<td class='rcell'><div class='lws-$context-opt-input'><input type='text' id='{$prefix}denominator' name='{$prefix}denominator' value='$value' placeholder='1' pattern='\\d*' size='5' /></div>";
		$str .= "</td></tr>";

		return str_replace($this->getFieldsetPlaceholder(true, 1), $str, parent::getForm($context));
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'denominator'      => 'D',
				$prefix.'include_shipping' => 's',
			),
			'defaults' => array(
				$prefix.'denominator'      => '1',
				$prefix.'include_shipping' => '',
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setDenominator    ($values['values'][$prefix.'denominator']);
			$this->setShipping       (boolval($values['values'][$prefix.'include_shipping']));
		}
		return $valid;
	}

	/** @return bool */
	public function getShipping()
	{
		return isset($this->includeShipping) && $this->includeShipping;
	}

	public function setShipping($yes=true)
	{
		$this->includeShipping = $yes;
		return $this;
	}

	/** @return bool */
	public function getThresholdEffect()
	{
		if( isset($this->thresholdEffect) )
			return $this->thresholdEffect;
		else
			return true;
	}

	/** Points computed proportionaly or for each complet amount of money.
	 * Does we apply ceil. */
	public function setThresholdEffect($yes=true)
	{
		$this->thresholdEffect = $yes;
		return $this;
	}

	/** @return int */
	public function getDenominator()
	{
		return isset($this->denominator) ? $this->denominator : 1;
	}

	/** amount is divided by denominator before point earning. */
	public function setDenominator($value=1)
	{
		$this->denominator = max($value, 1);
		return $this;
	}

	public function getDisplayType()
	{
		return _x("Spend money", "getDisplayType", LWS_WOOREWARDS_DOMAIN);
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setShipping       (boolval(\get_post_meta($post->ID, 'wre_event_include_shipping', true)));
		$this->setThresholdEffect(boolval(\get_post_meta($post->ID, 'wre_event_threshold_effect', true)));
		$this->setDenominator    (intval (\get_post_meta($post->ID, 'wre_event_denominator',      true)));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_include_shipping', $this->getShipping());
		\update_post_meta($id, 'wre_event_threshold_effect', $this->getThresholdEffect()?'on':'');
		\update_post_meta($id, 'wre_event_denominator',      $this->getDenominator());
		return $this;
	}

	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), 99); // priority later to let other use some order lines
	}

	function orderDone($order)
	{
		$userId = $order->order->get_customer_id('edit');
		$this->user = empty($userId) ? false : \get_user_by('ID', $userId);
		if( $this->user == false )
			return $order;

		$amount = $this->getOrderAmount($order);
		$points = $this->getPointsForAmount($amount);

		if( $points > 0 )
		{
			$price = \wp_kses(\wc_price($amount, array('currency' => $order->order->get_currency())), array());
			$reason = sprintf(__('Spent %1$s from order #%2$s', LWS_WOOREWARDS_DOMAIN), $price, $order->order->get_order_number());
			$this->addPoint($userId, $reason, $points);
		}
		return $order;
	}

	function getPointsForAmount($amount)
	{
		$points = 0;
		if( $amount > 0 )
		{
			$points = floatval($amount) / floatval($this->getDenominator());
			if( $this->getThresholdEffect() )
				$points = floor($points);
		}
		return $points;
	}

	function getOrderAmount(&$order)
	{
		$amount = $order->amount;
		if( $this->getShipping() )
		{
			$amount += floatval($order->order->get_shipping_total('edit'));
			if( $order->inc_tax )
				$amount += floatval($order->order->get_shipping_tax('edit'));
		}
		return $amount;
	}

	/** Override to add information when context is 'view'. */
	public function getMultiplier($context='edit')
	{
		$mul = parent::getMultiplier($context);
		if( $context == 'view' && $mul > 0.0 )
		{
			$div = $this->getDenominator();
			$div = trim(trim(sprintf("%f", $div), '0'), '.,');
			$mul = trim(trim(sprintf("%f", $mul), '0'), '.,');
			$ptsName = \LWS_WooRewards::getPointSymbol($mul, $this->getPoolName());
			$currency = \LWS_WooRewards::isWC() ? \get_woocommerce_currency_symbol() : '?';
			return sprintf(_x('%1$s %2$s / %3$s %4$s', "Point per money spent", LWS_WOOREWARDS_DOMAIN), $mul, $ptsName, $div, $currency);
		}
		else
			return $mul;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", LWS_WOOREWARDS_DOMAIN),
			'money' => __("Money", LWS_WOOREWARDS_DOMAIN),
			'order' => __("Order", LWS_WOOREWARDS_DOMAIN)
		));
	}
}

?>