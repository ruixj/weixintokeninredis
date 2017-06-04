
<?php
// 中文
require_once( XRUI_PLUGIN_DIR . 'wx/redisjssdk.php' );
 
$jssdk = new JSSDK("yourappid", "yourappkey");
$signPackage = $jssdk->GetSignPackage();

?>
 

