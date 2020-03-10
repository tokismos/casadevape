<?php
namespace LWS\Adminpanel;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\EditList") ) :

require_once 'editlist/abstract-source.php';
require_once 'editlist/class-pager.php';
require_once 'editlist/class-filter.php';
require_once 'editlist/abstract-action.php';

/** As post, display a list of item with on-the-fly edition. */
class EditList
{
	const FIX = 0x00; /// read only list
	const MOD = 0x01; /// allows row modification only
	const DEL = 0x02; /// allows delete row
	const DUP = 0x04; /// allows creation of new record via copy of existant
	const ADD = 0x08; /// allows creation of new record from scratch
	const DDD = self::MOD | self::DEL | self::DUP; /// eDit, Duplicate and Delete
	const MDA = self::MOD | self::DEL | self::ADD; /// Edit, Delete and Add
	const ALL = 0x0F; /// Allows all modification, equivalent to MOD | ADD | DEL | DUP

	private $KeyAction = 'action-uid';

	/**
	 * @param $editionId (string) is a unique id which refer to this EditList.
	 * @param $recordUIdKey (string) is the key which will be used to ensure record unicity.
	 * @param $source instance which etends EditListSource.
	 * @param $mode allows list for modification (use bitwise operation, @see ALL)
	 * @param $filtersAndActions an array of instance of EditList\Action or EditList\Filter. */
	public function __construct( $editionId, $recordUIdKey, $source, $mode = self::ALL, $filtersAndActions=array() )
	{
		$this->slug = sanitize_key($editionId);
		$this->m_Id = esc_attr($editionId);
		$this->m_UId = esc_attr($recordUIdKey);

		if( $this->m_UId != $recordUIdKey )
			error_log("!!! $recordUIdKey is not safe to be used as record key (html escape = {$this->m_UId}).");

		$sourceClass = __NAMESPACE__ . '\EditList\Source';
		if( !is_a($source, $sourceClass) )
			error_log("!!! EditList data source is not a $sourceClass");
		else
			$this->m_Source = $source;

		$this->m_Mode = $mode;
		$this->m_PageDisplay = new EditList\Pager($this->m_Id);

		if( !is_array($filtersAndActions) )
			$filtersAndActions = array($filtersAndActions);

		$this->m_Actions = array();
		$this->m_Filters = array();
		foreach( $filtersAndActions as $faa )
		{
			if( is_a($faa, __NAMESPACE__ . '\EditList\Action') )
				$this->m_Actions[] = $faa;
			else if( is_a($faa, __NAMESPACE__ . '\EditList\Filter') )
				$this->m_Filters[] = $faa;
		}

		add_action('init', array($this, 'manageActions'), 0);
		add_action('wp_ajax_lws_adminpanel_editlist', array($this, 'ajax'));
	}

	/** Apply actions */
	public function manageActions()
	{
		$this->m_Actions = \apply_filters('lws_adminpanel_editlist_actions_'.$this->slug, $this->m_Actions);
		$this->applyActions();
	}

	public function ajax()
	{
		if( isset($_REQUEST['id']) && isset($_REQUEST['method']) && isset($_REQUEST['line']) )
		{
			$method = \sanitize_key($_REQUEST['method']);
			if( !in_array($method, self::methods()) )
				exit(0);

			$id = \sanitize_text_field($_REQUEST['id']);
			$line = \sanitize_text_field($_REQUEST['line']);
			if( empty($id) || empty($line) )
				exit(0);

			$up = $this->accept($id, $method, $line);
			if( !is_null($up) )
			{
				wp_send_json($up);
				exit();
			}
		}
	}

	/**	Editlist will be splitted and grouped by given settings.
	 *	@param $groupby (array) the entries must be as follow:
	 *	*	'key'  => the grouping field, must exists in editlist rows.
	 *	*	'head' => a readonly html bloc used as group header. Use span[data-name] to allow value placing, where name are same as input names.
	 *	*	'form' => an html input bloc if grouped values are editable, where input names exist in rows. If empty, no add or edit is allowed.
	 * 	*	'add'  => (bool|string) if false, no add button set. A string should be used as add button label. True will set a default 'Add' button text.
	 *	*	'activated' => (bool) default is true, does the groupby should be activated at loading.
	 *	@return $this for method chaining */
	public function setGroupBy($groupby=array())
	{
		if( is_array($groupby) )
		{
			if( isset($groupby['key']) && !empty($groupby['key']) )
			{
				$this->groupBy = \wp_parse_args($groupby, array(
					'head' => "<span data-name='{$groupby['key']}'>&nbsp;</span>",
					'form' => '',
					'add'  => true,
					'activated' => true
				));
			}
			else
				error_log("Require an grouped by editlist[{$this->slug}] without any grouping key.");
		}
		else if( isset($this->groupBy) )
			unset($this->groupBy);
		return $this;
	}

