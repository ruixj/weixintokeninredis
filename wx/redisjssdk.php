<?php
require_once('redisconfig.php');
require_once('redislock.php');
class JSSDK {
  private $appId;
  private $appSecret;

  static function logmessage($msg)
  {
	$file = XRUI_PLUGIN_DIR.'log.txt';
	file_put_contents($file, $msg.PHP_EOL,FILE_APPEND);
  }

  public function __construct($appId, $appSecret) {
    $this->appId = $appId;
    $this->appSecret = $appSecret;

    $this->accesstokenkey = $appId . $appSecret . "_accesstoken";
    $this->jsticketkey = $appId . $appSecret . "_ticket";

    $this->JSTICKETFILE = XRUI_PLUGIN_DIR . 'wx/jsapi_ticket.php';
    $this->ACCESSTOKEN = XRUI_PLUGIN_DIR . 'wx/access_token.php';

  }

  public function getSignPackage() {
    $jsapiTicket = $this->getJsApiTicket();

    // 注意 URL 一定要动态获取，不能 hardcode.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    $timestamp = time();
    $nonceStr = $this->createNonceStr();

    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

    $signature = sha1($string);

    $signPackage = array(
      "appId"     => $this->appId,
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "url"       => $url,
      "signature" => $signature,
      "rawString" => $string
    );

    return $signPackage; 
  }

  private function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  private function getJsApiTicket() {
    $redis = new Redis();
    $redis->pconnect(REDIS_HOST, REDIS_PORT);
    $ticket = $redis->get($this->jsticketkey);
    $access_token = '';
    if($ticket)
    {
        JSSDK::logmessage("using existing ticket:" . $ticket);
    }
    else
    {
        $accessToken = $this->getAccessToken();
        $redisLock = new RedisLock($redis,"jsticketlock");
        $IsLocked = $redisLock->acquirelock();
        while (!$IsLocked)
            $IsLocked = $redisLock->acquirelock();
        if($IsLocked)
        {
            $ticket = $redis->get($this->jsticketkey);
            if(!$ticket)
            {
                $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
                $res = json_decode($this->httpGet($url));
                $ticket = $res->ticket;
                $expires_in = $res->expires_in - 300;
                if ($ticket) {
                    $ret = $redis->setEx($this->jsticketkey,$expires_in,$ticket);
                    JSSDK::logmessage("Got new jsticket key: ".$this->jsticketkey. $expires_in); 
                    if(!$ret)
                    {
                        JSSDK::logmessage('failed to store ticket to redis');
                    }
                }
                else
                {
                    JSSDK::logmessage('failed to get ticket from weixin');
                }
            }
            $redisLock->unlock();
        }
    }


    // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
    //$data = json_decode($this->get_php_file($JSTICKETFILE));

    //if ($data->expire_time < time()) {
     // $accessToken = $this->getAccessToken();
      // 如果是企业号用以下 URL 获取 ticket
      // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
      //$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
      //$res = json_decode($this->httpGet($url));
      //$ticket = $res->ticket;
      //if ($ticket) {
      //  $data->expire_time = time() + 6000;
      //  $data->jsapi_ticket = $ticket;
      //  $this->set_php_file($this->JSTICKETFILE, json_encode($data));
      //}
    //} else {
    	//JSSDK::logmessage("not expired"); 
      	//$ticket = $data->jsapi_ticket;
    //}

    return $ticket;
  }

  public function getAccessToken($forceNew=false) {
    // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
    $redis = new Redis();
    $redis->pconnect(REDIS_HOST, REDIS_PORT);
    $data = $redis->get($this->accesstokenkey);
    $access_token = '';

    if($data && !$forceNew)
    {
       JSSDK::logmessage($data);
       JSSDK::logmessage("Using the existing access totken:".$data);
       $access_token = $data;
    }
    else
    {
       $redisLock = new RedisLock($redis,"accesstokenlock");
        $IsLocked = $redisLock->acquirelock();
        while (!$IsLocked)
            $IsLocked = $redisLock->acquirelock();
        if($IsLocked)
        {
            $access_token = $redis->get($this->accesstokenkey);
            if( !$data)
            {
                JSSDK::logmessage("Getting new acccess token");
                $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
                $res = json_decode($this->httpGet($url));
                $access_token = $res->access_token;
                $expire_in = $res->expires_in - 300;
                if($access_token)
                {
                    JSSDK::logmessage("Got a new access_token:".$this->accesstokenkey . "expire_time:".$expire_in); 
                    $ret = $redis->setEx($this->accesstokenkey,$expire_in,$access_token);
                    if(!$ret)
                    {
                        JSSDK::logmessage('failed to store access_token to redis');
                    }
                }
                else
                {
                    JSSDK::logmessage('failed to get access_token from weixin');
                }
            }
            $redisLock->unlock();
        }
    }

    //$data = json_decode($this->get_php_file($this->ACCESSTOKEN));
    //if ($data->expire_time < time()|| $forceNew) {
      //// 如果是企业号用以下URL获取access_token
      //// $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
      //$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
      //$res = json_decode($this->httpGet($url));
      //$access_token = $res->access_token;
      //if ($access_token) {
        //$data->expire_time = time() + 6000;
        //$data->access_token = $access_token;
        //$this->set_php_file($this->ACCESSTOKEN, json_encode($data));
      //}
    //} else {
      //$access_token = $data->access_token;
    //}
    return $access_token;
  }

  private function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
    // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
  }

  private function get_php_file($filename) {
    return trim(substr(file_get_contents($filename), 15));
  }
  private function set_php_file($filename, $content) {
    $fp = fopen($filename, "w");
    fwrite($fp, "<?php exit();?>" . $content);
    fclose($fp);
  }
}

