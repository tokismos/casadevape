<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if (!defined('ABSPATH')) {
    exit();
}

/** List all special pools. */
class Achievements extends \LWS\Adminpanel\EditList\Source
{
    const ROW_ID = 'post_id';
    const SLUG = 'lws-wr-achievements';

    public function total()
    {
        return $this->getCollection()->count();
    }

    public function read($limit=null)
    {
        $pools = array();
        foreach ($this->getCollection()->asArray() as $pool) {
            $pools[] = $this->objectToArray($pool);
        }
        return $pools;
    }

    public function labels()
    {
        $labels = array(
            'image' => array(__("Badge", LWS_WOOREWARDS_PRO_DOMAIN), '56px'),
            'display_title' => __("System Title", LWS_WOOREWARDS_PRO_DOMAIN),
            'cost' => array(__("Occurence", LWS_WOOREWARDS_PRO_DOMAIN), '5em'),
            'action_descr' => __("Action", LWS_WOOREWARDS_PRO_DOMAIN),
        );
        return \apply_filters('lws_woorewards_achiavements_labels', $labels);
    }

    private function objectToArray($pool)
    {
        $data = $pool->getOptions(array(
            'title', 'display_title'
        ));

        $data['src_id'] = $data[self::ROW_ID] = $pool->getId();
        $data['image'] = $pool->getThumbnailImage();
        $data['achievement_badge'] = '';
        $data['cost'] = 1;
        $data['achievement_action'] = '';
        $data['action_descr'] = '';

        if ($badge = $pool->getTheReward()) {
            $data['achievement_badge'] = $badge->getBadgeId();
            $data['cost'] = $badge->getCost('edit');
        }

        if ($event = $pool->getEvents()->first()) {
            $data['achievement_action'] = $event->getType();
            $data['action_descr'] = $event->getDescription();
            $data = array_merge($event->getData(), $data);
        }

        return $data;
    }

    public function defaultValues()
    {
        $act = '';
        //if( $first = $this->loadChoices()->first() )
        //	$act = $first->getType();

        $values = array();
        foreach ($this->loadChoices()->asArray() as $choice) {
            $values = array_merge($values, $choice->getData());
        }

        $values = array_merge($values, array(
            self::ROW_ID        => '',
            'src_id'            => '',
            'achievement_badge' => '',
            'cost'              => 1,
            'achievement_action'=> $act,
        ));
        return $values;
    }

