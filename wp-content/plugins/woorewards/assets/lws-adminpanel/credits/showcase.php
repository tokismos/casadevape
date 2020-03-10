<?php
namespace LWS\Adminpanel;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Showcase") ) :

require_once LWS_ADMIN_PANEL_PATH . '/cache.php';

/** Add a tab to promote available extensions.
 * Info is provided bà WooSoftware api.
 * */
class Showcase
{
	const TAB_ID = 'license';

	/**
	 * @param $product_slug basename of the base product.
	 * @param $adminPageId the id of the administration page (default use the given slug). */
	public static function install($product_slug, $adminPageId='', $api_url='', $tab_id='')
	{
		if( is_admin() && !defined('DOING_AJAX') )
		{
			$me = new Showcase($product_slug, $adminPageId, $api_url, $tab_id);
			add_filter( 'lws_adminpanel_make_page_' . $me->adminPageId, array($me, 'promote'), PHP_INT_MAX - 10, 2 );
		}
	}

	function promote($page, $isCurrent)
	{
		if( $isCurrent )
		{
			$grps = array();
			$posts = $this->getPosts();
			if( !empty($posts) )
			{
				$this->completePage($page, $isCurrent);

				for($i=0 ; $i<count($posts) ; ++$i )
				{
					if( $posts[$i] )
					{
						$page['tabs'][$this->tabId]['groups'][] = array(
							'id' => "_$i",
							'title' => $posts[$i]->title,
							'function' => array($this, 'present')
						);
					}
				}
			}
		}
		return $page;
	}

	protected function completePage(&$page, $isCurrent)
	{
		if( !isset($page['tabs']) )
			$page['tabs'] = array();

		if( !isset($page['tabs'][$this->tabId]) )
		{
			$page['tabs'][$this->tabId] = array(
				'title' => __("More...", LWS_ADMIN_PANEL_DOMAIN),
				'id' => $this->tabId,
				'nosave' => true,
				'groups' =>	array()
			);
		}
	}

	protected function __construct($slug, $adminPageId='', $url='', $tab_id)
	{
		$this->slug = strtolower(basename(plugin_basename($slug), '.php'));
		if( !empty($url) )
			$this->url = $url;
		else
		{
			$args = array('action'=>'lwswoosoftware','product' => $this->slug,'showcase' => '');
			$server = 'https://plugins.longwatchstudio.com/wp-admin/admin-ajax.php';
			if( defined('LWS_DEV') && !empty(LWS_DEV) )
			{
				if( LWS_DEV === true )
					$server = admin_url('admin-ajax.php');
				else if( is_string(LWS_DEV) )
					$server = LWS_DEV;
			}
			$this->url = add_query_arg($args, $server);
		}
		$this->url = add_query_arg(array('lang'=>get_locale()), $this->url);
		$this->adminPageId = empty($adminPageId) ? $this->slug : $adminPageId;
		$this->tabId = empty($tab_id) ? self::TAB_ID : $tab_id;
	}

	private function getPosts()
	{
		if( !isset($this->posts) )
		{
			$this->posts = array();
			$cached = new Cache('showcase-'.$this->slug.'.json');
			$buffer = $cached->pop(true, 0, true);
			if( $buffer !== false )
			{
				$json = json_decode($buffer);
				if( !empty($json) )
					$this->posts = is_array($json) ? $json : array($json);
			}
			else
			{
				$request = wp_remote_get($this->url, array('timeout' => 120));
				if( is_wp_error($request) )
					error_log("Cannot reach showcase server: " . $request->get_error_message());
				else
				{
					$json = json_decode(wp_remote_retrieve_body($request));
					if( !empty($json) )
						$this->posts = is_array($json) ? $json : array($json);
					$cached->put( json_encode($this->posts) );
				}
			}
			$this->posts = apply_filters('lws_showcase_posts', $this->posts);
		}
		return $this->posts;
	}

	function present($index)
	{
		$index = substr($index, 1);
		$posts = $this->getPosts();
		if( is_numeric($index) && $index >= 0 && $index < count($posts) )
		{
			echo apply_filters('lws_showcase_item_content', $posts[$index]->descr);
		}
	}

}

endif
?>
