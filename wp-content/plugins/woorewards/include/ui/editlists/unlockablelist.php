<?php
namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Edit what can be bought with points.
 * Tips: prevent page nav with EditList::setPageDisplay(false) */
class UnlockableList extends \LWS\Adminpanel\EditList\Source
{
	const ROW_ID = 'post_id';

	function __construct(\LWS\WOOREWARDS\Core\Pool $pool)
	{
		$this->pool = $pool;
	}

	function labels()
	{
		$labels = array(
			'purchasing'  => array(__("Points cost", LWS_WOOREWARDS_DOMAIN), '10%'),
			'title'       => __("Public title", LWS_WOOREWARDS_DOMAIN),
			'description' => __("Reward descriptions", LWS_WOOREWARDS_DOMAIN)
		);
		return \apply_filters('lws_woorewards_unlockablelist_labels', $labels);
	}

	function read($limit=null)
	{
		$unlockables = array();
		if( $this->pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_STANDARD )
			$this->pool->getUnlockables()->sort();
		foreach( $this->pool->getUnlockables()->asArray() as $unlockable )
		{
			$unlockables[] = $this->objectToArray($unlockable);
		}
		return $unlockables;
	}

	private function objectToArray($item)
	{
		$descr = trim($item->getCustomDescription(false));
		return array_merge(
			array(
				self::ROW_ID  => $item->getId(), // it is important that id is first for javascript purpose
				'wre_type'    => $item->getType(),
				'purchasing'  => "<div class='lws-wr-unlockable-cost'>".$item->getCost('view')."</div>",
				'title'       => $item->getThumbnailImage('lws_wr_thumbnail_small')."<div class='lws-wr-unlockable-title'>".$item->getTitle()."</div>",
				'description' => $descr ? $descr : $item->getDescription(),
				'cost'        => $item->getCost()
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

			$this->choices = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create()->byCategory(
				$blacklist,
				$this->pool->getOption('whitelist'),
				$this->pool->getUnlockables()->getTypes()
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
			self::ROW_ID    => '', // it is important that id is reset and first for javascript purpose
			'wre_type'      => (empty($item) ? '' : $item->getType()),
			'grouped_title' => ''
		));
	}

	/** no edition, use bulk action */
	function input()
	{
		$options = array();
		$divs = array();
		foreach( $this->loadChoices()->asArray() as $choice )
		{
			$choice->setPool($this->pool);
			$type = \esc_attr($choice->getType());
			$title = $choice->getDisplayType();

			$options[] = "<option value='$type'>" . $title . "</option>";

			$divs[] = "<div data-type='$type' class='lws-wr-choice-content lws_woorewards_system_choice $type'>"
				. $choice->getForm('editlist')
				. "</div>";
		}

		return "<div class='lws-woorewards-system-edit lws_woorewards_system_master lws-woorewards-unlockablelist-edit'>"
			. "<input type='hidden' name='" . self::ROW_ID . "' class='lws_woorewards_system_id' />"
			. "<input type='hidden' name='grouped_title' />"
			. "<input type='hidden' name='cost' class='lws_wr_unlockable_master_cost' />"
			. "<div class='lws_woorewards_system_type_select lws-editlist-opt-input'>"
			. "<select class='lac_select lws_woorewards_system_type' name='wre_type' data-mode='select'>" . implode('', $options) . "</select>"
			. "</div>"
			. implode("\n", $divs)
			. "</div>";
	}

	function write($row)
	{
		$item = null;
		$type = (is_array($row) && isset($row['wre_type'])) ? trim($row['wre_type']) : '';
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = $this->pool->getUnlockables()->find($id);
			if( empty($item) )
				return new \WP_Error('404', __("The selected reward cannot be found.", LWS_WOOREWARDS_DOMAIN));
			if( $type != $item->getType() )
				return new \WP_Error('403', __("The reward type cannot be changed. Delete this and create a new one instead.", LWS_WOOREWARDS_DOMAIN));
		}
		else if( !empty($type) )
		{
			$item = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create($type)->last();
			if( empty($item) )
				return new \WP_Error('404', __("The selected reward type cannot be found.", LWS_WOOREWARDS_DOMAIN));
		}

		if( !empty($item) )
		{
			if( isset($_REQUEST['groupedBy']) && boolval($_REQUEST['groupedBy']) && isset($row['cost']) )
			{
				$row[$item->getDataKeyPrefix().'cost'] = $row['cost'];
			}

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
			$item = $this->pool->getUnlockables()->find($id);
			if( empty($item) )
			{
				return new \WP_Error('404', __("The selected reward cannot be found.", LWS_WOOREWARDS_DOMAIN));
			}
			else
			{
				$this->pool->removeUnlockable($item);
				$item->delete();
				return true;
			}
		}
		return false;
	}
}

?>