    /** no edition, use bulk action */
    public function input()
    {
        $labelCreate = \esc_attr(__("Create", LWS_WOOREWARDS_PRO_DOMAIN));
        $labelSave = \esc_attr(__("Save", LWS_WOOREWARDS_PRO_DOMAIN));
        $labelCopy = \esc_attr(_x(" (copy)", "title suffix at pool copy", LWS_WOOREWARDS_PRO_DOMAIN));
        $rowId = self::ROW_ID;

        $labels = array(
            'stitle' => __("Achievement settings", LWS_WOOREWARDS_PRO_DOMAIN),
            'atitle' => __("Action settings", LWS_WOOREWARDS_PRO_DOMAIN),
            'title'  => __("Title", LWS_WOOREWARDS_PRO_DOMAIN),
            'badge'  => __("Reward", LWS_WOOREWARDS_PRO_DOMAIN),
            'cost'   => __("Action occurences", LWS_WOOREWARDS_PRO_DOMAIN),
            'action' => __("Action", LWS_WOOREWARDS_PRO_DOMAIN),
        );
        $placeholders = array(
            'badge' => __("Choose a badge ...", LWS_WOOREWARDS_PRO_DOMAIN),
            'title' => __("Title (Optional)", LWS_WOOREWARDS_PRO_DOMAIN),
            'action' => __("Action to perform", LWS_WOOREWARDS_PRO_DOMAIN),
        );
        $tooltips = array(
            'cost'   => \lws_get_tooltips_html(__("The number of time the user must perform the chosen action.", LWS_WOOREWARDS_PRO_DOMAIN)),
            'action' => \lws_get_tooltips_html(__("What user must do to get the achievement.", LWS_WOOREWARDS_PRO_DOMAIN)),
        );

        $badgeInput = \LWS\Adminpanel\Pages\Field\LacSelect::compose('achievement_badge', array(
            'ajax' => 'lws_woorewards_badge_list',
            'value' => '',
            'class' => 'achievement_badge_field'
        ));

        $events = \esc_attr(base64_encode(json_encode($this->optionGroups())));
        $eventDivs = array();
        foreach ($this->loadChoices()->asArray() as $choice) {
            $choice->setPool($this->pool);
            $type = \esc_attr($choice->getType());
            $eventDivs[] = "<div data-type='$type' class='lws-wr-choice-content lws_woorewards_system_choice $type'>"
                . $choice->getForm('achievements')
                . "</div>";
        }
        $eventDivs = implode("\n", $eventDivs);

        $str = <<<EOT
<input type='hidden' class='lws_wre_pool_save_label create' value='{$labelCreate}'>
<input type='hidden' class='lws_wre_pool_save_label save' value='{$labelSave}'>
<input type='hidden' class='lws_wre_pool_copy_label' value='{$labelCopy}'>
<input type='hidden' name='{$rowId}' class='lws_woorewards_achievement_id' />
<input type='hidden' name='src_id' class='lws_woorewards_achievement_duplic' />

<div class='lws_achievement_main_settings'>
	<div class='lws-achievement-main-settings'>

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
				<tr>
					<td class='lcell' nowrap>{$labels['badge']}</td>
					<td class='rcell'>
						<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input lws-editlist-opt-badge' placeholder='{$placeholders['badge']}'>
							$badgeInput
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset class='col25 pdr5 lws-editlist-fieldset'>
			<div class='lws-editlist-title'>{$labels['atitle']}</div>
			<table class='lws-editlist-fs-table' cellpadding='0' cellspacing='0'>
				<tr>
					<td class='lcell' nowrap>{$labels['action']}</td>
					<td class='rcell'>
						<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
							<input class='lac_select lws_woorewards_system_type' name='achievement_action' data-mode='select' data-source='{$events}'  data-placeholder='{$placeholders['action']}'>
							{$tooltips['action']}
						</div>
					</td>
				</tr>
				<tr>
					<td class='lcell' nowrap>{$labels['cost']}</td>
					<td class='rcell'>
						<div class='lws-editlist-fs-table-row-rcell lws-editlist-opt-input'>
							<input type='number' name='cost' class='lws_woorewards_pool_cost' />
							{$tooltips['cost']}
						</div>
					</td>
				</tr>
			</table>
		</fieldset>


	</div>
</div>
<div class='lws_achievement_action_settings'>
	<!-- <div class='lws-editlist-title'>____</div> -->
	<div class='lws_woorewards_system_type_select lws-editlist-opt-input'>
		$eventDivs
	</div>
</div>
EOT;
        return $str;
    }