	public function setCssClass($class)
	{
		$this->css = $class;
		return $this;
	}

	/** Display list by page (default is true)
	 * @return $this for method chaining */
	public function setPageDisplay($yes=true)
	{
		if( $yes === false || is_null($yes) )
			$this->m_PageDisplay = null;
		else if( $yes === true )
			$this->m_PageDisplay = new EditList\Pager($this->m_Id);
		else if( is_a($yes, __NAMESPACE__ . '\EditList\Pager') )
			$this->m_PageDisplay = $yes;
		else
			$this->m_PageDisplay = null;
		return $this;
	}

	protected function getGroupByForm()
	{
		$str = '';
		if( isset($this->groupBy) )
		{
			$add = '';
			if( !empty($this->groupBy['form']) && $this->groupBy['add'] && ($this->m_Mode & self::ADD) ) // no edit -> no add
			{
				if( $this->groupBy['add'] === true )
					$this->groupBy['add'] = _x("Add a group", "editlist groupby", LWS_ADMIN_PANEL_DOMAIN);
				$add = (" data-add='" . \esc_attr($this->groupBy['add']) . "'");
			}

			$str .= "<div data-groupby='{$this->groupBy['key']}'$add class='lws_editlist_groupby_settings' style='display:none;'>";

			$str .= "<div class='lws_editlist_groupby_head'>";
			$str .= "<div class='lws-editlist-groupby-header'>{$this->groupBy['head']}";
			if( !empty($this->groupBy['form']) && ($this->m_Mode & self::MOD) ) // edit
				$str .= "<button class='lws-editlist-group-btn lws_editlist_modal_edit_button lws_editlist_group_head_edit lws-icon-pencil'></button>";
			if( $this->m_Mode & self::DEL ) // del (no add -> no del)
				$str .= "<button class='lws-editlist-group-btn lws_editlist_modal_edit_button lws_editlist_group_del lws-icon-bin'></button>";
			$str .= "</div></div>";

			if( !empty($this->groupBy['form']) )
			{
				$str .= "<div class='lws_editlist_groupby_form lws_editlist_modal_form' style='display:none;'>";
				$str .= "<div class='lws-editlist-groupby-header'>{$this->groupBy['form']}";
				$str .= "<button class='lws-editlist-group-btn lws_editlist_group_form_submit lws-icon-checkmark'></button>"; // submit
				$str .= "<button class='lws-editlist-group-btn lws_editlist_group_form_cancel lws-icon-cross'></button>"; // submit
				$str .= "</div></div>";
			}

			$str .= "</div>";
		}
		return $str;
	}

