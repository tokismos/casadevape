<?php
namespace LWS\Adminpanel;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Query") ) :


/** Créé un champ sur la page d'administrtion.
 *	La valeur saisie pourra être utilisée via get_option($key).
 * Provide a filter 'admin_tabs_{plugin_slug}' for your adminpanel tabs
 * to let us add an activation form. */
class Query
{
	private $file = '';
	private $slug = '';
	private $url = '';
	private $adminPageId = '';
	private $data = null;
	private $prod = '';

	const TAB_ID = 'license';

	/** Register a plugin requiring activation
	 * @param $main_file is the plugin main file (who contains the header).
	 * @param $api_url is the url to ask for license, default is ?.
	 * @param $adminPageId is the id of page in Pages::makePages array.
	 * @return true if plugin already activated. */
	public static function install($main_file, $api_url='', $adminPageId='', $nic='')
	{
		$me = new Query($main_file, $api_url, $adminPageId);
		static $slugDone = array();
		if( !isset($slugDone[$me->slug]) )
		{
			$activated = $me->activated();
			$slugDone[$me->slug] = $activated;
			if( defined('DOING_AJAX') )
			{
				$me->nic = $nic;
				add_action( 'wp_ajax_lws_adminpanel_activation', array( $me, 'activationNotice') );
			}
			else
			{
				//if( !$activated )
				//	add_action( 'admin_notices', array($me, 'adminNotice') );
				if( !is_array($me->adminPageId) )
					add_filter( 'lws_adminpanel_make_page_' . $me->adminPageId, array($me, 'activationTab'), PHP_INT_MAX-20, 2 );
				else foreach( $me->adminPageId as $pageId )
					add_filter( 'lws_adminpanel_make_page_' . $pageId, array($me, 'activationTab'), PHP_INT_MAX-20, 2 );
			}
		}
		return $slugDone[$me->slug];
	}

	function activationTab($page, $isCurrent)
	{
		$page['activated'] = $this->activated();
		if( $isCurrent )
		{
			$this->completePage($page, $isCurrent);
			$grp = array();
			if( current_user_can('install_plugins') )
				$grp = $this->authorisedUserTabContent($page['activated']);
			else
				$grp['license'] = $this->lambdaUserTabContent($page['activated']);

			$page['tabs'][self::TAB_ID]['groups'] = array_merge($grp, $page['tabs'][self::TAB_ID]['groups']);
		}
		return $page;
	}

	/** @return a group array about licenses for user with 'install_plugins' capacity. */
	private function authorisedUserTabContent($activated)
	{
		$groups = array();
		$data = $this->pluginData();
		if( $activated )
		{
			$num = get_site_option('lws-license-key-' . $this->slug);
			$help = empty($num) ? __("You actually use a <b>hacked</b> version.", LWS_ADMIN_PANEL_DOMAIN) : sprintf(__("This activation actually uses the license key <b>%s</b>", LWS_ADMIN_PANEL_DOMAIN), $num);
			$help .= $this->upgradeButton();
			$groups['licinfo'] = array(
				'title' => sprintf(__("%s - Activated", LWS_ADMIN_PANEL_DOMAIN), $data['Name']),
				'text' => sprintf(
					__("Visit <a href='%s'>%s</a> to discover other offers.", LWS_ADMIN_PANEL_DOMAIN),
					esc_attr($data['PluginURI']), $data['Author']
				),
				'fields' => array(
					array(
						'id' => 'licence-details',
						'type' => 'help',
						'extra' => array(
							'help' => $help
						)
					)
				)
			);
			$end = get_site_option('lws-license-end-' . $this->slug);
			if( !empty($end) )
			{
				$end = \date_i18n( \get_option( 'date_format' ), strtotime($end) );
				$message = sprintf(__("Your license expires the %s.", LWS_ADMIN_PANEL_DOMAIN), $end);
				$groups['licinfo']['fields'][] = array(
					'id' => 'licence-expiry',
					'type' => 'help',
					'extra' => array(
						'help' => $message
					)
				);
			}
		}

		$groups['license'] = array(
			'title' => $activated ? __("Set another license key", LWS_ADMIN_PANEL_DOMAIN) : sprintf(__("%s - Activate your license", LWS_ADMIN_PANEL_DOMAIN), $data['Name']),
			'function' => array($this, 'activationForm')
		);

		return $groups;
	}