    public function write($row)
    {
        $values = \apply_filters('lws_adminpanel_arg_parse', array(
            'values'   => $row,
            'format'   => array(
                self::ROW_ID         => 'd',
                'src_id'             => 'd',
                'title'              => 't',
                'achievement_badge'  => 'D',
                'cost'               => 'D',
                'achievement_action' => 'K',
            ),
            'defaults' => array(
                self::ROW_ID => '',
                'src_id'     => '',
                'title'      => '',
            ),
            'labels'   => array(
                'title'  => __("Title", LWS_WOOREWARDS_PRO_DOMAIN),
                'achievement_badge'  => __("Reward", LWS_WOOREWARDS_PRO_DOMAIN),
                'cost'               => __("Occurence", LWS_WOOREWARDS_PRO_DOMAIN),
                'achievement_action' => __("Action", LWS_WOOREWARDS_PRO_DOMAIN),
            )
        ));
        if (!(isset($values['valid']) && $values['valid'])) {
            return isset($values['error']) ? new \WP_Error('400', $values['error']) : false;
        }

        $pool = false;
        $creation = false;

        if (isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID]))) {
            // quick update
            $pool = $this->getCollection()->find($id);
            if (empty($pool)) {
                return new \WP_Error('404', __("The selected Loyalty System cannot be found.", LWS_WOOREWARDS_PRO_DOMAIN));
            }
        } else {
            $creation = true; // new pool

            if (isset($row['src_id']) && !empty($srcId = intval($row['src_id']))) {
                // copy that source pool
                $pool = $this->getCollection()->find($srcId);
                if (empty($pool)) {
                    return new \WP_Error('404', __("The selected Loyalty System cannot be found for copy.", LWS_WOOREWARDS_PRO_DOMAIN));
                }
                $pool->detach();
            } else {
                $pool = \LWS\WOOREWARDS\PRO\Collections\Achievements::instanciate()->create('achievement')->last();
            }

            $pool->setOption('type', \LWS\WOOREWARDS\Core\Pool::T_LEVELLING);
            $pool->setOption('public', true);
        }

        if (!empty($pool)) {
            $event = $pool->getEvents()->last();
            // can we reuse existant
            if ($event && $event->getType() != $row['achievement_action']) {
                foreach ($pool->getEvents()->asArray() as $item) {
                    $pool->removeEvent($item);
                    $item->delete();
                }
                $event = false;
            }

            // create new if needed
            $action = ($event ? $event : \LWS\WOOREWARDS\Collections\Events::instanciate()->create($row['achievement_action'])->last());
            if (!$action) {
                return new \WP_Error('404', __("The selected action type cannot be found.", LWS_WOOREWARDS_PRO_DOMAIN));
            }

            if (true === ($err = $action->submit($row))) {
                $action->setMultiplier(1);
                if (!$event) {
                    $pool->addEvent($action);
                }
            } else {
                return new \WP_Error('update', $err);
            }

            $badge = $pool->getTheReward();
            if (!$badge) {
                $badge = $pool->createTheReward();
            }

            if ($badge) {
                $badge->setBadgeId($row['achievement_badge']);
                $badge->setCost($row['cost']);
            }

            if (empty($row['title'])) {
                $row['title'] = $badge->getBadge()->getTitle();
            }
            $pool->setOptions(array(
                'title' => $row['title'],
            ));

            if ($creation) {
                $pool->setName($row['title']);
            }

            $pool->ensureNameUnicity();
            $pool->save(true, true);

            $row = $this->objectToArray($pool);
            return $row;
        }
        return false;
    }

    public function erase($row)
    {
        if (is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID]))) {
            $item = $this->getCollection()->find($id);
            if (empty($item)) {
                return new \WP_Error('404', __("The selected Loyalty System cannot be found.", LWS_WOOREWARDS_PRO_DOMAIN));
            } elseif (!$item->isDeletable()) {
                return new \WP_Error('403', __("The default Loyalty Systems cannot be deleted.", LWS_WOOREWARDS_PRO_DOMAIN));
            } else {
                $item->delete();
                return true;
            }
        }
        return false;
    }

    public function getCollection()
    {
        static $collection = false;
        if ($collection === false) {
            $collection = \LWS\WOOREWARDS\PRO\Collections\Achievements::instanciate()->load();
        }
        return $collection;
    }

    protected function loadChoices()
    {
        if (!isset($this->choices)) {
            $blacklist = array();
            if (!\LWS_WooRewards::isWC()) {
                $blacklist = array_merge(array('woocommerce'=>'woocommerce'), $blacklist);
            }

            $this->choices = \LWS\WOOREWARDS\Collections\Events::instanciate()->create()->byCategory(
                $blacklist,
                array('achievement')
            )->usort(function ($a, $b) {
                return strcmp($a->getDisplayType(), $b->getDisplayType());
            });
        }
        return $this->choices;
    }

    protected function optionGroups()
    {
        $groups = \apply_filters('lws_woorewards_eventlist_type_groups', array( // use free text-domain since it is the same list
            ''             => array('label'=>'', 'options' => array()),
            'order'        => array('label'=>\esc_attr(_x("Orders", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
            'product'      => array('label'=>\esc_attr(_x("Products", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
            'periodic'     => array('label'=>\esc_attr(_x("Periodic", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
            'site'         => array('label'=>\esc_attr(_x("Website", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
            'social'       => array('label'=>\esc_attr(_x("Social network", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
            'sponsorship'  => array('label'=>\esc_attr(_x("Sponsorship", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
            'achievements' => array('label'=>\esc_attr(_x("Achievements", "Option Group", LWS_WOOREWARDS_DOMAIN)), 'options' => array()),
        ));

        foreach ($this->loadChoices()->asArray() as $choice) {
            $done = false;
            foreach ($choice->getCategories() as $cat => $name) {
                if (isset($groups[$cat])) {
                    $groups[$cat]['options'][\esc_attr($choice->getType())] = $choice->getDisplayType();
                    $done = true;
                    break;
                }
            }
            if (!$done) {
                $groups['']['options'][\esc_attr($choice->getType())] = $choice->getDisplayType();
            }
        }

        $options = array();
        foreach ($groups as $cat => $group) {
            if (!empty($group['options'])) {
                if (empty($cat)) {
                    foreach ($group['options'] as $value => $label) {
                        $options[] = array('label' => $label, 'value' => $value);
                    }
                } else {
                    $sub = array();
                    foreach ($group['options'] as $value => $label) {
                        $sub[] = array('label' => $label, 'value' => $value);
                    }
                    $options[] = array('label' => $group['label'], 'group' => $sub);
                }
            }
        }
        return $options;
    }
}
