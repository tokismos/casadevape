<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/usertitle.php';

/**
 * Assign a badge to a user. */
class Badge extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'badge'] = $this->getBadgeId();
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Badge", LWS_WOOREWARDS_PRO_DOMAIN), 'col50');

		// The badge
		$label   = _x("Badge", "event form", LWS_WOOREWARDS_PRO_DOMAIN);
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}badge' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'><div class='lws-$context-opt-input lws-lac-select-badge'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacSelect::compose($prefix.'badge', array(
			'ajax' => 'lws_woorewards_badge_list',
			'value' => $this->getBadgeId()
		));
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
				$prefix.'badge' => 'D',
			),
			'defaults' => array(
			),
			'labels'   => array(
				$prefix.'badge' => __("Badge", LWS_WOOREWARDS_PRO_DOMAIN),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setBadgeId($values['values'][$prefix.'badge']);
		}
		return $valid;
	}

	public function getBadge()
	{
		$badge = new \LWS\WOOREWARDS\PRO\Core\Badge($this->getBadgeId());
		return $badge->isValid() ? $badge : false;
	}

	public function getBadgeId()
	{
		return isset($this->badgeId) ? $this->badgeId : '';
	}

	public function setBadgeId($badge)
	{
		$this->badgeId = $badge;
		return $this;
	}

	public function setTestValues()
	{
		// ...
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setBadgeId(\get_post_meta($post->ID, 'woorewards_badge_id', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_badge_id', $this->getBadgeId());
		return $this;
	}

	public function createReward(\WP_User $user, $demo=false)
	{
		if( $badge = $this->getBadge() )
		{
			if( !$demo && $user && $user->ID )
			{
				if( false === $badge->assignTo($user->ID, $this->getId()) )
					return false; // user already got that badge
			}
		}
		return $badge;
	}

	public function getDisplayType()
	{
		return _x("Assign a badge", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** use product image by default if any but can be override by user */
	public function getThumbnailUrl()
	{
		if( empty($this->getThumbnail()) && !empty($badge = $this->getBadge()) && !empty($img = $badge->getThumbnailUrl()) )
			return $img;
		else
			return parent::getThumbnailUrl();
	}

	/** use product image by default if any but can be override by user */
	public function getThumbnailImage($size='lws_wr_thumbnail')
	{
		if( empty($this->getThumbnail()) && !empty($badge = $this->getBadge()) && !empty($img = $badge->getThumbnailImage($size)) )
			return $img;
		else
			return parent::getThumbnailImage($size);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		$badge = $this->getBadge();
		$name = $badge ? $badge->getTitle() : __('[unknown]', LWS_WOOREWARDS_PRO_DOMAIN);

		$str = '';
		if( $context != 'raw' )
		{
			$url = false;
			if( $context == 'backend' && $badge )
				$url = $badge->getEditLink(true);

			$name = $url ? "<a href='{$url}'>{$name}</a>" : "<b>{$name}</b>";
			$str .= sprintf(_x("Assign badge %s", 'pretty text', LWS_WOOREWARDS_PRO_DOMAIN), $name);
		}
		else
			$str .= sprintf(_x("Assign badge '%s'", 'raw text', LWS_WOOREWARDS_PRO_DOMAIN), $name);

		return $str;
	}

	/** A badge can only be purchased once.
	 * @return (bool) if user already owned the badge. */
	public function noMorePurchase($userId)
	{
		if( !\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			$badge = $this->getBadge();
			if( $badge && $userId && $badge->ownedBy($userId) )
				return true;
		}
		return false;
	}

	public function isPurchasable($points=PHP_INT_MAX, $userId=null)
	{
		$purchasable = parent::isPurchasable($points, $userId);
		if( $purchasable && !\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			if( !($badge = $this->getBadge()) )
				$purchasable = false;
			else if( $purchasable && $userId && $badge->ownedBy($userId) )
				$purchasable = false;
		}
		return $purchasable;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'badge' => __("Badge", LWS_WOOREWARDS_PRO_DOMAIN),
			'achievements' => __("Achievements", LWS_WOOREWARDS_PRO_DOMAIN),
			'wp_user'   => __("User", LWS_WOOREWARDS_PRO_DOMAIN),
		));
	}
}

?>