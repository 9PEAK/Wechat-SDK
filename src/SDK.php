<?php

namespace Peak\SDK\Wechat;

class SDK
{

	use \Peak\Plugin\Debuger;

	protected static function http_response ($res)
	{
		$res = json_decode($res);
		return @$res->errcode ? self::debug($res->errmsg, $res->errcode) : $res;
	}

	protected static function http_get($url)
	{
		return self::http_response(file_get_contents($url));
	}

	/**
	 * post请求
	 * @param $url string
	 * @param $dat mixed 允许是字符串、数组、对象，函数内部将自动转化
	 * @param $file boolean 是否上传文件，默认false。
	 * */
	protected static function http_post($url, $dat, $file=false)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_POST, 1);
		if ($file) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($dat) ? $dat : json_decode($dat, 1));
		} else {
			curl_setopt($curl, CURLOPT_POSTFIELDS, is_string($dat) ? $dat : json_encode($dat, JSON_UNESCAPED_UNICODE));
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($curl);
		if (curl_errno($curl)) {
			return self::debug('Error: '.curl_error($curl));
		}
		curl_close($curl);
		return self::http_response($res);
	}



	function __construct($appId=null, $appSecret=null)
	{
		Config::appId($appId);
		Config::appSecret($appSecret);
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
		$res = self::http_get($url);
		return $res;
	}



	const URL_JS_TICKET = self::DOMAIN.self::PATH_BASE.'ticket/getticket?type=jsapi&access_token=';

	/**
	 * 获取js临时票据(全局)
	 * */
	public function reqJsTicket ($accessToken)
	{
		$res = self::http_get(self::URL_JS_TICKET .$accessToken);
		return @$res->errcode ? self::debug($res->errmsg, $res->errcode) : $res;
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
			'appId' => Config::appId() ,
			'timestamp' => Config::timestamp(),
			'nonceStr' => Config::nonceStr(),
			'signature' => $this->signJsConfig($url, $jsTicket)
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
		$res = self::http_get($url);
		return @$res->errcode ? self::debug($res->errmsg, $res->errcode) : $res;
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
		$res = self::http_get($url);
		return @$res->errcode ? self::debug($res->errmsg, $res->errcode) : $res;
	}



	###### 临时素材模块

	const URL_MEDIA = self::DOMAIN.'cgi-bin/media/upload?access_token=';


	/**
	 * 上传临时素材
	 * @param $accessToken string
	 * @param $type string 素材类型
	 * @param $file string 文件的绝对路径
	 * */
	public static function uploadMedia ($accessToken, $type, $file)
	{
		$url = self::URL_MEDIA.$accessToken;
		$url.= '&type='.$type;
		return self::http_post(
			$url,
			[
				'media' => new \CURLFile($file)
			],
			true
		);
	}




	/**
	 * 获取临时素材
	 * @param $accessToken string
	 * @param $mediaId string 素材id
	 * @param $returnUrl string 是否返回URL
	 * */
	const URL_MEDIA_GET = 'https://api.weixin.qq.com/cgi-bin/media/get?access_token=ACCESS_TOKEN&media_id=MEDIA_ID';
	public static function reqMedia ($accessToken, $mediaId, $returnUrl)
	{
		$url = str_replace('ACCESS_TOKEN', $accessToken, self::URL_MEDIA_GET);
		$url = str_replace('MEDIA_ID', $mediaId, $url);
		return $returnUrl ? $url :self::http_get($url);
	}


	const URL_CUSTOMER_SERVICE_MSG = self::DOMAIN.'cgi-bin/message/custom/send?access_token=';

	/**
	 * 客服·发送文字消息
	 * @param $openid string
	 * @param $msg string
	 * @param $accessToken string
	 * */
	public static function sendCustomerServiceText ($openid, $msg, $accessToken)
	{
		return self::http_post(
			self::URL_CUSTOMER_SERVICE_MSG.$accessToken,
			[
				'touser' => $openid,
				'msgtype' => 'text',
				'text' => [
					'content' => $msg
				],
			]
		);
	}


	/**
	 * 客服·发送图片消息
	 * @param $openid string
	 * @param $mediaId string
	 * @param $accessToken string
	 * */
	public static function sendCustomerServiceImg ($openid, $mediaId, $accessToken)
	{
		return self::http_post(
			self::URL_CUSTOMER_SERVICE_MSG.$accessToken,
			[
				'touser' => $openid,
				'msgtype' => 'image',
				'image' => [
					'media_id' => $mediaId
				],
			]
		);
	}



	/**
	 * 客服·发送图片消息
	 * @param $openid string
	 * @param $mediaId string
	 * @param $accessToken string
	 * */
	public static function sendCustomerServiceVoice ($openid, $mediaId, $accessToken)
	{
		return self::http_post(
			self::URL_CUSTOMER_SERVICE_MSG.$accessToken,
			[
				'touser' => $openid,
				'msgtype' => 'voice',
				'voice' => [
					'media_id' => $mediaId
				],
			]
		);
	}


	/**
	 * 客服·发送视频消息
	 * @param $openid string
	 * @param $video array 包含media_id素材id、thumb_media_id素材预览id、title标题、description描述四项属性。
	 * @param $accessToken string
	 * */
	public static function sendCustomerServiceVideo ($openid, array $video, $accessToken)
	{
		$tpl = [
			'media_id',
			'thumb_media_id',
			'title',
			'description',
		];
		foreach ($tpl as $key) {
			if (!array_key_exists($key, $video)) {
				return self::debug('参数“'.$key.'”缺失。');
			}
		}

		return self::http_post(
			self::URL_CUSTOMER_SERVICE_MSG.$accessToken,
			[
				'touser' => $openid,
				'msgtype' => 'video',
				'video' => $video,
			]
		);
	}



	### 消息模板

	const URL_TEMPLATE_MSG = 'https://api.weixin.qq.com/cgi-bin/template/';



	/**
	 * 获取模板列表
	 * @param $accessToken string
	 * */
	public function getAllTemplateMsg ($accessToken)
	{
		return self::http_get(self::URL_TEMPLATE_MSG.'get_all_private_template?access_token='.$accessToken);
	}




	/**
	 * 设置模板消息内容明细
	 * @param $dat array 消息模板参数集
	 * @param $key string 参数名
	 * @param $val string 参数值
	 * @param $color string 参数显示颜色，默认空字符串表示不设置颜色
	 * @return $this
	 * */
	public function setTemplateMsgContent (array &$dat, $key, $val, $color='')
	{
		$dat['data'][$key] = [
			'value' => urlencode($val),
			'color' => $color,
		];
		return $this;
	}



	/**
	 * 设置模板消息关联的小程序
	 * @param $dat array 消息模板参数集
	 * @param $id string 小程序appid
	 * @param $path string 小程序路由url
	 * @return $this
	 * */
	public function setTemplateMsgMiniprogram (array &$dat, $id, $path)
	{
		$dat['miniprogram'] = [
			'appid' => $id,
			'pagepath' => $path,
		];
		return $this;
	}


	/**
	 * 模板消息参数 设置url
	 * @param $dat array 消息模板参数集
	 * @param $url string 跳转url
	 * @return $this
	 * */
	public function setTemplateMsgUrl (array &$dat, $url)
	{
		$dat['url'] = $url;
		return $this;
	}



	/**
	 * 发送模板消息
	 * @param $accessToken string
	 * @param $openid string
	 * @param $tplId string 模板id
	 * @param $dat array 消息内容
	 *
	 * */
	public function sendTemplateMsg ($accessToken, $openid, $tplId, array $dat)
	{
		$dat = array_merge([
			[
				'touser' => $openid,
				'template_id' => $tplId,
			],
			$dat
		]);
		\Log::info(urldecode(json_encode($dat)));
		return self::http_post(
			'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$accessToken,
			urldecode(json_encode($dat))
		);
	}



}
