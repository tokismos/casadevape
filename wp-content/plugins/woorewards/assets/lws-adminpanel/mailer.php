<?php
namespace LWS\Adminpanel;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_ADMIN_PANEL_ASSETS . '/Parsedown.php';

/** Manage mail formating and sending.
 *
 *	To send a mail, use the action 'lws_mail_send' with parameters email, template_name, data.
 *
 *	You must add a filter to set mail settings with hook 'lws_mail_settings_' . $template_name.
 *	@see defaultSettings about values to return.
 *
 *	You must add a filter to define mail body with hook 'lws_mail_body_' . $template_name.
 *	Second argument is data given to 'lws_mail_send'.
 *	If a WP_Error is given instead, assume it is a demo (usually for stygen).
 *
 *	To get a mail settings value, use the filter 'lws_mail_snippet'.
 *
 *	@note
 *	During dev, notes that mailbox should prevent your image display if their url contains 127.0.0.1
 *
 *	This class use singleton.
 *
 * Settings array for a single mail is:
 *	* 'domain' => '', // groups several mail template with few commun settings.
 *	* 'settings_domain_name' => '', // name display in admin settings screen.
 *	* 'settings_name' => '', // name display in admin settings screen.
 *	* 'about' => '', // describe the purpose of this mail to the admin settings screen.
 *	* 'infomessage' => '', // replace the default help on to of stygen
 *	* 'subject' => '', // subject of the mail.
 *	* 'title' => '', // set at top of mail body.
 *	* 'header' => '', // presentation text in the body.
 *	* 'demo_file_path' => false, // path to a php/html file with a fake content for styling purpose.
 *	* 'css_file_url' => false, // url to a css file.
 *	* 'subids' => (string|array) inline editable text id.
 *	* 'fields' => array(), // (array of field array) as for lws_register_pages, add extra fields in mail settings.
 *	* 'footer' => '', // set at end of mail body.
 *	* 'headerpic' => false, // media ID of a picture set at the very top of the mail.
 *	* 'logo_url' => '' // <img> html code build from 'headerpic'
 *	* 'bcc_admin' => false // (boolval|string) send a blind copy to specified email (or admin if true or 'on'). Let choice to user with a field ['id' => 'lws_mail_bcc_admin_'.$template, 'type' => 'box']
 *
 *	Uninstall mails settings with:
 * @code
	foreach( array('lws_domain') as $domain )
	{
		$mailprefix = "lws_mail_{$domain}_attribute_";
		delete_option($mailprefix.'headerpic');
		delete_option($mailprefix.'footer');
	}
	foreach( array('lws_template1', 'lws_template2') as $template )
	{
		delete_option('lws_mail_subject_'.$template);
		delete_option('lws_mail_preheader_'.$template);
		delete_option('lws_mail_template_'.$template);
		delete_option('lws_mail_title_'.$template);
		delete_option('lws_mail_header_'.$template);
	}
 * @endcode
 **/
class Mailer
{

	/** $coupon_id (array|id) an array of coupon post id.
	 * That function switch langage the time it formats and send the email
	 * @see https://wpml.org/documentation/support/sending-emails-with-wpml/ */
	function sendMail($email, $template, $data=null)
	{
		do_action('wpml_switch_language_for_email', $email); // switch to user language before format email

		$settings = $this->getSettings($template, true);
		$settings['user_email'] = $email;
		$settings = apply_filters('lws_mail_arguments_' . $template, $settings, $data);

		$headers = array('Content-Type: text/html; charset=UTF-8');
		if( !empty($fromEMail = \sanitize_email(\get_option('woocommerce_email_from_address'))) )
		{
			if( !empty($fromName = \wp_specialchars_decode( \esc_html( \get_option('woocommerce_email_from_name') ), ENT_QUOTES )) )
				$headers[] = sprintf('From: %s <%s>', $fromName, $fromEMail);
			else
				$headers[] = 'From: ' . $fromEMail;
		}

		if( isset($settings['bcc_admin']) && !empty($settings['bcc_admin']) )
		{
			$admMail = $settings['bcc_admin'];
			if( (true === $admMail) || ('on' == $admMail) )
				$admMail = \get_option('admin_email');
			if( \is_email($admMail) )
				$headers[] = 'Bcc: ' . $admMail;
		}

		$this->altBody = true;
		\wp_mail(
			$email,
			$settings['subject'],
			$this->getContent($template, $settings, $data),
			\apply_filters('lws_mail_headers_' . $template, $headers, $data)
		);
		$this->altBody = false;

		do_action('wpml_restore_language_from_email');
	}

