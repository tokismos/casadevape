<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for article comment.
 * That must be the first comment of that customer on that article. */
class PostComment extends \LWS\WOOREWARDS\Abstracts\Event
{
	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'apply'] = '';
		if( $this->isInPostTypes('post') )
			$data[$prefix.'apply'] .= 'o';
		if( $this->isInPostTypes('page') )
			$data[$prefix.'apply'] .= 'a';
		return $data;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'apply' => '/o?a?o?/i'
			),
			'defaults' => array(
				$prefix.'apply' => 'oa'
			),
			'labels'   => array(
				$prefix.'apply' => __("Apply On", LWS_WOOREWARDS_PRO_DOMAIN)
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			if( $values['values'][$prefix.'apply'] == 'o' )
				$this->setPostTypes('post');
			else if( $values['values'][$prefix.'apply'] == 'a' )
				$this->setPostTypes('page');
			else
				$this->setPostTypes(array('post', 'page'));
		}
		return $valid;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Apply on", LWS_WOOREWARDS_PRO_DOMAIN), 'col40');

		$cpost = $this->isInPostTypes('post') ? 'checked' : '';
		$cpage = $this->isInPostTypes('page') ? 'checked' : '';
		$cboth = '';
		if( $cpost == $cpage )
		{
			$cpost = $cpage = '';
			$cboth = 'checked';
		}

		$attrs = "data-baseicon='lws-icon-square-o' data-selecticon='lws-icon-square' data-selectcolor='#3fa9f5' data-size='30px'";

		// post
		$label   = _x("Post", "Post Comment Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$checked = $cpost;
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}post' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input $attrs class='lws_radio' type='radio' id='{$prefix}post' name='{$prefix}apply' $checked/></div>";
		$form .= "</td></tr>";

		// page
		$label   = _x("Page", "Post Comment Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$checked = $cpage;
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}page' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input $attrs class='lws_radio' type='radio' id='{$prefix}page' name='{$prefix}apply' $checked/></div>";
		$form .= "</td></tr>";

		// both
		$label   = _x("Both", "Post Comment Event", LWS_WOOREWARDS_PRO_DOMAIN);
		$checked = $cboth;
		$form .= "<tr><td class='lcell' nowrap>";
		$form .= "<label for='{$prefix}both' class='lws-$context-opt-title'>$label</label></td>";
		$form .= "<td class='rcell'>";
		$form .= "<div class='lws-$context-opt-input'><input $attrs class='lws_radio' type='radio' id='{$prefix}both' name='{$prefix}apply' $checked/></div>";
		$form .= "</td></tr>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setPostTypes(\get_post_meta($post->ID, 'wre_event_comment_post_types', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_comment_post_types', $this->getPostTypes());
		return $this;
	}

	public function getPostTypes()
	{
		return isset($this->postTypes) && is_array($this->postTypes) ? $this->postTypes : array('post', 'page');
	}

	public function setPostTypes($types=array())
	{
		$this->postTypes = is_array($types) ? $types : array($types);
		return $this;
	}

	public function isInPostTypes($type)
	{
		return in_array($type, $this->getPostTypes());
	}

	/** @return a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Post comment", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('comment_post', array($this, 'trigger'), 10, 2);
		\add_action('comment_unapproved_to_approved', array($this, 'delayedApproval'), 10, 1);
	}

	function delayedApproval($comment)
	{
		if( $this->isValid($comment) )
			$this->process($comment);
	}

	/** When a registered customer comment a product for the very first time. */
	function trigger($comment_id, $comment_approved)
	{
		if( !empty($comment = $this->getValidComment($comment_id, $comment_approved)) )
			$this->process($comment);
	}

	protected function oncekey()
	{
		return 'lws_wre_event_comment_'.$this->getPoolName();
	}

	protected function process($comment)
	{
		\add_user_meta($comment->user_id, $this->oncekey(), $comment->comment_post_ID, false);
		$reason = sprintf(__("Post a comment about '%s'", LWS_WOOREWARDS_PRO_DOMAIN), \get_the_title($comment->comment_post_ID));
		$this->addPoint($comment->user_id, $reason);
	}

	protected function isValid($comment)
	{
		if( empty($comment->user_id) ) // not anonymous
			return false;
		if( in_array($comment->comment_post_ID, \get_user_meta($comment->user_id, $this->oncekey(), false)) ) // already commented by him
			return false;
		if( !$this->isInPostTypes(\get_post_type($comment->comment_post_ID)) ) // it is a type we looking for
			return false;
		return true;
	}

	/** @return (false|object{userId, postId} */
	protected function getValidComment($comment_id, $comment_approved)
	{
		if( !$comment_approved )
			return false;
		if( !isset($_POST['comment_post_ID']) ) // it is a comment
			return false;
		if( empty($comment = \get_comment($comment_id, OBJECT)) ) // it is a valid comment
			return false;
		if( !$this->isValid($comment) )
			return false;

		return $comment;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'site' => __("Website", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}
}

?>