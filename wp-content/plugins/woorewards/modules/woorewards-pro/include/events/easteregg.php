<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points the first time a customer clic on easter egg image hidden somewhere in frontend.
 * Clickable element are dedicated shortcode managed in Ui namespace.
 * Each clickable element is available only once per user, hidden if visitor is log off.
 *
 * 2 images for 2 states: For seek and visited.
 * Provide a shortcode in exchange that can be hidden by a site's author. */
class EasterEgg extends \LWS\WOOREWARDS\Abstracts\Event
{

	function getDescription($context='backend')
	{
		$img = !empty($this->getImage()) ? $this->getImageHtml(false) : '';
		if( empty($img) )
			$img = "<span class='lws-icon-image' style='font-size: 2em;'></span>";

		if( $context == 'backend' )
		{
			$descr = "<span>". _x("Customers will seek for this image : ", "EasterEgg image backend text", LWS_WOOREWARDS_PRO_DOMAIN) . '&nbsp;';
			$descr .= $img;
			$descr .= "</span>";

			if( !empty($this->getVisitedImage()) )
			{
				$descr .= "<br/><span>". _x("If clicked, the image will turn into :", "EasterEgg image after found", LWS_WOOREWARDS_PRO_DOMAIN)
					. ' ' . $this->getImageHtml(true) . "</span>";
			}

			$shortcode = sprintf(
				"%s <span class='lws-editlist-field-copy lws_ui_value_copy'><div class='lws-editlist-field-copy-text content' tabindex='0'>[lws_easteregg p=%d]</div><div class='lws-editlist-field-copy-icon lws-icon-copy1 copy'></div></span>",
				__("Copy this shortcode somewhere on your site", LWS_WOOREWARDS_PRO_DOMAIN),
				$this->getId()
			);

			return "<div class='lws-easteregg-descr'>$descr</div><div class='lws-easteregg-shortcode'>$shortcode</div>";
		}
		else
		{
			$descr = "<span class='lws-wr-easteregg-clue'>". _x("Seek for", "EasterEgg image frontend text", LWS_WOOREWARDS_PRO_DOMAIN) . "</span> ";
			$descr .= $img;
			return $descr;
		}
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'egg']     = $this->getImage();
		$data[$prefix.'egg_url'] = $this->getImageUrl(false);
		$data[$prefix.'visited_egg']     = $this->getVisitedImage();
		$data[$prefix.'visited_egg_url'] = $this->getImageUrl(true);
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$str = parent::getForm($context);
		$str .= $this->getFieldsetBegin(2, __("Images", LWS_WOOREWARDS_PRO_DOMAIN), 'col50');

		// add for seek image here
		$label = _x("Seek", "Frontend easter egg image", LWS_WOOREWARDS_PRO_DOMAIN);
		$str .= "<div class='lws-editlist-field-help'>";
		$str .= "<div class='lws-editlist-field-help-icon lws-icon-info'></div>";
		$str .= "<div class='lws-editlist-field-help-text'>";
		$str .= __("Let your customers hunt for images across your website.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>";
		$str .= __("The first image is the one they have to find and click on.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>";
		$str .= __("The second image (optional) will be displayed after the users clicks on the first one.", LWS_WOOREWARDS_PRO_DOMAIN)."<br/>";
		$str .= "<tr><td class'lcell' nowrap><label class='lws-$context-opt-title'>$label</label></td>";
		$str .= "<td class='rcell'>";
		$str .= \LWS\Adminpanel\Pages\Field\Media::compose($prefix.'egg', array(
			'value'        => $this->getImage(),
			'type'         => 'image',
			'size'         => 'thumbnail',
			'classSize'    => 'lws_wr_thumbnail',
			'urlInputName' => $prefix.'egg_url'
		));
		$str .= "</td></tr>";

		// add visited image here
		$label = _x("Found", "Frontend easter egg image", LWS_WOOREWARDS_PRO_DOMAIN);
		$str .= "<tr><td class'lcell' nowrap><label class='lws-$context-opt-title'>$label</label></td>";
		$str .= "<td class='rcell'>";
		$str .= \LWS\Adminpanel\Pages\Field\Media::compose($prefix.'visited_egg', array(
			'value'        => $this->getVisitedImage(),
			'type'         => 'image',
			'size'         => 'thumbnail',
			'classSize'    => 'lws_wr_thumbnail',
			'urlInputName' => $prefix.'visited_egg_url'
		));
		$str .= "</td></tr>";

		$str .= $this->getFieldsetEnd(2);
		return $str;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'egg'         => 'd',
				$prefix.'visited_egg' => 'd'
			),
			'defaults' => array(
				$prefix.'egg'         => '0',
				$prefix.'visited_egg' => '0'
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setImage       ($values['values'][$prefix.'egg']);
			$this->setVisitedImage($values['values'][$prefix.'visited_egg']);
		}
		return $valid;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setImage       (intval(\get_post_meta($post->ID, 'wre_event_egg',         true)));
		$this->setVisitedImage(intval(\get_post_meta($post->ID, 'wre_event_visited_egg', true)));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_egg', $this->getImage());
		\update_post_meta($id, 'wre_event_visited_egg', $this->getVisitedImage());
		return $this;
	}

	/** @return int id */
	public function getImage()
	{
		$id = isset($this->image) ? $this->image : 0;
		if( $id && !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
			$id = \apply_filters('wpml_object_id', $id, 'attachment', true);
		return $id;
	}

	/** @return int id */
	public function getVisitedImage()
	{
		$id = isset($this->visitedImage) ? $this->visitedImage : 0;
		if( $id && !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
			$id = \apply_filters('wpml_object_id', $id, 'attachment', true);
		return $id;
	}

	public function setImage($id)
	{
		$this->image = $id;
		return $this;
	}

	public function setVisitedImage($id)
	{
		$this->visitedImage = $id;
		return $this;
	}

	/** @return string url */
	public function getImageUrl($visited=false)
	{
		if( empty($img = ($visited ? $this->getVisitedImage() : $this->getImage())) )
			return '';
		else
			return \wp_get_attachment_url($img);
	}

	/** @return html <img> */
	public function getImageHtml($visited=false, $size='lws_wr_thumbnail')
	{
		if( empty($img = ($visited ? $this->getVisitedImage() : $this->getImage())) )
			return '';
		else
			return \wp_get_attachment_image($img, $size, false, array('class'=>'lws-wr-easteregg lws-wr-event-easteregg'));
	}

	/** @return a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Easter Egg", "getDisplayType", LWS_WOOREWARDS_PRO_DOMAIN);
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_easteregg', array($this, 'listener'), 10, 2);
	}

	/** is really the first time for that user on that easter egg? */
	function listener($userId, $eggId)
	{
		if( $eggId != $this->getId() )
			return;

		$done = \get_user_meta($userId, 'lws_woorewards_easteregg', false);
		if( in_array($eggId, $done) )
			return;

		$this->addPoint($userId, $this->getTitle());
		\add_user_meta($userId, 'lws_woorewards_easteregg', $eggId, false);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'site' => __("Website", LWS_WOOREWARDS_PRO_DOMAIN),
			'playful' => __("Fun activities", LWS_WOOREWARDS_PRO_DOMAIN)
		));
	}
}

?>