	/**	@return an array to set in admin page registration as 'groups', each item representing a group array.
	 *	@param $templates array of template names. */
	function settingsGroup($templates)
	{
		$mails = array();

		if( !is_array($templates) )
		{
			if( is_string($templates) )
				$templates = array($templates);
			else
				return $mails;
		}

		foreach( $this->groupsByDomain($templates) as $domain => $settings )
		{
			$mails['D_'.$domain] = $this->buildDomainSettingsGroup($domain, $settings['name']);

			foreach( $settings['settings'] as $template => $args )
				$mails[$template] = $this->buildTemplateSettingsGroup($template, $args);
		}
		return $mails;
	}

	/** @return a mail settings property.
	 * @param $value (string) default value.
	 * @param $template (string) the mail template name we are looking for.
	 * @param $key (string) the property name @see defaultSettings */
	function settingsData($value, $template, $key)
	{
		$settings = $this->getSettings($template, true);
		if( isset($settings[$key]) && !empty($settings[$key]) )
			$value = $settings[$key];
		return $value;
	}

	protected static function defaultSettings()
	{
		return array(
			'domain' => '', // groups several mail template with few commun settings.
			'settings_domain_name' => '', // name display in admin settings screen.
			'settings_name' => '', // name display in admin settings screen.
			'about' => '', // describe the purpose of this mail to the admin settings screen.
			'infomessage' => '', // replace the default help on to of stygen
			'subject' => '', // subject of the mail.
			'preheader' => '', // excerpt of the mail.
			'title' => '', // set at top of mail body.
			'header' => '', // presentation text in the body.
			'demo_file_path' => false, // path to a php/html file with a fake content for styling purpose.
			'css_file_url' => false, // url to a css file.
			'fields' => array(), // (array of field array) as for lws_register_pages, add extra fields in mail settings.
			'footer' => '', // set at end of mail body.
			'headerpic' => false, // media ID of a picture set at the very top of the mail.
			'logo_url' => '' // <img> html code build from 'headerpic'
		);
	}

	function parsedown($txt)
	{
		return $this->Parsedown->text($txt);
	}

	static function instance()
	{
		static $_instance = null;
		if( $_instance == null )
			$_instance = new self();
		return $_instance;
	}

	protected function __construct()
	{
		$this->Parsedown = new \LWS\Adminpanel\Parsedown();
		$this->Parsedown->setBreaksEnabled(true);
		$this->settings = array();

		/** Send a mail
		 * @param user mail,
		 * @param mail_template (string),
		 * @param data (whatever is needed by your template) pass to hook 'lws_woorewards_mail_body_' . $template */
		add_action('lws_mail_send', array($this, 'sendMail'), 10, 3);
		/** return the settings piece of data.
		 * @param (not used)
		 * @param template_name
		 * @param settings key (as title, header...) @see defaultSettings */
		add_filter('lws_mail_snippet', array($this, 'settingsData'), 10, 3);

		add_filter('lws_markdown_parse', array($this, 'parsedown'));

		$this->altBody = false;
		add_action('phpmailer_init', array($this, 'addAltBody'), 9, 1);
	}