	/** @return a group array about activated or not for user without 'install_plugins' capacity. */
	private function lambdaUserTabContent($activated)
	{
		$data = $this->pluginData();
		if( $activated )
		{
			$fields = array();
			if( empty(get_site_option('lws-license-key-' . $this->slug)) )
				$fields[] = array(
					'id' => 'licence-details',
					'type' => 'help',
					'extra' => array(
						'help' => __("You actually use a <b>hacked</b> version.", LWS_ADMIN_PANEL_DOMAIN)
					)
				);

			return array(
				'title' => sprintf(__("%s - Activated", LWS_ADMIN_PANEL_DOMAIN), $data['Name']),
				'text' => sprintf(
					__("Visit <a href='%s'>%s</a> to discover other offers.", LWS_ADMIN_PANEL_DOMAIN),
					esc_attr($data['PluginURI']), $data['Author']
				),
				'fields' => $fields
			);
		}
		else
		{
			return array(
				'title' => sprintf(__("%s - Activate your license", LWS_ADMIN_PANEL_DOMAIN), $data['Name']),
				'text' => sprintf(
					__("<p>The plugin <strong>%s</strong> from <a href='%s'>%s</a> needs activation</p><p>Contact your administrator.</p>", LWS_ADMIN_PANEL_DOMAIN),
					$data['Name'], esc_attr($data['PluginURI']), $data['Author']
				)
			);
		}
	}

	protected function completePage(&$page, $isCurrent)
	{
		if( !isset($page['tabs']) ) $page['tabs'] = array();
		if( !isset($page['tabs'][self::TAB_ID]) ){
			$page['tabs'][self::TAB_ID] = array(
				'title' => __("Pro Version", LWS_ADMIN_PANEL_DOMAIN),
				'id' => self::TAB_ID,
				'nosave' => true,
				'groups' =>	array()
			);
		}
	}

	private function addFormScript()
	{
		$script = LWS_ADMIN_PANEL_JS . '/activation.js';
		$guid = 'lws-adminpanel-activation';
		wp_enqueue_script( $guid, $script, array('jquery'), LWS_ADMIN_PANEL_VERSION, true );
		$info = admin_url('admin-ajax.php?action=lws_adminpanel_activation');
		wp_localize_script( $guid, 'lwsActivationUrl', $info );
	}

