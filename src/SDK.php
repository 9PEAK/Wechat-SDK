<?php

namespace Peak\SDK\Wechat;

class SDK
{

	use \Peak\Plugin\Debuger;

	private static $http;

	protected static function http_get($url)
	{
		$res = file_get_contents($url);
		$res = json_decode($res);
		return @$res->errcode ? self::debug($res) : $res;
	}

	function __construct($appId=null, $appSecret=null)
	{

		Config::appId($appId);
		Config::appSecret($appSecret);
//		self::$http = new \Curl\Curl();
	}

	const DOMAIN = 'https://api.weixin.qq.com/';

	const PATH_BASE = 'cgi-bin/';
	const URL_ACCESS_TOKEN = self::DOMAIN.self::PATH_BASE.'token?grant_type=client_credential&appid={appid}&secret={appsecret}';


	/**
	 * 获取全局AccessToken
	 *
	 * @return object|null return object if seccess, otherwise failed.
	 * */
	public function reqAccessToken()
	{
		$url = str_replace('{appid}', Config::appId(), self::URL_ACCESS_TOKEN);
		$url = str_replace('{appsecret}', Config::appSecret(), $url);

		$res = json_decode(file_get_contents($url));
		return @$res->errcode ? self::debug($res) : $res;
	}



	const URL_JS_TICKET = self::DOMAIN.self::PATH_BASE.'ticket/getticket?type=jsapi&access_token=';

	/**
	 * 获取js临时票据(全局)
	 * */
	public function reqJsTicket ($accessToken)
	{
		$res = json_decode(
			file_get_contents(self::URL_JS_TICKET .$accessToken)
		);
		return @$res->errcode ? self::debug($res) : $res;
	}



	/**
	 * 根据js临时票据创建签名
	 * */
	public function signJsConfig ($url, $jsTicket)
	{
		$param = [
			'jsapi_ticket' => $jsTicket,
			'noncestr' => Config::nonceStr(),
			'timestamp' => Config::timestamp(),
			'url' => $url,
		];
//		print_r($param);exit;
		foreach ( $param as $k=>&$v ) {
			$v = $k.'='.$v ;
		}
		$param = join ( '&' , $param ) ;

		return sha1($param) ;
	}



	/**
	 * 创建JS前端Config参数
	 * */
	public function makeJsConfig ($url, $jsTicket)
	{
		return [
			'appid' => Config::appId() ,
			'timestamp' => Config::timestamp(),
			'nonce_str' => Config::nonceStr(),
			'js_signature' => $this->signJsConfig($url, $jsTicket)
		];
	}


	const PATH_OAUTH = 'connect/oauth2/';

	/**
	 * OAuth 授权跳转接口
	 * @param $callback string $callback 回调URI
	 * @param $scope string $callback 回调URI
	 * @param $state string $state 重定向后会带上state参数，企业可以填写a-zA-Z0-9的参数值
	 * @return string
	 */
	const URL_OAUTH = 'https://open.weixin.qq.com/'.self::PATH_OAUTH.'authorize?appid={appid}&redirect_uri={callback}&response_type=code&scope={scope}&state={state}#wechat_redirect';
	public function reqOauthRedirectUrl ($callback=null, $state='9peak', $scope='snsapi_base')
	{
		$url = str_replace('{appid}', Config::appId(), self::URL_OAUTH);
		$url = str_replace('{callback}', urlencode($callback ?: Config::oauthUrl()), $url);
		$url = str_replace('{scope}', $scope, $url);
		$url = str_replace('{state}', $state, $url);
		return $url;
	}


	/**
	 * OAuth 获取JS的AccessToken
	 *
	 * */
	const URL_OAUTH_ACCESS_TOKEN = self::DOMAIN.'sns/oauth2/access_token?appid={appid}&secret={secret}&code={code}&grant_type=authorization_code';
	public static function reqOauthAccessToken ($code)
	{
		$url = str_replace('{appid}', Config::appId(), self::URL_OAUTH_ACCESS_TOKEN);
		$url = str_replace('{secret}', Config::appSecret(), $url);
		$url = str_replace('{code}', $code, $url);
		$res = file_get_contents($url);
		$res = json_decode($res);
		return @$res->errcode ? self::debug($res) : $res;
	}



	/**
	 * Oauth 获取UserInfo
	 *
	 * */
	const URL_OAUTH_USER_INFO = 'https://api.weixin.qq.com/sns/userinfo?access_token={accessToken}&openid={openId}&lang={lang}';
	public static function reqOauthUserInfo ($accessToken, $openId, $lang='zh_CN')
	{
		$replace = compact([
			'accessToken', 'openId', 'lang'
		]);
		$url = self::URL_OAUTH_USER_INFO;
		foreach ($replace as $key=>&$val) {
			$url = str_replace('{'.$key.'}', $val, $url);
		}
		$res = file_get_contents($url);
		$res = json_decode($res);
		return @$res->errcode ? self::debug($res) : $res;
	}



	/**
	 * 获取临时素材
	 * */
	const URL_MEDIA = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=ACCESS_TOKEN&media_id=MEDIA_ID';
	public static function reqMedia ($accessToken, $mediaId, $returnUrl)
	{
		$url = str_replace('ACCESS_TOKEN', $accessToken, self::URL_MEDIA);
		$url = str_replace('MEDIA_ID', $mediaId, $url);
		return $returnUrl ? $url :self::http_get($url);
	}


}
