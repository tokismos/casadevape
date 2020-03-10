<?php
namespace LWS\WOOREWARDS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Save Pool options from Admin screen.
 * Use a faky hidden field named 'lws-wr-pool-option' as entry point
 * and save relevant fields as pool post. */
class Options
{
	private $optPool = 'lws-wr-pool-option';
	private $poolOptionPrefix = 'lws-wr-pool-option-';
	private $pagePool = LWS_WOOREWARDS_PAGE.'.loyalty';

	function __construct()
	{
		\add_action('pre_update_option_'.$this->optPool, array($this, 'savePool'), 9, 3);
		\add_filter('whitelist_options', array($this, 'whitelistPoolOptions'), 99999);
	}

	/** @return bool */
	function isOptionPage()
	{
		if( !isset($_POST['tab']) || false === strpos($_POST['tab'], 'wr_loyalty') )
			return false;

		$vars = array(
			'action' => 'update',
			'option_page' => LWS_WOOREWARDS_PAGE.'.loyalty'
		);
		foreach( $vars as $var => $value )
		{
			if( !isset($GLOBALS[$var]) || $GLOBALS[$var] != $value )
				return false;
		}

		return true;
	}

	/** grab pool options */
	function whitelistPoolOptions($whitelistOptions)
	{
		if( $this->isOptionPage() && isset($whitelistOptions[$this->pagePool]) )
		{
			$lastOptions = array($this->optPool);
			$this->whitelist = array_diff($whitelistOptions[$this->pagePool], $lastOptions);
			$whitelistOptions[$this->pagePool] = $lastOptions;
		}
		return $whitelistOptions;
	}

	/** Save any relevant $_POST in pool wp_post.
	 *	@return $oldValue cause WP dont go further with that option. */
	function savePool($value, $oldValue, $option)
	{
		if( isset($this->whitelist) && !empty($this->whitelist) && ($poolId = intval($value)) >= 0 )
		{
			$pool = \apply_filters('lws_wr_pool_admin_options_get_pool', null, $poolId, $this->whitelist, $this->poolOptionPrefix);
			if( empty($pool) && $poolId > 0 )
				$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p' => $poolId, 'deep'=>false))->last();

			if( empty($pool) )
			{
				error_log("Requested pool cannot be loaded or created ($poolId).");
				\lws_admin_add_notice_once('wr-pool-option-update-failure', __("Requested loyalty system cannot be loaded or created.", LWS_WOOREWARDS_DOMAIN), array('level'=>'error'));
			}
			else
			{
				\do_action('lws_wr_pool_admin_options_before_update', $pool, $this->whitelist);

				foreach( $this->whitelist as $option )
				{
					$option = trim($option);
					$value  = null;
					if( isset($_POST[$option]) )
					{
						$value = $_POST[$option];
						$value = \wp_unslash(is_array($value) ? $value : trim($value));
					}

					if( substr($option, 0, strlen($this->poolOptionPrefix)) == $this->poolOptionPrefix )
					{
						$key = substr($option, strlen($this->poolOptionPrefix));
						$pool->setOption($key, $value);
						// special case
						if( $key == 'title' && empty($poolId) )
							$pool->setName($value);
					}
					else if( $option != $this->optPool )
					{
						\update_option($option, $value);
					}
				}

				$pool->ensureNameUnicity();
				$pool->save(false, false);
				\do_action('lws_wr_pool_admin_options_after_update', $pool, $this->whitelist);
			}
		}
		return $oldValue;
	}
}