	/**	Echo the list as a <table> */
	public function display()
	{
		$dataGrpBy = (isset($this->groupBy) && $this->groupBy['activated']) ? " data-groupby='on'" : '';
		$class = 'lws_editlist lws-master-editlist';
		if( isset($this->css) )
			$class .= (' '.$this->css);

		echo "<div id='{$this->m_Id}' class='$class'$dataGrpBy>";
		if( isset($this->groupBy) )
			echo $this->getGroupByForm();

		$rcount = -1;
		$limit = null;
		$this->displayFilters($rcount, $limit, true);

		$head = $this->completeLabels(\apply_filters('lws_adminpanel_editlist_labels_'.$this->slug, $this->m_Source->labels()));
		$popup = '';
		if( isset($this->actionResult) && !empty($this->actionResult) )
			$popup = " data-popup='" . base64_encode($this->actionResult) . "'";

		echo "<table class='lws_editlist_table wp-list-table widefat striped lws-editlist' data-editlist='{$this->m_Id}' uid='{$this->m_UId}'$popup>";
		echo "<thead>";
		echo ($thead = $this->getHead($head));
		echo "</thead><tbody class='lws_editlist_table_body' data-body=''>"; /// use data-body to join add button and table body

		echo $this->getEditionForm(count($head));
		echo $this->getRow(array(), $head); // template line
		$table = \apply_filters('lws_adminpanel_editlist_read_'.$this->slug, $this->m_Source->read($limit), $limit);
		foreach( $table as $tr )
			echo $this->getRow($tr, $head); // data line

		echo "</tbody>";
		if( !isset($this->repeatHead) || $this->repeatHead ) // default true
			echo "<tfoot>$thead</tfoot>";
		echo "</table>";

		echo $this->getAddButton();

		echo "<div class='lws-editlist-bottom-line'>";
		if( !empty($this->m_Actions) )
			$this->displayActions($this->m_Actions);

		$this->displayFilters($rcount, $limit, false);
		echo "</div>";

		foreach( ($deps = array('jquery', 'jquery-ui-core', 'jquery-ui-dialog' , 'lws-base64', 'lws-tools')) as $dep )
			\wp_enqueue_script($dep);
		\wp_register_script('lws-adminpanel-editlist', LWS_ADMIN_PANEL_JS.'/editlist.js', $deps, LWS_ADMIN_PANEL_VERSION, true);
		\wp_localize_script('lws-adminpanel-editlist', 'lws_editlist_ajax_url', \add_query_arg('action', 'lws_adminpanel_editlist', \admin_url('/admin-ajax.php')));
		\wp_enqueue_script('lws-adminpanel-editlist');
		\wp_enqueue_script('lws-adminpanel-editlist-filters', LWS_ADMIN_PANEL_JS.'/editlistfilters.js', $deps, LWS_ADMIN_PANEL_VERSION, true);

		echo "</div>";
	}

	/** default is true: repeat head in footer.
	 * @return $this for method chaining */
	function setRepeatHead($yes=true)
	{
		$this->repeatHead = $yes;
		return $this;
	}

	protected function displayFilters(&$rcount, &$limit, $above=true)
	{
		$class = $above ? " lws-editlist-above" : " lws-editlist-below";
		if( !is_null($this->m_PageDisplay) )
		{
			if( !$above ) echo "<br/>";
			echo "<div class='lws-editlist-filters$class {$this->m_Id}-filters'>";
			$hide = $above ? '' : "style='display:none;'";
			if( $filters = \apply_filters('lws_adminpanel_editlist_filters_'.$this->slug, $this->m_Filters) )
			{
				echo "<div $hide class='lws-editlist-filters-first-line'>";
				foreach( $filters as $filter )
				{
					$c = $filter->cssClass();
					echo "<div class='$c'>";
					echo $filter->input($above);
					echo "</div>";
				}
				echo "</div>";
			}
			if( is_null($limit) )
			{
				$rcount = \apply_filters('lws_adminpanel_editlist_total_'.$this->slug, $this->m_Source->total());
				$limit = $this->m_PageDisplay->readLimit($rcount);
			}
			echo $this->m_PageDisplay->navDiv($rcount, $limit);
			echo "</div>";
		}
	}

	protected function displayActions()
	{
		$ph = __('Apply', LWS_ADMIN_PANEL_DOMAIN);
		echo "<div class='lws_editlist_actions'>";
		echo "<div class='lws-editlist-actions-cont'>";
		echo "<div class='lws-editlist-actions-left'><div class='lws-editlist-actions-icon lws-icon-arrow-right2'></div></div>";
		echo "<div class='lws-editlist-actions-right'>";
		$first = true;
		foreach( $this->m_Actions as $action )
		{
			if($first){$first=false;}else{echo "<div class='lws-editlist-action-sep'></div>";}
			echo "<div class='lws-editlist-action' data-id='{$this->m_Id}'>";
			echo "<input type='hidden' name='{$this->KeyAction}' value='{$action->UID}'>";
			echo $action->input();
			echo "<button class='lws-adm-btn lws-editlist-action-trigger'>$ph</button>";
			echo "</div>";
		}
		echo "</div></div></div>";
	}

	protected function completeLabels($lab)
	{
		foreach( array_keys($lab) as $k )
		{
			if( !is_array( $lab[$k] ) )
				$lab[$k] = array($lab[$k]);
			while( count($lab[$k]) < 2 )
				$lab[$k][] = '';
		}
		return $lab;
	}