	function activationForm()
	{
		$this->addFormScript();
		$data = $this->pluginData();
		$fingerprint = $this->fingerprint();
		$nonce = wp_create_nonce('lws-activation' . $fingerprint);

		$ph = array(
			esc_attr(_x("Activate", "plugin activation form button", LWS_ADMIN_PANEL_DOMAIN)),
			_x("License key", "plugin activation form", LWS_ADMIN_PANEL_DOMAIN),
			_x("Login", "plugin activation form", LWS_ADMIN_PANEL_DOMAIN),
			_x("Password", "plugin activation form", LWS_ADMIN_PANEL_DOMAIN),
			__("Install pro version after license validation", LWS_ADMIN_PANEL_DOMAIN),
		);

		$form = "<div class='lws-adminpanel-activation-form'>
			<input type='hidden' value='$fingerprint' name='fingerprint'>
			<input type='hidden' value='$nonce' name='nonce'>";
		$form1 = $form . "<label><span class='lws-adminpanel-activation-form-title'>{$ph[1]}</span>
			<span class='lws-adminpanel-activation-form-input'>
			<input class='lws-input lws-ignore-confirm' type='text' name='license_token' size='25' required /></span></label>
			<input class='lws-adm-btn' type='submit' value='{$ph[0]}'>";
			if( \current_user_can('update_plugins') )
				$form1 .= " <label class='lws-adminpanel-activation-form-small-text'>({$ph[4]} <input class='lws-input lws-ignore-confirm' type='checkbox' id='lws_adm_install_pro_cb' checked='checked'>)</label>";
			$form1 .= "</div>";
		$form2 = $form . "<label><span class='lws-adminpanel-activation-form-title'>{$ph[2]}</span>
			<span class='lws-adminpanel-activation-form-input'>
			<input class='lws-input lws-ignore-confirm' type='text' name='login' required /></span></label>
			<label><span class='lws-adminpanel-activation-form-title'>{$ph[3]}</span>
			<span class='lws-adminpanel-activation-form-input'>
			<input class='lws-input lws-ignore-confirm' type='password' name='password' required /></span></label>
			<input class='lws-adm-btn' type='submit' value='{$ph[0]}'></div>";

		$info = _x('<p>Activate this plugin to enjoy all the pro features of <strong>%1$s</strong><br/>
If you do not have any license, please visit <a href=\'%3$s\'>%2$s</a></p>
<p>%4$s</p>', '%4$s is license key form.', LWS_ADMIN_PANEL_DOMAIN);
		$info = apply_filters('lws_adminpanel_license_form_text', $info, $this->slug);
		$info = sprintf( $info, $data['Name'], $data['Author'], esc_attr($data['PluginURI']), $form1);
		$error = sprintf(__("<p>An error occured during <strong>%s</strong> activation.</p>", LWS_ADMIN_PANEL_DOMAIN), $data['Name']);
		$success = current_user_can('update_plugins') ? $this->upgradeButton() : '';

		$info2 = __('or using your connection settings to <a href=\'%2$s\'>%1$s</a>', LWS_ADMIN_PANEL_DOMAIN);
		$info2 = sprintf($info2, $data['Author'], $data['AuthorURI']);
		$info .= sprintf("<p><span class='lws-second-activation-method'>%s</span></p>
			<div style='display:none'>%s</div>", $info2, $form2);

		$content = '';
		if( empty(\get_site_option('lws-license-key-' . $this->slug)) )
		{
			$free = sprintf(
				__('You are using the free version of %1$s. You can use it <a href="%2$s">here</a> or upgrade to the Trial/Pro version down below.', LWS_ADMIN_PANEL_DOMAIN),
				$data['Name'],
				\apply_filters('lws_adminpanel_settings_page_url_'.$this->slug, \remove_query_arg('tab'))
			);
			$content .= "<div class='lws-use-free-info'>$free</div>";
		}
		$content .= "<div class='lws-activation-info'>$info</div>";
		$content .= "<div class='lws-activation-success' style='display:none'>$success</div>";
		$content .= "<div class='lws-activation-error' style='display:none'>$error</div>";
		echo "<div class='lws-activation'>$content</div>";
	}

	/** @return html code of a button to start plugin upgrade. */
	private function upgradeButton()
	{
		$url = \wp_nonce_url(\is_multisite() ? \network_admin_url('update-core.php') : \admin_url('update-core.php'), 'upgrade-core');
		$url = \add_query_arg(array(
			'plugins' => $this->plugin_slug,
			'action'  => 'do-plugin-upgrade',
			'lwsupforce' => '1',
		), $url);
//		$url = \add_query_arg(array('action'=>'upgrade-plugin', 'plugin'=>$this->plugin_slug, 'lwsupforce'=>'1'), \self_admin_url('update.php'));
//		$url = \wp_nonce_url($url, 'upgrade-plugin_' . $this->plugin_slug);
		return sprintf("<a data-slug='%s' data-plugin='%s' id='plugin_update_from_iframe' class='lws-updpro lws-theme-bg' href='%s' target='_parent'>%s</a>",
			\esc_attr($this->slug),
			\esc_attr($this->plugin_slug),
			\esc_attr($url),
			__("Install PRO version now", LWS_ADMIN_PANEL_DOMAIN)
		);
	}

	/** display a form for activation purpose. */
	function adminNotice()
	{
		$data = $this->pluginData();
		if( current_user_can('install_plugins') )
		{
			$ph = esc_attr(_x("Activate", "plugin activation link", LWS_ADMIN_PANEL_DOMAIN));
			$pageId = is_array($this->adminPageId) ? $this->adminPageId[0] : $this->adminPageId;
			$url = add_query_arg(array('page'=>$pageId, 'tab'=>self::TAB_ID), admin_url('/admin.php'));
			$info = sprintf(
				__("<p>The plugin <strong>%s</strong> needs activation %s</p>", LWS_ADMIN_PANEL_DOMAIN),
				$data['Name'], "<input class='lws-adm-btn' type='submit' value='$ph' onclick='location.href=\"$url\"; return false;'>"
			);
			echo "<div class='lws-activation notice notice-info is-dismissible'>$info</div>";
		}
		else
		{
			$content = sprintf(__("<p>The plugin <strong>%s</strong> from <a href='%s'>%s</a> needs activation</p>
				<p>Contact your administrator.</p>", LWS_ADMIN_PANEL_DOMAIN),
				$data['Name'], esc_attr($data['PluginURI']), $data['Author']);
			echo "<div class='lws-activation notice notice-info is-dismissible'>$content</div>";
		}
	}

	/** listen AJAX call {action: 'lws_adminpanel_activation'}
	 * Expect POST p_slug, fingerprint and
	 * either license_token
	 * or couple login/password. */
	function activationNotice()
	{
		$result = array('ok'=>false, 'html'=>__("<p>Data seems corrupted.</p>", LWS_ADMIN_PANEL_DOMAIN));
		$fp = isset($_POST['fingerprint']) ? sanitize_key($_POST['fingerprint']) : '';
		$fingerprint = $this->fingerprint();
		if( wp_verify_nonce((isset($_POST['nonce']) ? $_POST['nonce'] : ''), 'lws-activation' . $fingerprint)
			&& ($fp === $fingerprint)
			&& (isset($_POST['license_token']) || (isset($_POST['login']) && isset($_POST['password'])))
		)
		{
			$data = $this->pluginData();
			$nonce = wp_create_nonce($fp . $this->slug);
			$body = array(
				'fingerprint' => $fp,
				'version' => $data['Version'],
				'hash' => hash( 'sha256',
					implode('/', array($this->prod, $data['Version'], $fp, $nonce, $this->nic)) )
			);
			if( isset($_POST['license_token']) )
				$body['token'] = sanitize_text_field($_POST['license_token']);
			else if( isset($_POST['login'])&& isset($_POST['password']) )
			{
				$body['login'] = sanitize_user($_POST['login']);
				$body['password'] = $_POST['password'];
			}
			$args = array(
				'method' => 'POST',
				'timeout' => 45,
				'body' => $body,
				'cookies' => array('nonce' => $nonce)
			);
			$result = $this->askLicenseServer($args, $result, $fp);
			wp_send_json($result);
		}
	}

	/** @param $args @see wp_remote_post */
	private function askLicenseServer($args, $result, $fingerprint)
	{
		$result['ok'] = false;
		$result['html'] = __("<p>Internal error, please retry later.</p>", LWS_ADMIN_PANEL_DOMAIN);
		$request = wp_remote_post($this->url, $args);
		if( is_wp_error($request) )
		{
			error_log("Cannot reach license server: " . $request->get_error_message());
			$result['html'] = __("<p>Cannot reach license server. Please retry later or contact the administrator.</p>", LWS_ADMIN_PANEL_DOMAIN);
		}
		else if( !in_array(($code = \wp_remote_retrieve_response_code($request)), array(200, 301, 302)) )
		{
			$cat = substr(trim($code), 0, 1);
			$msg = \wp_remote_retrieve_response_message($request);
			if( $cat == '4' ) // client error
			{
				error_log("License server return code: $code\n==> $msg");
				$result['html'] = __("<p class='lws-adminpanel-activation-error'>The license server cannot validate your request. Please check your values and try again.</p>", LWS_ADMIN_PANEL_DOMAIN);
				$result['html'] .= "<p class='lws-adminpanel-activation-error-details'>$msg</p>";
			}
			else if( $cat == '5' ) // server error
			{
				error_log("License server return code: $code\n==> $msg");
				$result['html'] = __("<p class='lws-adminpanel-activation-error'>Bad response from license server ($code). Please retry later or contact the administrator.</p>", LWS_ADMIN_PANEL_DOMAIN);
			}
			else // unexpected answer
			{
				error_log("License server return code: $code\n==> $msg");
				$result['html'] = __("<p class='lws-adminpanel-activation-error'>Unexpected response from license server ($code). Please retry later or contact the administrator.</p>", LWS_ADMIN_PANEL_DOMAIN);
			}
		}
		else
		{
			$json = json_decode(wp_remote_retrieve_body($request));
			$nonce = wp_remote_retrieve_cookie_value($request, 'nonce');
			$rand = wp_remote_retrieve_cookie_value($request, 'rand');
			if( !wp_verify_nonce($nonce, $fingerprint . $this->slug) )
			{
				$result['html'] = __("<p>Operation abort, detect a man-in-the-middle attack.</p>", LWS_ADMIN_PANEL_DOMAIN);
			}
			else if( !(isset($json->ok) && isset($json->slug) && isset($json->fingerprint) && isset($json->token)
				&& isset($json->hash) && $this->isConsistentActivation($json, $nonce, $rand)) )
			{
				if( isset($json->html) )
					$result['html'] = $json->html;
				else
					$result['html'] = __("<p>Corrupted data from license server.</p>", LWS_ADMIN_PANEL_DOMAIN);
			}
			else
			{
				if( isset($json->ok) && $json->ok == true )
				{
					$result['ok'] = $this->setActivated($json->slug, true, $json->fingerprint);
					$result['html'] = isset($json->html) ? $json->html : sprintf(__("<p><strong>%s</strong> is now activated.</p>", LWS_ADMIN_PANEL_DOMAIN), $this->pluginData('Name'));
					$result['html'] .= sprintf("<p><a class='lws-adminpanel-goto-update' href='%s'>%s</a></p>",
						esc_attr(network_admin_url('update-core.php')),
						__("Now, you can check available plugin updates.", LWS_ADMIN_PANEL_DOMAIN)
					);
					\update_site_option('lws-license-key-' . $this->slug, $json->token);
					\update_site_option('lws-license-end-' . $this->slug, isset($json->expire) ? $json->expire : '');
					\update_site_option('lws-license-xpr', array_merge(get_site_option('lws-license-xpr',array(), false),array($this->slug=>\wp_hash($this->slug.$json->token.$json->fingerprint.(isset($json->expire)?$json->expire:$this->x)).'-'.(isset($json->expire)?$json->expire:''))));
					\update_site_option('lws_adminpanel_notices', array_filter(\get_site_option('lws_adminpanel_notices', array()), array($this, 'isNotTheNotice'), ARRAY_FILTER_USE_KEY));
					\do_action('lws_adminpanel_plugin_activated_'.$this->slug);

					if( \current_user_can('update_plugins') )
					{
						$url = \wp_nonce_url(\is_multisite() ? \network_admin_url('update-core.php') : \admin_url('update-core.php'), 'upgrade-core');
						$url = \add_query_arg(array(
							'plugins' => $this->plugin_slug,
							'action'  => 'do-plugin-upgrade',
							'lwsupforce' => '1',
						), $url);
						$result['redirect'] = $url;
					}
				}
				else
				{
					$result['html'] = isset($json->html) ? $json->html : __("<p>License denied.</p>", LWS_ADMIN_PANEL_DOMAIN);
				}
			}
		}
		return $result;
	}

	function isNotTheNotice($slug)
	{
		if( 0 === strpos($slug, $this->slug) && strlen($slug) == (strlen($this->slug)+10) )
		{
			$matches = array();
			return !preg_match('/\d{4}-\d{2}-\d{2}/', $slug, $matches, 0, strlen($this->slug));
		}
		return true;
	}

	private function isConsistentActivation($json, $nonce, $rand)
	{
		if( !(empty($nonce) || empty($rand)) )
			return ($json->hash == hash( 'sha256',
				implode('/', array($json->slug, $json->token, $nonce, $rand, $this->nic)) ));
		return false;
	}

	private function pluginData($key='')
	{
		if( is_null($this->data) )
		{
			if( !function_exists('\get_plugin_data') )
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$this->data = \get_plugin_data($this->file, false);
			if( !isset($this->data['Name']) || empty($this->data['Name']) )
				$this->data['Name'] = $this->slug;
			if( !isset($this->data['Version']) )
				$this->data['Version'] = '';
			if( !isset($this->data['Author']) || empty($this->data['Author']) )
				$this->data['Author'] = __("Long Watch Studio", LWS_ADMIN_PANEL_DOMAIN);
			if( !isset($this->data['AuthorURI']) || empty($this->data['AuthorURI']) )
				$this->data['AuthorURI'] = __("https://plugins.longwatchstudio.com", LWS_ADMIN_PANEL_DOMAIN);
			if( !isset($this->data['PluginURI']) || empty($this->data['PluginURI']) )
				$this->data['PluginURI'] = __("https://plugins.longwatchstudio.com", LWS_ADMIN_PANEL_DOMAIN);
		}
		return empty($key) ? $this->data : $this->data[$key];
	}

	protected function __construct($main_file, $url, $adminPageId)
	{
		$this->file = $main_file;
		$this->plugin_slug = plugin_basename($this->file);
		$this->slug = $this->prod = strtolower(basename(plugin_basename($main_file), '.php'));
		$this->prod .= \apply_filters('lws_adminpanel_cred_slug_suffix_'.$this->slug, '', $main_file, $adminPageId);
		$this->x = \date('Y-m-d',0);
		if( !empty($url) )
			$this->url = $url;
		else
		{
			$args = array('action'=>'lwswoosoftware','product' => $this->prod, 'activate' => '');
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
	}

	private function setActivated($slug, $yes, $fingerprint='')
	{
		$res = false;
		if( $yes )
		{
			if( $slug == $this->slug && $fingerprint === $this->fingerprint() )
			{
				// Force refresh of plugin update information
				\delete_site_transient('update_plugins');
				\wp_cache_delete( 'plugins', 'plugins' );
				$old = get_site_option($this->optionName());
				$value = $this->activationKey();
				$res = update_site_option($this->optionName(), $value) || ($value == $old);

				if ( !function_exists( '\plugins_api' ) )
					include_once( ABSPATH . '/wp-admin/includes/plugin-install.php' );
				$plugin_api = \plugins_api( 'plugin_information', array( 'slug' => $this->slug, 'fields' => array( 'sections' => false, 'compatibility' => false, 'tags' => false ) ) );
				if( \is_wp_error($plugin_api) )
					error_log("Error on [{$this->slug}] licensed version info request: " . $plugin_api->get_error_message());
				else if( (isset($plugin_api->new_version) || isset($plugin_api->version)) && isset($plugin_api->download_link) )
				{
					$obj = new \stdClass();
					foreach( get_object_vars($plugin_api) as $k => $v )
						$obj->$k = $v;
					$obj->slug = $this->slug;
					$obj->url = $this->url;
					$obj->new_version = isset($plugin_api->new_version) ? $plugin_api->new_version : $plugin_api->version;
					$obj->package = $plugin_api->download_link;
					$plugin_transient->response[plugin_basename($this->file)] = $obj;
					\set_site_transient( 'update_plugins', $plugin_transient );
				}
				else
					error_log("Missing data returned by {$this->slug} licensed version info request.");
			}
			else
				error_log("Cannot activate plugin $slug");
		}
		else
			$res = delete_site_option($this->optionName());
		return $res;
	}

	private function optionName()
	{
		return 'lws-ap-activation-' . $this->slug;
	}

	private function server()
	{
		$url = parse_url( $_SERVER['SERVER_NAME'], PHP_URL_HOST );
		$parts = explode(".", $url, 2);
		if( count($parts) == 1 )
			$url =  $parts[0];
		else if( count($url) == 2 )
			$url =  $parts[1];
		return empty($url) ? $_SERVER['SERVER_NAME'] : $url;
	}

	private function fingerprint()
	{
		$tokens = array($this->slug, \get_class(), __FUNCTION__, $this->getSiteUrl(),
			DB_HOST, DB_NAME, (is_multisite()?'M':'S'), get_site_option('initial_db_version'));
		return sanitize_key(wp_hash(implode('.', $tokens)));
	}

	private function activationKey()
	{
		$tokens = array($this->slug, \get_class(), __FUNCTION__, $this->getSiteUrl(),
			DB_HOST, DB_NAME, (is_multisite()?'M':'S'), get_site_option('initial_db_version'));
		return wp_hash(implode('.', $tokens) . $this->slug);
	}

	private function getSiteUrl()
	{
		if( defined('WP_SITEURL') && WP_SITEURL )
			return WP_SITEURL;
		else
			return \get_site_option('siteurl');
	}

	private function activated()
	{
		$activated = get_site_option($this->optionName(), '');
		if( !empty($activated) )
		{
			if( $activated === $this->activationKey() )
				return true;
			else
				error_log("An activation key is set for {$this->slug} but seems corrupted. Did you duplicate or move your site?");
		}
		return false;
	}

}

endif
?>
