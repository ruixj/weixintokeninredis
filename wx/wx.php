
<?php
// 中文
require_once( XRUI_PLUGIN_DIR . 'wx/redisjssdk.php' );
 
$jssdk = new JSSDK("wx94d7f781c80eeb2b", "209062295c281505026e5fbbfcd309c2");
$signPackage = $jssdk->GetSignPackage();

?>
 

