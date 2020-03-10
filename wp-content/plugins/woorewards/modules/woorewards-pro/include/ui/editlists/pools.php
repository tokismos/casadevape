<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** List all pools (starting with prefabs)
 *	Allows add, duplicate, delete[not_prefabs]
 *	Full edit for single pool is done in a dedicated page. */
class Pools extends \LWS\Adminpanel\EditList\Source
{
	const ROW_ID = 'post_id';
	const SLUG = 'lws-wr-pools';

	static protected $filter = false;

	function __construct()
	{
		$this->tabPrefix = 'wr_loyalty.wr_upool_';
		static $once = true;
		if( $once )
		{
			\add_filter('lws_ap_editlist_item_actions_'.self::SLUG, array($this, 'quickButtons'), 10, 3);
			\add_action('lws_wr_pool_admin_options_after_update', array($this, 'afterPoolUpdate'), 10, 1);
			$once = false;
		}
	}

	/** after pool update (via options.php), the redirection should includes the new pool slug.
	 * The only way to set that is to hook into the next redirection to change location. */
	function afterPoolUpdate($pool)
	{
		if( $pool->isDeletable() ) // for prefabs, url never change whatever the name
		{
			$arg = array('tab' => $this->tabPrefix.$pool->getName());

			\add_filter('wp_redirect', function($location, $status)use($arg){
				return \add_query_arg($arg, $location);
			}, 10, 2);
		}
	}

	function quickButtons($btns, $id, $data)
	{
		if( isset($data['prefabs']) && $data['prefabs'] != 'no' )
			unset($btns['del']);

		if( isset($data['name']) )
		{
			static $label = false;
			if( $label === false )
				$label = __("Edit", LWS_WOOREWARDS_PRO_DOMAIN);
			$btns = array_merge(
				array('edit' => $this->getEditLink($data['name'], $label, 'lws_editlist_btn_edit')),
				$btns
			);
		}
		return $btns;
	}

	protected function getEditLink($slug, $label, $css='')
	{
		if( !empty($css) )
			$css = ' ' . $css;
		$url = \esc_attr(\add_query_arg(
				array(
					'page' => LWS_WOOREWARDS_PAGE.'.loyalty',
					'tab' => $this->tabPrefix.$slug
				),
				admin_url('admin.php')
			));
		return "<a href='$url' class='lws-wre-pool-edit-link$css'>$label</a>";
	}

	function total()
	{
		return self::getCollection()->count();
	}

	function read($limit=null)
	{
		$pools = array();
		foreach( self::getCollection()->asArray() as $pool )
		{
			$pools[] = $this->objectToArray($pool);
		}
		return $pools;
	}

	static public function statusList()
	{
		static $trads = false;
		if( $trads === false )
		{
			$trads = array(
				'on'  => _x("On", "editlist cell pool status", LWS_WOOREWARDS_PRO_DOMAIN),
				'off' => _x("Off", "editlist cell pool status", LWS_WOOREWARDS_PRO_DOMAIN),
				'sch' => _x("Scheduled", "editlist cell pool status", LWS_WOOREWARDS_PRO_DOMAIN),
				'run' => _x("Running", "editlist cell pool status", LWS_WOOREWARDS_PRO_DOMAIN),
				'ra'  => _x("Ended - Rewards available", "editlist cell pool status", LWS_WOOREWARDS_PRO_DOMAIN),
				'end' => _x("Ended", "editlist cell pool status", LWS_WOOREWARDS_PRO_DOMAIN),
			);
		}
		return $trads;
	}

	private function trad($key)
	{

		return self::statusList()[$key];
	}

