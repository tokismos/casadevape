<?php
namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Edit point earning amouts and the way to get them.
 * Tips: prevent page nav with EditList::setPageDisplay(false) */
class EventList extends \LWS\Adminpanel\EditList\Source
{
	const ROW_ID = 'post_id';

	function __construct(\LWS\WOOREWARDS\Core\Pool $pool)
	{
		$this->pool = $pool;
	}

	function labels()
	{
		$labels = array(
			'earning'     => array(__("Earned points", LWS_WOOREWARDS_DOMAIN), '10%'),
			'title'       => __("Public title", LWS_WOOREWARDS_DOMAIN),
			'description' => __("Action to perform", LWS_WOOREWARDS_DOMAIN)
		);
		return \apply_filters('lws_woorewards_eventlist_labels', $labels);
	}

	function read($limit=null)
	{
		$events = array();
		foreach( $this->pool->getEvents()->asArray() as $event )
		{
			$events[] = $this->objectToArray($event);
		}
		return $events;
	}

	private function objectToArray($item)
	{
		return array_merge(
			array(
				self::ROW_ID  => $item->getId(), // it is important that id is first for javascript purpose
				'wre_type'    => $item->getType(),
				'earning'     => "<div class='lws-wr-event-multiplier'>".$item->getMultiplier('view')."</div>",
				'title'       => "<div class='lws-wr-event-title'>".$item->getTitle()."</div>",
				'description' => $item->getDescription()
			),
			$item->getData()
		);
	}

	protected function loadChoices()
	{
		if( !isset($this->choices) )
		{
			$blacklist = $this->pool->getOption('blacklist');
			if( !\LWS_WooRewards::isWC() )
				$blacklist = array_merge(array('woocommerce'=>'woocommerce'), is_array($blacklist)?$blacklist:array());

			$this->choices = \LWS\WOOREWARDS\Collections\Events::instanciate()->create()->byCategory(
				$blacklist,
				$this->pool->getOption('whitelist'),
				$this->pool->getEvents()->getTypes()
			)->usort(function($a, $b){return strcmp($a->getDisplayType(), $b->getDisplayType());});
		}
		return $this->choices;
	}

	public function defaultValues()
	{
		$values = array();
		foreach( $this->loadChoices()->asArray() as $choice )
			$values = array_merge($values, $choice->getData());

		$item = $this->loadChoices()->get(0);
		return array_merge($values, array(
			self::ROW_ID => '', // it is important that id is reset and first for javascript purpose
			'wre_type'   => (empty($item) ? '' : $item->getType())
		));
	}

	/** no edition, use bulk action */
	function input()
	{
		$divs = array();
		foreach( $this->loadChoices()->asArray() as $choice )
		{
			$choice->setPool($this->pool);
			$type = \esc_attr($choice->getType());
			$divs[] = "<div data-type='$type' class='lws-wr-choice-content lws_woorewards_system_choice $type'>"
				. $choice->getForm('editlist')
				. "</div>";
		}

		return "<div class='lws-woorewards-system-edit lws_woorewards_system_master lws-woorewards-eventlist-edit'>"
			. "<input type='hidden' name='" . self::ROW_ID . "' class='lws_woorewards_system_id' />"
			. "<div class='lws_woorewards_system_type_select lws-editlist-opt-input'>"
			. "<select class='lac_select lws_woorewards_system_type' name='wre_type' data-mode='select'>" . $this->optionGroups() . "</select>"
			. "</div>"
			. implode("\n", $divs)
			. "</div>";
	}

	protected function optionGroups()
	{
		$groups = \apply_filters('lws_woorewards_eventlist_type_groups', array(
			''             => array('label'=>'', 'options' => array()),
			'order'        => array('label'=>\esc_attr(_x("Orders", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
			'product'      => array('label'=>\esc_attr(_x("Products", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
			'periodic'     => array('label'=>\esc_attr(_x("Periodic", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
			'site'         => array('label'=>\esc_attr(_x("Website", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
			'social'       => array('label'=>\esc_attr(_x("Social network", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
			'sponsorship'  => array('label'=>\esc_attr(_x("Sponsorship", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
			'achievements' => array('label'=>\esc_attr(_x("Achievements", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
		));

		foreach( $this->loadChoices()->asArray() as $choice )
		{
			$done = false;
			foreach( $choice->getCategories() as $cat => $name )
			{
				if( isset($groups[$cat]) )
				{
					$groups[$cat]['options'][\esc_attr($choice->getType())] = $choice->getDisplayType();
					$done = true;
					break;
				}
			}
			if( !$done )
				$groups['']['options'][\esc_attr($choice->getType())] = $choice->getDisplayType();
		}

		$options = '';
		foreach( $groups as $cat => $group )
		{
			if( !empty($group['options']) )
			{
				if( !empty($cat) )
					$options .= "\n<optgroup label='{$group['label']}'>";
				foreach( $group['options'] as $value => $label )
					$options .= "\n\t<option value='{$value}'>{$label}</option>";
				if( !empty($cat) )
					$options .= "\n</optgroup>";
			}
		}
		return $options;
	}

	function write($row)
	{
		$item = null;
		$type = (is_array($row) && isset($row['wre_type'])) ? trim($row['wre_type']) : '';
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = $this->pool->getEvents()->find($id);
			if( empty($item) )
				return new \WP_Error('404', __("The selected Earning Points System cannot be found.", LWS_WOOREWARDS_DOMAIN));
			if( $type != $item->getType() )
				return new \WP_Error('403', __("Earning Points System Type cannot be changed. Delete this and create a new one instead.", LWS_WOOREWARDS_DOMAIN));
		}
		else if( !empty($type) )
		{
			$item = \LWS\WOOREWARDS\Collections\Events::instanciate()->create($type)->last();
			if( empty($item) )
				return new \WP_Error('404', __("The selected Earning Points System type cannot be found.", LWS_WOOREWARDS_DOMAIN));
		}

		if( !empty($item) )
		{
			if( true === ($err = $item->submit($row)) )
			{
				$item->save($this->pool);
				return $this->objectToArray($item);
			}
			else
				return new \WP_Error('update', $err);
		}
		return false;
	}

	function erase($row)
	{
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = $this->pool->getEvents()->find($id);
			if( empty($item) )
			{
				return new \WP_Error('404', __("The selected Earning Point System cannot be found.", LWS_WOOREWARDS_DOMAIN));
			}
			else
			{
				$this->pool->removeEvent($item);
				$item->delete();
				return true;
			}
		}
		return false;
	}
}

?>