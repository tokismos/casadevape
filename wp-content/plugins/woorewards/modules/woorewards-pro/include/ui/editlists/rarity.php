<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if (!defined('ABSPATH')) {
    exit();
}

/** Set the different badges rarity levels
 * Tips: prevent page nav with EditList::setPageDisplay(false) */
class BadgeRarity extends \LWS\Adminpanel\EditList\Source
{
    const ROW_ID = 'rarity_id';

    public function labels()
    {
        $labels = array(
            'percentage'  => __("Max Percentage", LWS_WOOREWARDS_PRO_DOMAIN),
            'rarity' => __("Rarity", LWS_WOOREWARDS_PRO_DOMAIN)
        );
        return \apply_filters('lws_woorewards_rarity_labels', $labels);
    }

    public function read($limit=null)
    {
		$source = $this->getCollection();
		foreach( $source as &$rarity )
		{
			$rarity['rarity_id'] = $rarity['percentage'];
		}
		return $source;
    }

    public function input()
    {
        $labels = array(
            'percentage'  => __("Max Percentage", LWS_WOOREWARDS_PRO_DOMAIN),
            'rarity' => __("Rarity", LWS_WOOREWARDS_PRO_DOMAIN)
        );

        $retour = "<div class='lws-woorewards-rarity-edit'>";
        $retour .= "<input type='hidden' name='" . self::ROW_ID . "' class='lws_woorewards_rarity_id' />";
        $retour .= "<fieldset class='col50 pdr5 lws-editlist-fieldset fieldset-0'>";
		$retour .= "<div class='lws-editlist-title'>Rarity Percentage</div>";
		$retour .= "<div class='lws-editlist-input-line'>";
		$retour .= "<div class='lws-editlist-input-line-label'>{$labels['percentage']}</div>";
        $retour .= "<div class='lws-editlist-opt-input'><input name='percentage' type='text'/></div>";
        $retour .= "</div></fieldset>";
        $retour .= "<fieldset class='col50 lws-editlist-fieldset fieldset-1'>";
        $retour .= "<div class='lws-editlist-title'>Rarity Description</div>";
		$retour .= "<div class='lws-editlist-input-line'>";
		$retour .= "<div class='lws-editlist-input-line-label'>{$labels['rarity']}</div>";
        $retour .= "<div class='lws-editlist-opt-input'><input name='rarity' type='text' /></div>";
        $retour .= "</div></fieldset>";
        $retour .= "</div>";
        return $retour;
    }

    public function write($row)
    {
		$source = $this->getCollection();
		/* Basic verifications */
		$row['rarity'] = esc_attr($row['rarity']);
		if(!is_numeric($row['percentage'])) return new \WP_Error('404', __("The percentage must be a numeric value", LWS_WOOREWARDS_PRO_DOMAIN));
		if($row['percentage']<0 || $row['percentage']>100 ) return new \WP_Error('404', __("The percentage must be number between 0 and 100", LWS_WOOREWARDS_PRO_DOMAIN));
		if(empty($row['rarity'])) return new \WP_Error('404', __("The rarity label can't be empty", LWS_WOOREWARDS_PRO_DOMAIN));

		/* New Value*/
		if(empty($row['rarity_id']))
		{
			if(!empty($source[$row['percentage']]))	return new \WP_Error('404', __("This percentage is already taken", LWS_WOOREWARDS_PRO_DOMAIN));
		}else{
			/* Percentage Change */
			if($row['rarity_id'] != $row['percentage']) unset($source[$row['rarity_id']]);
		}

		/*Update Option */
		$row['rarity_id'] = $row['percentage'];
		$source[$row['rarity_id']]['percentage'] = $row['percentage'];
		$source[$row['rarity_id']]['rarity'] = $row['rarity'];
		asort($source);
		$source = array_reverse($source,true);
		\update_option('lws_woorewards_rarity_levels', $source);

        return $row;
    }

    public function erase($row)
    {

		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$source = $this->getCollection();
			$item = $source[$id];
			if( empty($item) )
			{
				return new \WP_Error('404', __("The selected Percentage cannot be found.", LWS_WOOREWARDS_PRO_DOMAIN));
			}
			else
			{
				unset($source[$id]);
				\update_option('lws_woorewards_rarity_levels', $source);
				return true;
			}
		}
        return false;
    }

    public function getCollection()
    {
		static $collection = false;
		if( $collection === false )
		{
            $defaults = array(
				'100' => array(
					'percentage'=> 100,
					'rarity'	=> __("Common", LWS_WOOREWARDS_PRO_DOMAIN),
				),
				'50' => array(
					'percentage'=> 50,
					'rarity'	=> __("Uncommon", LWS_WOOREWARDS_PRO_DOMAIN),
				),
				'20' => array(
					'percentage'=> 20,
					'rarity'	=> __("Rare", LWS_WOOREWARDS_PRO_DOMAIN),
				),
				'10' => array(
					'percentage'=> 10,
					'rarity'	=> __("Epic", LWS_WOOREWARDS_PRO_DOMAIN),
				),
				'2' => array(
					'percentage'=> 2,
					'rarity'	=> __("Legendary", LWS_WOOREWARDS_PRO_DOMAIN),
				),
			);
            $collection = \lws_get_option("lws_woorewards_rarity_levels", $defaults);
		}
		return $collection;
    }
}