	protected function getAddButton()
	{
		$button = '';
		if( $this->m_Mode & self::ADD )
		{
			$ph = \apply_filters('lws_ap_editlist_button_add_value_'.$this->slug, __("Add", LWS_ADMIN_PANEL_DOMAIN), $this->m_Mode);

			if( !empty($ph) ) /// use data-body to join add button and table body
				$button = "<button class='lws-adm-btn lws_editlist_modal_edit_button lws-editlist-add lws_editlist_item_add' data-body='' data-id='{$this->m_Id}'>$ph</button>";
		}
		return $button;
	}

	protected function getHead($head)
	{
		$tr = "<tr class='lws-editlist-header lws-editlist-row'>";
		$first = ' column-title column-primary';

		if( !empty($this->m_Actions) )
		{
			$width = " style='width:20px'";
			$chk = "<input type='checkbox' class='lws_editlist_check_selectall lws-ignore-confirm'>";
			$tr .= "<th class='lws-editlist-cell lws-editlist-checkbox manage-column'$width>$chk</th>";
		}

		foreach( $head as $key => $label )
		{
			$width = '';
			if( !empty($label[1]) )
				$width = " style='width:{$label[1]}'";
			$tr .= "<th class='lws_editlist_td lws-editlist-cell manage-column$first' data-key='$key'$width>{$label[0]}</th>";
			$first = '';
		}

		$tr .= "</tr>";
		return $tr;
	}

	protected function getRow($tr, $head)
	{
		$open = '<tr>';
		$close = '</tr>';
		$cells = '';

		if( empty($tr) )
		{
			// template line
			$data = "";
			$dft = \apply_filters('lws_adminpanel_editlist_default_'.$this->slug, $this->m_Source->defaultValues());
			if( !empty($dft) && is_array($dft) )
			{
				$decode = array();
				foreach( $dft as $k => $v )
					$decode[$k] = html_entity_decode($v);
				$data = base64_encode(json_encode($decode));
			}
			$open = "<tr data-template='1' class='lws-editlist-row lws_editlist_template' data-line='$data' style='display:none'>";
		}
		else
		{
			$decode = array();
			foreach( $tr as $k => $v )
				$decode[$k] = html_entity_decode($v);
			$data = base64_encode(json_encode($decode));
			$open = "<tr class='lws-editlist-row lws_editlist_row_editable' data-line='$data'>";
		}

		if( !empty($this->m_Actions) )
		{
			$id = "";
			if( isset($tr[$this->m_UId]) )
			{
				$encoded = base64_encode($tr[$this->m_UId]);
				$id = " data-id='$encoded'";
			}
			$chk = "<input type='checkbox'$id class='lws_editlist_check_selectitem lws-ignore-confirm'>";
			$cells .= "<th class='lws-editlist-cell lws-editlist-checkbox'>$chk</th>";
		}

		$first = true;
		foreach( $head as $id => $td )
		{
			$primaryclass = ($first == true) ? 'title column-title column-primary' : '';
			$cell = isset($tr[$id]) ? $tr[$id] : '';
			$cells .= "<td class='lws_editlist_td lws-editlist-cell $primaryclass' data-colname='{$td[0]}' data-key='$id'>$cell";
			if( $first )
			{
				$cells .= $this->getInRowButtons(isset($tr[$this->m_UId]) ? $tr[$this->m_UId] : null, $tr);
				$cells .= "<button class='toggle-row' type = 'button'></button>";
			}
			$first = false;
			$cells .= "</td>";
		}

		return $open.$cells.$close;
	}

	protected function getEditionForm($colspan=1)
	{
		$firstCol = "";
		if( !empty($this->m_Actions) )
			$firstCol = "<td></td>";

		$form = "<tr class='lws-editlist-row' style='display:none'>$firstCol<td colspan='$colspan'></td></tr>";
		$form .= "<tr class='lws-editlist-row lws_editlist_line_form lws_editlist_modal_form' style='display:none'>$firstCol<td colspan='$colspan'>";

		$form .= "<div class='lws-editlist-line-inputs'>";
		$form .= \apply_filters('lws_adminpanel_editlist_input_'.$this->slug, $this->m_Source->input());
		$form .= "</div>";

		$ph = array(
			'cancel' => __('Cancel', LWS_ADMIN_PANEL_DOMAIN),
			'save'   => __('Save', LWS_ADMIN_PANEL_DOMAIN)
		);
		$form .= "<div class='lws-editlist-line-btns'>";
		$form .= "<button class='button lws-adm-btn lws-editlist-btn-cancel'>{$ph['cancel']}</button>";
		$form .= "<button class='button lws-adm-btn lws-editlist-btn-save'>{$ph['save']}</button>";
		$form .= "</div>";

		$form .= "</td></tr>";
		return $form;
	}

