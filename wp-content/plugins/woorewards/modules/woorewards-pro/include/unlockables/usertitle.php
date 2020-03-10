<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/usertitle.php';

/**
 * Create a WooCommerce Coupon. */
class UserTitle extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'utitle'] = $this->getUserTitle();
		$data[$prefix.'utpos'] = $this->getPosition();
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Customer's Title", LWS_WOOREWARDS_PRO_DOMAIN), 'col50');

		// title
		$label = _x("Customer's Title", "User's title", LWS_WOOREWARDS_PRO_DOMAIN);
		$value = empty($this->getUserTitle()) ? '' : \esc_attr($this->getUserTitle());
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}utitle' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input type='text' id='{$prefix}value' name='{$prefix}utitle' value='$value' /></div>";
		$form .= "</td></tr>";

		// position
		$label = array(
			0 => _x("Position", "User's title position", LWS_WOOREWARDS_PRO_DOMAIN),
			'l' => _x("Before name", "User's title position", LWS_WOOREWARDS_PRO_DOMAIN),
			'r' => _x("After name", "User's title position", LWS_WOOREWARDS_PRO_DOMAIN),
		);
		$value = array(
			'l' => $this->getPosition() == 'left' ? ' checked' : '',
			'r' => $this->getPosition() != 'left' ? ' checked' : ''
		);
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}utpos' class='lws-$context-opt-title'>{$label[0]}</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'>";
		$form .= "<label><input type='radio' id='{$prefix}utpos' name='{$prefix}utpos' value='left'{$value['l']} />{$label['l']}</label>";
		$form .= "<label><input type='radio' name='{$prefix}utpos' value='right'{$value['r']} />{$label['r']}</label>";
		$form .= "</div></td></tr>";

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
				$prefix.'utpos' => '/(right|left)/i',
				$prefix.'utitle' => 'S'
			),
			'defaults' => array(
				$prefix.'utpos' => 'right'
			),
			'labels'   => array(
				$prefix.'utpos'   => __("Title position", LWS_WOOREWARDS_PRO_DOMAIN),
				$prefix.'utitle' => __("Customer title", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setUserTitle($values['values'][$prefix.'utitle']);
			$this->setPosition ($values['values'][$prefix.'utpos']);
		}
		return $valid;
	}

	public function getUserTitle()
	{
		$utitle = isset($this->userTitle) ? $this->userTitle : '';
		return $utitle;
	}

	public function setUserTitle($userTitle='')
	{
		$this->userTitle = $userTitle;
		return $this;
	}

	public function getPosition()
	{
		return isset($this->userTitlePosition) ? $this->userTitlePosition : 'right';
	}

	public function setPosition($position='right')
	{
		if( strtolower(substr($position, 0, 1)) == 'l' )
			$this->userTitlePosition = 'left';
		else
			$this->userTitlePosition = 'right';
		return $this;
	}

	public function setTestValues()
	{
		$this->setUserTitle(__(": The Tester", LWS_WOOREWARDS_PRO_DOMAIN));
		if( !empty($user = \wp_get_current_user()) )
		{
			$title = $this->getUserTitle();
			$this->lastUserName = sprintf(\LWS\WOOREWARDS\PRO\Core\UserTitle::getPlaceholder($this->getPosition()), $user->display_name, $title);
		}
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setUserTitle(\get_post_meta($post->ID, 'woorewards_special_title', true));
		$this->setPosition(\get_post_meta($post->ID, 'woorewards_special_title_position', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_special_title', $this->getUserTitle());
		\update_post_meta($id, 'woorewards_special_title_position', $this->getPosition());

		if( isset($this->userTitle) )
			\do_action('wpml_register_single_string', 'WooRewards User Title', "WooRewards User Title", $this->userTitle);
		return $this;
	}

	public function createReward(\WP_User $user, $demo=false)
	{
		if( !$demo )
		{
			\update_user_meta($user->ID, 'woorewards_special_title', $this->getUserTitle());
			\update_user_meta($user->ID, 'woorewards_special_title_position', $this->getPosition());
		}

		$this->lastUserName = \LWS\WOOREWARDS\PRO\Core\UserTitle::getDisplayName($user, false, 'reason');
		return array(
			'user_title' => $this->getUserTitle(),
			'user_title_position' => $this->getPosition()
		);
	}

	public function getDisplayType()
	{
		return _x("User title", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		if( isset($this->lastUserName) )
			$name = $this->lastUserName;
		else
		{
			if( $context != 'backend' && !empty($user = \wp_get_current_user()) )
				$demo = $user->display_name;
			else
				$demo = _x("YourName", "A default name for demo", LWS_WOOREWARDS_PRO_DOMAIN);
			$title = $this->getUserTitle();
			$name = sprintf(\LWS\WOOREWARDS\PRO\Core\UserTitle::getPlaceholder($this->getPosition()), $demo, $title);
		}
		return sprintf(__("Be known as <b>%s</b>", LWS_WOOREWARDS_PRO_DOMAIN), $name);
	}

	/** For point movement historic purpose. Can be override to return a reason.
	 *	Last generated coupon code is consumed by this function. */
	public function getReason($context='backend')
	{
		if( isset($this->lastUserName) )
			return sprintf(__("The user becomes %s", LWS_WOOREWARDS_PRO_DOMAIN), $this->lastUserName);
		else
			return $this->getDescription($context);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING => __("Levelling", LWS_WOOREWARDS_PRO_DOMAIN),
			'wordpress' => __("WordPress", LWS_WOOREWARDS_PRO_DOMAIN),
			'wp_user'   => __("User", LWS_WOOREWARDS_PRO_DOMAIN)
		);
	}
}

?>