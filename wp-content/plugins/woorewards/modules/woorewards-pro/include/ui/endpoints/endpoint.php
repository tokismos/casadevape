<?php
namespace LWS\WOOREWARDS\PRO\Ui\Endpoints;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Base features of endpoint page.
 * If WC is installed, take place in my-account.
 * Else, replace a specified page defined in settings. */
abstract class Endpoint
{
	/** The page content @return as string. */
	abstract function getPage();

	/** Check wp_options if the endpoint is active.
	 * Manage if rewrite rules must be flushed.
	 * get_option($option) value is assumed to be a checkbox kind of value: 'on' or empty string. */
	function isActive($option, $default='on')
	{
		$active = \get_option($option, $default);

		$this->optFlush = 'rflush_'.$option;
		$this->flush = \get_option($this->optFlush);
		if( empty($active) && !empty($this->flush) )
			\update_option('rflush_'.$option, '');

		return !empty($active);
	}

	/**	@param $key (slug) url endpoint
	 *	@param $title page title or my-account tab title if WC activated. */
	function __construct($key, $title)
	{
		$this->key = $key;
		$this->tab = array($key => $title);

		// register endpoint in WC my-account
		\add_filter('woocommerce_account_menu_items', array($this, 'getWCMyAccountTabs'));
		\add_filter('woocommerce_get_query_vars', array($this, 'getQueryVars'));
		\add_action('init', array($this, 'addRewriteRules'));

		// behavior in WC my-account
		\add_action('woocommerce_account_' . $key . '_endpoint', array($this, 'printPage'));
		\add_filter('woocommerce_endpoint_' . $key . '_title', array($this, 'getTitle'));

		// replace a page content if any
		\add_filter('the_content', array($this, 'getPageContent'));
	}

	/** add rules to interpret our endpoint in url */
	function addRewriteRules()
	{
		\add_rewrite_endpoint($this->key, EP_ROOT|EP_PAGES);

		if( isset($this->optFlush) && isset($this->flush) && empty($this->flush) )
		{
			$option = $this->optFlush;
			\add_action('shutdown', function()use($option){
				\flush_rewrite_rules(true);
				\update_option($option, \date_create()->format(DATE_W3C));
			});
		}
	}

	/** Add our tab key to WC Query Vars */
	function getQueryVars($vars)
	{
		$vars[$this->key] = $this->key;
		return $vars;
	}

	/** @return WC my-account tab */
	function getTitle($title)
	{
		return $this->tab[$this->key];
	}

	/** @return WC my-account tab list including our. */
	function getWCMyAccountTabs($items=array())
	{
		if( empty($items) )
			$items = $this->tab;
		else // insert at penultimate place (-1), since last is usually "log off".
			$items = array_slice($items, 0, -1, true) + $this->tab + array_slice($items, -1, NULL, true);
		return $items;
	}

	/** echo the page for WC my-account page */
	function printPage()
	{
		echo $this->getPage();
	}

	/** @return $content with our endpoint page appended.
	 * Look for a 'page' with id recorded in option "lws_woorewards_endpoint_content_for_page_{$this->key}" */
	function getPageContent($content)
	{
		global $post;
		if( !empty($post) && isset($post->post_type) && ($post->post_type == 'page') && isset($post->ID) && ($post->ID == intval(\get_option('lws_woorewards_endpoint_content_for_page_'.$this->key))) )
		{
			if( !empty($content) )
				$content .= '<br/>';
			$content .= $this->getPage();
		}
		return $content;
	}
}
