<?php
if( !defined( 'ABSPATH' ) ) exit();
if( false === has_action( 'plugins_loaded', 'lws_adminpanel_init' ) )
{
	function lws_adminpanel_init()
	{
		global $LWS_Adminpanel_Instance;
		$LWS_Adminpanel_Instance = apply_filters('lws_adminpanel_instance', null);
		if( !is_null($LWS_Adminpanel_Instance) )
			$LWS_Adminpanel_Instance->init();
	}
	add_action('plugins_loaded', 'lws_adminpanel_init', -1);
}
?>