	protected function getSettings($template, $loadValues=false, $reset=false)
	{
		if( !isset($this->settings[$template]) || $reset )
			$this->settings[$template] = apply_filters('lws_mail_settings_' . $template, self::defaultSettings());

		if( $loadValues && (!isset($this->settings[$template]['loaded']) || !$this->settings[$template]['loaded']) )
		{
			$value = trim(\get_option('lws_mail_subject_'.$template));
			if( !empty($value) ) $this->settings[$template]['subject'] = $value;
			$value = trim(\get_option('lws_mail_preheader_'.$template));
			if( !empty($value) ) $this->settings[$template]['preheader'] = $value;
			$value = trim(\get_option('lws_mail_title_'.$template));
			if( !empty($value) ) $this->settings[$template]['title'] = $value;
			$value = trim(\get_option('lws_mail_header_'.$template));
			if( !empty($value) ) $this->settings[$template]['header'] = $value;

			$domain = !empty($this->settings[$template]['domain']) ? $this->settings[$template]['domain'] : $template;
			$value = trim(\get_option("lws_mail_{$domain}_attribute_footer"));
			if( !empty($value) ) $this->settings[$template]['footer'] = $value;

			$value = intval(\get_option("lws_mail_{$domain}_attribute_headerpic"));
			if( !empty($value) ) $this->settings[$template]['headerpic'] = $value;
			if( !empty($this->settings[$template]['headerpic']) )
			{
				$value = \wp_get_attachment_image($this->settings[$template]['headerpic'], 'small');
				if( !empty($value) ) $this->settings[$template]['logo_url'] = $value;
			}

			if( !isset($this->settings[$template]['bcc_admin']) )
				$this->settings[$template]['bcc_admin'] = \get_option('lws_mail_bcc_admin_'.$template);

//			$this->settings[$template]['title']   = $this->Parsedown->text($this->settings[$template]['title']);

			$this->settings[$template]['loaded'] = true;
		}

		return $this->settings[$template];
	}

	protected function getContent($template, &$settings, &$data)
	{
		$style = '';
		if( !empty($settings['css_file_url']) )
			$style = \apply_filters('stygen_inline_style', '', $settings['css_file_url'], 'lws_mail_template_'.$template);

		return $this->content($template, $settings, $data, $style);
	}

	protected function content($template, &$settings, &$data, $style='')
	{
		$html = "<!DOCTYPE html><html xmlns='http://www.w3.org/1999/xhtml'>";
		$html .= "<head><meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />";
		if( !empty($style) )
			$html .= "<style>$style</style>";
		$html .= "</head><body leftmargin='0' marginwidth='0' topmargin='0' marginheight='0' offset='0'>";
		if( isset($settings['preheader']) && $settings['preheader'] )
		{
			$preheader = "<span class='preheader' style='display:none !important;'>{$settings['preheader']}</span>";
			$html .= \apply_filters('lws_mail_preheader_' . $template, $preheader, $data, $settings);
		}
		$html .= $this->banner($template, $settings);
		$html .= \apply_filters('lws_mail_body_' . $template, '', $data, $settings);
		$html .= $this->footer($template, $settings);
		$html .= "</body></html>";
		return $html;
	}

	/** Ask for a mail content with placeholder data.
	 * Do not embed any style.
	 * provided for class-stygen.php */
	function getDemo($template)
	{
		$settings = $this->getSettings($template, true);
		$data = new \WP_Error('gizmo', __("This is a test."));
		return $this->content($template, $settings, $data);
	}

	protected function banner($template, $settings)
	{
		$html = <<<EOT
	<center>
		<center>{$settings['logo_url']}</center>
		<table class='lwss_selectable lws-main-conteneur $template' data-type='Main Border'>
			<thead>
				<tr>
					<td class='lwss_selectable lws-top-cell lwss_modify $template' data-id='lws_mail_title_$template' data-type='Title'>
						<div class='lwss_modify_content'>{$settings['title']}</div>
					</td>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td class='lwss_selectable lws-middle-cell lwss_modify $template' data-id='lws_mail_header_$template' data-type='Header'>
						<div class='lwss_modify_content'>{$settings['header']}</div>
					</td>
				</tr>
EOT;
		return apply_filters('lws_mail_head_' . $template, $html, $settings);
	}

