<?php
namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add a user birthday field. */
class BirthdayField
{
	static function register()
	{
		if( !empty(\get_option('lws_woorewards_registration_birthday_field')) )
		{
			\add_filter('woocommerce_checkout_fields', array(new self(), 'checkout'));
		}
		if( !empty(\get_option('lws_woorewards_myaccount_detail_birthday_field')) )
		{
			$me = new self();
			\add_action('woocommerce_edit_account_form', array($me, 'myaccountDetailForm'));
			\add_action('woocommerce_save_account_details', array($me, 'myaccountDetailSave'));
		}
		if( !empty(\get_option('lws_woorewards_myaccount_register_birthday_field')) )
		{
			$me = new self();
			\add_action('woocommerce_register_form', array($me, 'myaccountRegisterForm'));
			\add_filter('woocommerce_process_registration_errors', array($me, 'myaccountRegisterValidation'), 10, 4);
			\add_action('woocommerce_created_customer', array($me, 'myaccountRegisterSave'), 10, 1);
		}
	}

	protected function getDefaultBirthdayMetaKey()
	{
		return 'billing_birth_date';
	}

	function checkout($fields)
	{
		$fields['account'][$this->getDefaultBirthdayMetaKey()] = array(
			'type'        => 'date',
			'label'       => __("Birthday", LWS_WOOREWARDS_PRO_DOMAIN),
			'required'    => false
		);
		return $fields;
	}

	function myaccountRegisterForm()
	{
		$field = $this->getDefaultBirthdayMetaKey();
		$label = __("Birthday", LWS_WOOREWARDS_PRO_DOMAIN);

		echo "<p class='woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide'>";
		echo "<label for='{$field}'>$label</label>";
		echo "<input type='date' class='woocommerce-Input woocommerce-Input--text input-text' name='{$field}' id='{$field}' />";
		echo "</p>";
	}

	function myaccountRegisterValidation($validation_error, $username, $password, $email)
	{
		$birtday = $this->grabBirtdayFromPost();
		if( false === $birtday )
			$validation_error->add($field, __("Invalid date format for birthday", LWS_WOOREWARDS_PRO_DOMAIN), 'birtday');
		return $validation_error;
	}

	function myaccountRegisterSave($userId)
	{
		$birtday = $this->grabBirtdayFromPost();
		\update_user_meta($userId, $this->getDefaultBirthdayMetaKey(), $birtday);
	}

	function myaccountDetailForm()
	{
		$userId = \get_current_user_id();
		$field = $this->getDefaultBirthdayMetaKey();
		$label = __("Birthday", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = \esc_attr(\get_user_meta($userId, $field, true));

		echo "<p class='woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide'>";
		echo "<label for='{$field}'>{$label}</label>";
		echo "<input type='date' class='woocommerce-Input woocommerce-Input--text input-text' name='{$field}' id='{$field}' value='{$value}' />";
		echo "</p><div class='clear'></div>";
	}

	function myaccountDetailSave($userId)
	{
		$birtday = $this->grabBirtdayFromPost();
		if( $birtday !== false )
			\update_user_meta($userId, $this->getDefaultBirthdayMetaKey(), $birtday);
		else
			\wc_add_notice(__("Invalid date format for birthday", LWS_WOOREWARDS_PRO_DOMAIN), 'error');
	}

	function grabBirtdayFromPost()
	{
		$field = $this->getDefaultBirthdayMetaKey();
		$birtday = !empty($_POST[$field]) ? \wc_clean($_POST[$field]): '';
		if( !empty($birtday) )
		{
			if( empty(\date_create($birtday)) )
			{
				\wc_add_notice(__("Invalid date format for birthday", LWS_WOOREWARDS_PRO_DOMAIN), 'error');
				$birtday = false;
			}
		}
		return $birtday;
	}
}