	// the button line which appear under each line.
	protected function getInRowButtons($id, $data)
	{
		$sep = "<span class='lws-editlist-btn-sep'>|</span>";
		$ph = apply_filters('lws_ap_editlist_item_action_names_' . $this->slug, array(
			self::DEL => __('Delete', LWS_ADMIN_PANEL_DOMAIN),
			self::DUP => __('Copy', LWS_ADMIN_PANEL_DOMAIN),
			self::MOD => __('Quick Edit')
		), $id, $data);
		$row = '';
		$btns = array();

		if( $this->m_Mode & self::DEL )
			$btns['del'] = "<span class='lws-editlist-btn-del'>{$ph[self::DEL]}</span>";
		if( $this->m_Mode & self::DUP )
			$btns['dup'] = "<span class='lws-editlist-btn-dup'>{$ph[self::DUP]}</span>";
		if( $this->m_Mode & self::MOD )
			$btns['mod'] = "<span class='lws-editlist-btn-mod'>{$ph[self::MOD]}</span>";

		$btns = apply_filters('lws_ap_editlist_item_actions_' . $this->slug, $btns, $id, $data);
		if( !empty($btns = implode($sep, $btns)) )
			$row = "<br/><div class='lws-editlist-buttons' style='visibility: hidden;'>$btns</div>";
		return $row;
	}

	/// @return an array with accepted method value.
	static public function methods()
	{
		return array("put", "del");
	}

	/**	Test if this instance is concerne (based on $editionId),
	 *	then save the $line. @see write().
	 * 	or return a list of the lines. @see read().
	 * 	or delete a line. @see erase().
	 * 	or null if not concerned.
	 *	ajax {action: 'editlist', method: 'put', id: "?", line: {json ...}} */
	public function accept($editionId, $method, $line)
	{
		if( $editionId === $this->m_Id )
		{
			$data = json_decode( base64_decode($line), true );
			if( $method === "put" )
			{
				$result = array( "status" => 0 );
				$data = \apply_filters('lws_adminpanel_editlist_write_'.$this->slug, $this->m_Source->write($data));
				if( \is_wp_error($data) )
				{
					$result["error"] = $data->get_error_message();
				}
				else if( \LWS\Adminpanel\EditList\UpdateResult::isA($data) )
				{
					$result["status"] = $data->success ? 1 : 0;
					if( $data->success )
					{
						$result["line"] = base64_encode(json_encode($data->data));
						if( !empty($data->message) )
							$result["message"] = $data->message;
					}
					else if( !empty($data->message) )
						$result["error"] = $data->message;
				}
				else if( $data !== false )
				{
					$result["status"] = 1;
					$result["line"] = base64_encode(json_encode($data));
				}
				return $result;
			}
			else if( $method === "del" )
			{
				return array( "status" => (\apply_filters('lws_adminpanel_editlist_erase_'.$this->slug, $this->m_Source->erase($data)) ? 1 : 0) );
			}
		}
		return null;
	}

	/** If any local action match the posted action uid,
	 * we apply it on the posted selection.
	 * Then, unset the uid from $_POST to ensure it is done only once. */
	protected function applyActions()
	{
		$keyItems = 'action-items';
		if( isset($_POST[$this->KeyAction]) && !empty($_POST[$this->KeyAction])
			&& isset($_POST[$keyItems]) && !empty($_POST[$keyItems]) )
		{
			$uid = sanitize_key($_POST[$this->KeyAction]);
			$items = json_decode( base64_decode($_POST[$keyItems]), true );
			foreach( $this->m_Actions as $action )
			{
				if( $uid == $action->UID )
				{
					$ret = $action->apply($items);
					if( !empty($ret) && is_string($ret) )
						$this->actionResult = $ret;
					unset($_POST[$this->KeyAction]);
					break;
				}
			}
		}
	}

}

endif
?>