	protected function footer($template, $settings)
	{
		$html = <<<EOT
			</tbody>
			<tfoot>
				<tr>
					<td class='lwss_selectable lws-bottom-cell $template' data-type='Footer'>{$settings['footer']}</td>
				</tr>
				</tfoot>
		</table>
	</center>
EOT;
		return apply_filters('lws_mail_foot_' . $template, $html, $settings);
	}

	protected function groupsByDomain($templates)
	{
		$domains = array();
		foreach($templates as $template)
		{
			$settings = $this->getSettings($template, false, true);
			if( empty($settings['domain']) )
			{
				if( !isset($domains[$template]) || empty($domains[$template]['name']) )
					$domains[$template]['name'] = !empty($settings['settings_domain_name']) ? $settings['settings_domain_name'] : '';
				$domains[$template]['settings'][$template] = $settings;
			}
			else
			{
				$domain = $settings['domain'];
				if( !isset($domains[$domain]) || empty($domains[$domain]['name']) )
					$domains[$domain]['name'] = !empty($settings['settings_domain_name']) ? $settings['settings_domain_name'] : '';
				$domains[$domain]['settings'][$template] = $settings;
			}
		}
		return $domains;
	}

	protected function buildDomainSettingsGroup($domain, $title)
	{
		$prefix = "lws_mail_{$domain}_attribute_";

		return array(
			'id' => 'lws_mail_d_' . $domain,
			'title' => empty($title) ? __("Email Settings", LWS_ADMIN_PANEL_DOMAIN) : sprintf(__("%s Email Settings", LWS_ADMIN_PANEL_DOMAIN), $title),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/emails/'),
			'fields' => array(
				array(
					'id' => 'lws_adminpanel_email_help',
					'type' => 'help',
					'extra' => array(
						'help'=> (isset($settings['infomessage']) && !empty($settings['infomessage'])) ? $settings['infomessage'] : __("
							Once you've finished the email settings, <b>save your changes</b><br/>
							You will then see the result in the style editor below<br/>
							Select the elements you wish to change and have fun!
						", LWS_ADMIN_PANEL_DOMAIN)
					)
				),
				array( 'type' => 'media' , 'title' => __("Header picture", LWS_ADMIN_PANEL_DOMAIN), 'id' => $prefix.'headerpic', 'extra' => array('size' => 'medium') ),
				array( 'type' => 'wpeditor' , 'title' => __("Footer text", LWS_ADMIN_PANEL_DOMAIN), 'id' => $prefix.'footer', 'extra' => array('editor_height' => 30) )
			)
		);
	}

	protected function buildTemplateSettingsGroup($template, $settings)
	{
		$mailId = 'lws_mail_t';
		if( isset($settings['domain']) )
			$mailId .= '_' . $settings['domain'];
		$mailId .= '_' . $template;

		$mail = array(
			'id' => $mailId,
			'title' => !empty($settings['settings_name']) ? $settings['settings_name'] : __("Email details", LWS_ADMIN_PANEL_DOMAIN),
			'text' => !empty($settings['about']) ? $settings['about'] : '',
			'fields' => array(
				array(
					'id' => 'lws_mail_subject_'.$template,
					'title' => __("Subject", LWS_ADMIN_PANEL_DOMAIN),
					'type' => 'text',
					'extra' => array( 'maxlength' => 350, 'placeholder' => $settings['subject'], 'size' => '40', 'wpml'=>"{$settings['domain']} mail - {$settings['settings_name']} - Subject" )
				),
				array(
					'id' => 'lws_mail_preheader_'.$template,
					'title' => __("Preheader", LWS_ADMIN_PANEL_DOMAIN),
					'type' => 'text',
					'extra' => array( 'advanced' => true, 'maxlength' => 350, 'placeholder' => $settings['preheader'], 'size' => '40', 'wpml'=>"{$settings['domain']} mail - {$settings['settings_name']} - Preheader" )
				),
			)
		);

		if( isset($settings['fields']) && is_array($settings['fields']) && !empty($settings['fields']) )
			$mail['fields'] = array_merge($mail['fields'], $settings['fields']);

		if( !empty($settings['css_file_url']) )
		{
			$extra = array(
				'template' => $template,
				'html' => !empty($settings['demo_file_path']) ? $settings['demo_file_path'] : false,
				'css' => $settings['css_file_url'],
				'purpose' => 'mail'
			);
			if( isset($settings['subids']) && !empty($settings['subids']) )
				$extra['subids'] = is_array($settings['subids']) ? $settings['subids'] : array($settings['subids']);
			$extra['subids']['lws_mail_title_'.$template] = "{$settings['domain']} mail - {$settings['settings_name']} - Title";
			$extra['subids']['lws_mail_header_'.$template] = "{$settings['domain']} mail - {$settings['settings_name']} - Header";

			$mail['fields'][] = array(
				'id' => 'lws_mail_template_'.$template,
				'type' => 'stygen',
				'extra' => $extra
			);
		}

		$mail['fields'][] = array(
			'id' => 'lws_adminpanel_mail_tester_'.$template,
			'title' => __("Receiver Email", LWS_ADMIN_PANEL_DOMAIN),
			'type' => 'text',
			'extra' => array(
				'help' => __("Test your email to see how it looks", LWS_ADMIN_PANEL_DOMAIN),
				'class' => 'lws-ignore-confirm',
				'size' => '40'
			)
		);
		$mail['fields'][] = array(
			'id' => 'lws_adminpanel_mail_tester_btn_'.$template,
			'title' => __("Send test email", LWS_ADMIN_PANEL_DOMAIN),
			'type' => 'button',
			'extra' => array('callback' => array($this, 'test'))
		);

		return $mail;
	}

	function test($id, $data)
	{
		$base = 'lws_adminpanel_mail_tester_btn_';
		$len = strlen($base);
		if( substr($id, 0, $len) == $base && !empty($template=substr($id,$len)) && isset($data['lws_adminpanel_mail_tester_'.$template]) )
		{
			$email = sanitize_email($data['lws_adminpanel_mail_tester_'.$template]);
			if( \is_email($email) )
			{
				do_action('lws_mail_send', $email, $template, new \WP_Error());
				return __("Test email sent.", LWS_ADMIN_PANEL_DOMAIN);
			}
			else
				return __("Test email is not valid.", LWS_ADMIN_PANEL_DOMAIN);
		}
		return false;
	}

	/** add a plain text version of our email */
	function addAltBody($phpmailer)
	{
		if( !$this->altBody )
			return;
		if( $phpmailer->ContentType === 'text/plain' )
			return;

		static $toDelPattern = array(
			'@<head[^>]*?>.*?</head>@siu',
			'@<style[^>]*?>.*?</style>@siu',
			'@<script[^>]*?.*?</script>@siu',
			'@<object[^>]*?.*?</object>@siu',
			'@<embed[^>]*?.*?</embed>@siu',
			'@<noscript[^>]*?.*?</noscript>@siu',
			'@<noembed[^>]*?.*?</noembed>@siu'
		);
		$body = preg_replace($toDelPattern, '', $phpmailer->Body);

		static $search = array('</td>', '</tr>', '<table', '</thead>', '</tbody>', '</table>');
		static $replace = array("</td>\t", "</tr>\n", "\n<table", "</thead>\n", "</tbody>\n", "</table>\n");
		$body = str_replace($search, $replace, $body);
		$body = trim(\wp_kses($body, array()));

		static $redondant = array("/\t+/", '/ +/', "/(\n[ \t]*\n[ \t]*)+/", "/\n[ \t]*/");
		static $single = array("\t", ' ', "\n\n", "\n");
		$phpmailer->AltBody = html_entity_decode(preg_replace($redondant, $single, $body));
	}

}
?>