	/** compute a rendering for actif state of pool, with date indication if any. */
	private function setStatus(&$data)
	{
		static $format = false;
		if( $format === false )
			$format = get_option('date_format');

		$dates = array();
		$txt = $this->trad('on');
		$status = 'enabled';

		if( $data['period_start'] || $data['period_mid'] || $data['period_end'] )
		{
			$txt = $this->trad('sch');
			$status = ' event';

			$now = \date_create();
			if( $data['period_start'] && $now < $data['period_start'] )
			{
				$txt = $this->trad('sch');
				$status = 'futur';
			}else{
				if( $data['period_end'] && $now <= $data['period_end'] )
				{
					$txt = $this->trad('run');
					$status = 'running';
				}
				if( $data['period_mid'] && $now >= $data['period_mid'] )
				{
					$txt = $this->trad('ra');
					$status = 'buyable';
				}
				if( $data['period_end'] && $now >= $data['period_end'] )
				{
					$txt = $this->trad('end');
					$status = 'outdated';
				}
			}

			$dates['start'] = $data['period_start'] ? date_i18n($format, $data['period_start']->getTimestamp()) : '-';
			if( !$data['period_mid'] && !$data['period_end'] )
				$dates['end'] = '-';
			else if( $data['period_mid'] && $data['period_end'] && $data['period_mid'] == $data['period_end'] )
				$dates['end'] = date_i18n($format, $data['period_end']->getTimestamp());
			else
			{
				$dates['mid'] = $data['period_mid'] ? date_i18n($format, $data['period_mid']->getTimestamp()) : '-';
				$dates['end'] = $data['period_end'] ? date_i18n($format, $data['period_end']->getTimestamp()) : '-';
			}
		}

		if( !$data['enabled'] )
		{
			$txt = $this->trad('off');
			$status = 'disabled';
		}

		$data['status'] = "<div class='lws-woorewards-list-pool-status $status'>$txt</div>";
		if( !empty($dates) )
		{
			foreach( $dates as $k => &$d )
				$d = "<span class='lws-woorewards-list-pool-date $k'>$d</span>";
			$data['status'] .= "<div class='lws-woorewards-list-pool-dates'>".implode(' / ', $dates)."</div>";
		}
		$data['period_start'] = $data['period_start'] ? $data['period_start']->format('Y-m-d') : '';
		$data['period_mid']   = $data['period_mid']   ? $data['period_mid']->format('Y-m-d') : '';
		$data['period_end']   = $data['period_end']   ? $data['period_end']->format('Y-m-d') : '';
	}

	function labels()
	{
		$labels = array(
			'display_title' => __("System Title", LWS_WOOREWARDS_PRO_DOMAIN),
			'status'        => __("Current Status", LWS_WOOREWARDS_PRO_DOMAIN),
			'behavior'      => __("Behavior", LWS_WOOREWARDS_PRO_DOMAIN),
			'eCount'        => __("Earning methods", LWS_WOOREWARDS_PRO_DOMAIN),
			'uCount'        => __("Rewards", LWS_WOOREWARDS_PRO_DOMAIN),
			'stack'         => __("Points Pool", LWS_WOOREWARDS_PRO_DOMAIN),
		);
		return \apply_filters('lws_woorewards_pools_labels', $labels);
	}

	private function objectToArray($pool)
	{
		$data = $pool->getOptions(array(
			'title', 'display_title', 'enabled', 'type', 'stack', 'period_start', 'period_mid', 'period_end', 'happening'
		));

		$data[self::ROW_ID] = $pool->getId();
		$data['feeder'] = $data['src_id'] = $pool->getId();
		$data['sharing'] = 'no'; // sharing and feeder are set for copy action and does not represent reallity.
		$data['prefabs'] = $pool->isDeletable() ? 'no' : 'yes';
		$data['name'] = $pool->getName();
		$data['display_title'] = $this->getEditLink($pool->getName(), $data['display_title'], 'row');
		$data['behavior'] = "<div class='lws-woorewards-list-pool-behavior {$data['type']}'>" . ($data['type'] == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING ? __("Levelling", LWS_WOOREWARDS_PRO_DOMAIN) : __("Standard", LWS_WOOREWARDS_PRO_DOMAIN)) . "</div>";
		$data['eCount'] = $pool->getEvents()->count();
		$data['uCount'] = $pool->getUnlockables()->count();
		$data['lifestyle'] = $data['happening'] ? 'temp' : 'perm';
		$this->setStatus($data);

		$data['enabled'] = $data['enabled'] ? 'on' : '';
		return $data;
	}

	public function defaultValues()
	{
		$values = array(
			'type'       => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			self::ROW_ID => '',
			'src_id'     => '',
			'enabled'    => '',
			'lifestyle'  => 'perm',
			'sharing'    => 'no',
			'prefabs'     => 'no'
		);
		if( self::getCollection()->count() > 0 )
			$values['feeder'] = self::getCollection()->get(0)->getId();
		return $values;
	}

	/** no edition, use bulk action */
	function input()
	{
		$labelCreate = \esc_attr(__("Create", LWS_WOOREWARDS_PRO_DOMAIN));
		$labelSave = \esc_attr(__("Save", LWS_WOOREWARDS_PRO_DOMAIN));
		$labelCopy = \esc_attr(_x(" (copy)", "title suffix at pool copy", LWS_WOOREWARDS_PRO_DOMAIN));
		$rowId = self::ROW_ID;
		$standard = \LWS\WOOREWARDS\Core\Pool::T_STANDARD;
		$levelling = \LWS\WOOREWARDS\Core\Pool::T_LEVELLING;

		$labels = array(
			'title'   => __("Title", LWS_WOOREWARDS_PRO_DOMAIN),
			'type'    => __("Behavior", LWS_WOOREWARDS_PRO_DOMAIN),
			'perm'    => __("Permanent", LWS_WOOREWARDS_PRO_DOMAIN),
			'temp'    => __("Event", LWS_WOOREWARDS_PRO_DOMAIN),
			'sharing' => __("Share points", LWS_WOOREWARDS_PRO_DOMAIN),
			'alone'   => __("Its own points", LWS_WOOREWARDS_PRO_DOMAIN),
			'lsystem'   => __("Loyalty System", LWS_WOOREWARDS_PRO_DOMAIN),
			'enabled' => __("Status", LWS_WOOREWARDS_PRO_DOMAIN),
			'period_start' => __("Start Date", LWS_WOOREWARDS_PRO_DOMAIN),
			'period_mid'   => __("Point earning end", LWS_WOOREWARDS_PRO_DOMAIN),
			'period_end'   => __("End Date", LWS_WOOREWARDS_PRO_DOMAIN),
			'stitle'   => __("System Title", LWS_WOOREWARDS_PRO_DOMAIN),
			'pbehavior'   => __("Points Behavior", LWS_WOOREWARDS_PRO_DOMAIN),
			'stype'   => __("System Type", LWS_WOOREWARDS_PRO_DOMAIN),
			'sedit'   => __("System Edition", LWS_WOOREWARDS_PRO_DOMAIN),
			'ppool'   => __("Points Pool", LWS_WOOREWARDS_PRO_DOMAIN),
		);
		$placeholders = array(
			'title' => __("Untitled", LWS_WOOREWARDS_PRO_DOMAIN),
			$standard => __("Standard", LWS_WOOREWARDS_PRO_DOMAIN),
			$levelling => __("Levelling", LWS_WOOREWARDS_PRO_DOMAIN),
		);
		$tooltips = array(
			'type' => \lws_get_tooltips_html(__("Standard: The customer spends points to buy rewards.<br/>Levelling: The customer keeps earning points and unlocks rewards automatically each time he passes a new level.", LWS_WOOREWARDS_PRO_DOMAIN)),
		);
		$sharing = '';
		foreach( self::getCollection()->asArray() as $pool )
		{
			$selected = empty($sharing) ? ' selected="selected"' : '';
			$value = \esc_attr($pool->getId());
			$sharing .= "<option value='$value'$selected>".$pool->getOption('display_title')."</option>";
		}

		$str = <<<EOT
<input type='hidden' class='lws_wre_pool_save_label create' value='{$labelCreate}'>
<input type='hidden' class='lws_wre_pool_save_label save' value='{$labelSave}'>
<input type='hidden' class='lws_wre_pool_copy_label' value='{$labelCopy}'>
<input type='hidden' name='{$rowId}' class='lws_woorewards_pool_id' />
<input type='hidden' name='src_id' class='lws_woorewards_pool_duplic' />
<input type='hidden' name='prefabs' class='lws_wre_pool_edit_prefabs_value' />

<fieldset class='col25 pdr5 lws-editlist-fieldset'>
	<div class='lws-editlist-title'>{$labels['stitle']}</div>
	<table class='lws-editlist-fs-table' cellpadding='0' cellspacing='0'>
		<tr>
			<td class='lcell' nowrap>{$labels['title']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
					<input type='text' name='title' placeholder='{$placeholders['title']}' class='lws_woorewards_pool_title' />
				</div>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset class='col25 pdr5 lws-editlist-fieldset lws_wre_pool_edit_phase_only create'>
	<div class='lws-editlist-title'>{$labels['pbehavior']}</div>
	<table class='lws-editlist-fs-table' cellpadding='0' cellspacing='0'>
		<tr>
			<td class='lcell' nowrap>{$labels['type']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
					<select name='type' class='lac_select lws_wre_pool_edit_type' data-mode='select'>
						<option value='{$standard}' selected>{$placeholders[$standard]}</option>
						<option value='{$levelling}'>{$placeholders[$levelling]}</option>
					</select>
					{$tooltips['type']}
				</div>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset class='col25 pdr5 lws-editlist-fieldset lws_wre_pool_edit_prefabs_only no'>
	<div class='lws-editlist-title'>{$labels['stype']}</div>
	<table class='lws-editlist-fs-table' cellpadding='0' cellspacing='0'>
		<tr>
			<td class='lcell' nowrap>{$labels['perm']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
					<input type='radio' name='lifestyle' value='perm' class='lws_radio lws_wre_pool_edit_lifestyle' data-baseicon='lws-icon-square-o' data-selecticon='lws-icon-square' data-selectcolor='#3fa9f5' data-size='30px' data-optclass='lws_wre_pool_edit_lifestyle'/>
				</div>
			</td>
		</tr>
		<tr>
			<td class='lcell' nowrap>{$labels['temp']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
					<input type='radio' name='lifestyle' value='temp' class='lws_radio lws_wre_pool_edit_lifestyle' data-baseicon='lws-icon-square-o' data-selecticon='lws-icon-square' data-selectcolor='#3fa9f5' data-size='30px' data-optclass='lws_wre_pool_edit_lifestyle'/>
				</div>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset class='col25 pdr5 lws-editlist-fieldset lws_wre_pool_edit_phase_only save'>
	<div class='lws-editlist-title'>{$labels['sedit']}</div>
	<table class='lws-editlist-fs-table' cellpadding='0' cellspacing='0'>
		<tr>
			<td class='lcell' nowrap>{$labels['enabled']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
					<input type='checkbox' name='enabled' class='lws_switch lws_woorewards_pool_enable' data-default='Off' data-checked='On'/>
				</div>
			</td>
		</tr>
	</table>
	<table class='lws-editlist-fs-table lws_wre_pool_edit_lifestyle_only temp'>
		<tr>
			<td class='lcell' nowrap>{$labels['period_start']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
					<input type='date' name='period_start' class='lws_woorewards_pool_period start' />
				</div>
			</td>
		</tr>
		<tr class='lws_wre_pool_edit_type_only {$standard}'>
			<td class='lcell' nowrap>{$labels['period_mid']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
					<input type='date' name='period_mid' class='lws_woorewards_pool_period mid' />
				</div>
			</td>
		</tr>
		<tr>
			<td class='lcell' nowrap>{$labels['period_end']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
					<input type='date' name='period_end' class='lws_woorewards_pool_period end' />
				</div>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset class='col25 lws-editlist-fieldset lws_wre_pool_edit_phase_only create'>
	<div class='lws-editlist-title'>{$labels['ppool']}</div>
	<table class='lws-editlist-fs-table' cellpadding='0' cellspacing='0'>
		<tr>
			<td class='lcell' nowrap>{$labels['alone']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input sharing no'>
					<input type='radio' name='sharing' value='no' class='lws_radio lws_wre_points_sharing master' data-baseicon='lws-icon-square-o' data-selecticon='lws-icon-square' data-selectcolor='#3fa9f5' data-size='30px' data-optclass='lws_wre_pool_edit_lifestyle'/>
				</div>
			</td>
		</tr>
		<tr>
			<td class='lcell' nowrap>{$labels['sharing']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input sharing yes'>
					<input type='radio' name='sharing' value='yes' class='lws_radio lws_wre_points_sharing master' data-baseicon='lws-icon-square-o' data-selecticon='lws-icon-square' data-selectcolor='#3fa9f5' data-size='30px' data-optclass='lws_wre_pool_edit_lifestyle'/>
				</div>
			</td>
		</tr>
		<tr class='lws_wre_points_sharing slave fuzzy'>
			<td class='lcell' nowrap>{$labels['lsystem']}</td>
			<td class='rcell'>
				<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
					<select name='feeder' class='lac_select' data-mode='select'>
						{$sharing}
					</select>
				</div>
			</td>
		</tr>
	</table>
</fieldset>


EOT;
		return $str;
	}

	function write($row)
	{
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'values'   => $row,
			'format'   => array(
				self::ROW_ID => 'd',
				'src_id'     => 'd',
				'title'      => 'T',
				'type'       => '/^('.\LWS\WOOREWARDS\Core\Pool::T_STANDARD.'|'.\LWS\WOOREWARDS\Core\Pool::T_LEVELLING.')$/',
				'lifestyle'  => '/^(temp|perm)$/',
				'sharing'    => '/^(yes|no)$/',
				'feeder'     => 'd',
				'enabled'    => 't',
				'period_start' => 't',
				'period_mid' => 't',
				'period_end' => 't',
			),
			'defaults' => array(
				self::ROW_ID => '',
				'src_id'     => '',
				'feeder'     => 0,
				'enabled'    => '',
				'period_start' => '',
				'period_mid' => '',
				'period_end' => '',
			),
			'labels'   => array(
				'title'  => __("Title", LWS_WOOREWARDS_PRO_DOMAIN),
				'type'   => __("Behavior", LWS_WOOREWARDS_PRO_DOMAIN),
				'lifestyle' => __("Permanent or Event", LWS_WOOREWARDS_PRO_DOMAIN),
				'sharing'   => __("Point sharing", LWS_WOOREWARDS_PRO_DOMAIN),
				'feeder'    => __("Loyalty system sharing its points", LWS_WOOREWARDS_PRO_DOMAIN),
				'enabled'   => __("Enabled", LWS_WOOREWARDS_PRO_DOMAIN),
				'period_start' => __("Start Date", LWS_WOOREWARDS_PRO_DOMAIN),
				'period_mid'   => __("Point earning end", LWS_WOOREWARDS_PRO_DOMAIN),
				'period_end'   => __("End Date", LWS_WOOREWARDS_PRO_DOMAIN),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? new \WP_Error('400', $values['error']) : false;

		$pool = false;
		$creation = false;
		$row['redirect'] = '';

		if( isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			// quick update
			$pool = self::getCollection()->find($id);
			if( empty($pool) )
				return new \WP_Error('404', __("The selected Loyalty System cannot be found.", LWS_WOOREWARDS_PRO_DOMAIN));

			$pool->setOption('disabled', empty($row['enabled']));
		}
		else
		{
			$creation = true; // new pool
			$stackId = '';

			if( $row['sharing'] == 'yes' )
			{
				$feeder = self::getCollection()->find(intval($row['feeder']));
				if( empty($feeder) )
					return new \WP_Error('404', __("Cannot find the Loyalty System sharing its points.", LWS_WOOREWARDS_PRO_DOMAIN));
				$stackId = $feeder->getStackId();
			}

			if( isset($row['src_id']) && !empty($srcId = intval($row['src_id'])) )
			{
				// copy that source pool
				$pool = self::getCollection()->find($srcId);
				if( empty($pool) )
					return new \WP_Error('404', __("The selected Loyalty System cannot be found for copy.", LWS_WOOREWARDS_PRO_DOMAIN));
				$pool->detach();
			}
			else
				$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('custom')->last();

			$pool->setOption('type', $row['type']);
			$pool->setOption('disabled', true);
			$pool->setStackId($stackId);
		}

		if( !empty($pool) )
		{
			$pool->setOptions(array(
				'title' => $row['title'],
				'happening' => $row['lifestyle'] == 'temp',
			));
			if( $row['lifestyle'] == 'temp' )
			{
				$pool->setOptions(array(
					'period_start' => $row['period_start'],
					'period_mid' => $row['period_mid'],
					'period_end' => $row['period_end'],
				));
			}

			if( $creation )
				$pool->setName($row['title']);

			$pool->ensureNameUnicity();
			$pool->save($creation, $creation);

			$row = $this->objectToArray($pool);
			$row['edit_url'] = \add_query_arg(
				array(
					'page' => LWS_WOOREWARDS_PAGE.'.loyalty',
					'tab' => $this->tabPrefix.$pool->getName()
				),
				admin_url('admin.php')
			);

			if( $creation )
				$row['redirect'] = $row['edit_url'];
			return $row;
		}
		return false;
	}

	function erase($row)
	{
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = self::getCollection()->find($id);
			if( empty($item) )
			{
				return new \WP_Error('404', __("The selected Loyalty System cannot be found.", LWS_WOOREWARDS_PRO_DOMAIN));
			}
			else if( !$item->isDeletable() )
			{
				return new \WP_Error('403', __("The default Loyalty Systems cannot be deleted.", LWS_WOOREWARDS_PRO_DOMAIN));
			}
			else
			{
				$item->delete();
				return true;
			}
		}
		return false;
	}

	static function getCollection()
	{
		static $collection = false;
		if( $collection === false )
		{
			$collection = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load();
			if( isset($_GET['poolfilter']) && !empty(self::$filter = trim($_GET['poolfilter'])) )
			{
				$collection = $collection->filter(array(\get_class(), 'passIn'));
			}
			$collection->sort();
		}
		return $collection;
	}

	/** @see $this->filter used with arg $_GET['poolfilter'] on computed status @see statusList() */
	static function passIn($pool)
	{
		$data = $pool->getOptions(array('enabled', 'period_start', 'period_mid', 'period_end'));
		$status = '';
		if( !$data['enabled'] )
		{
			$status = 'off';
		}
		else
		{
			if( $data['period_start'] || $data['period_mid'] || $data['period_end'] )
			{
				$status = 'run';
				$now = \date_create();
				if( $data['period_start'] && $now < $data['period_start'] )
					$status = 'sch';
				else if( $data['period_end'] && $now >= $data['period_end'] )
					$status = 'end';
				else if( $data['period_mid'] && $now >= $data['period_mid'] )
					$status = 'ra';
			}
			else
				$status = 'on';
		}
		return self::$filter == $status;
	}
}

?>