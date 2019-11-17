<?php

namespace Peak\SDK\Wechat;

class Core
{

	use \Peak\Plugin\Debuger\Base;



	function __construct(array $config)
	{
		Config::appId($config['app_id']);
		Config::appSecret($config['app_secret']);
		Config::oauthUrl($config['oauth_url']);
		Config::cacheName($config['cache_name']);
		Config::cachePath($config['cache_path']);
		Config::cacheExp($config['cache_exp']);

		self::$cache = new \Peak\Plugin\FileCache(Config::cacheFile(), 0666);
	}

	protected static $cache;

	/**
	 * 获取/设置缓存
	 * @param $key mixed 默认空，表示获取所有缓存数据，否则如果$key为string，则表示获取指定key的值；如果传入的$key是数组，则表示存储数据，仅更新传入的数据。
	 * @return mixed|false 异常时返回false，否则返回数据
	 * */
	protected static function cache ($key=null)
	{
		# 存储缓存
		if (is_array($key)||is_object($key)) {
			$dat = self::{__FUNCTION__}();

			if ($dat===false) {
				return false;
			}

			return self::$cache->content(array_merge((array)$dat, (array)$key)) ?: self::debug(self::$cache->debug());
		}

		# 获取缓存
		$dat = self::$cache->content();
		if ($dat===false) {
			return self::debug(self::$cache->debug());
		}
		$dat = json_decode($dat ?: '{}');
		return @$dat->expires_in>=time() ? ($key ? @$dat->$key : $dat) : (object)[];
	}



	/**
	 * HTTP请求
	 * @param $url string 请求url地址
	 * @param $post mixed post请求参数，默认为空，表示get请求
	 * @param $formData bool 是否是FormData类型数据，true时通常用于传输文件
	 * @return
	 * */
	protected static function http ($url, $post=null, $formData=false)
	{
	    try {
            if ($post) {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($formData) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($post) ? $post : json_decode($post, 1));
                } else {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, is_string($post) ? $post : json_encode($post, JSON_UNESCAPED_UNICODE));
                }

                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                $res = curl_exec($curl);
                if (curl_errno($curl)) {
                    return self::debug('Response: '.curl_error($curl));
                }
                curl_close($curl);
            } else {
                $res = file_get_contents($url);
            }
        } catch (\Exception $e) {
	        return self::debug($e->getMessage());
        }


		$res = json_decode($res);
		return @$res->errcode ? self::debug($res) : $res;
	}


	protected $access_token, $expires_in, $ticket;


	const URL = 'https://api.weixin.qq.com/';
	const URL_API = self::URL.'cgi-bin/';

	const URL_ACCESS_TOKEN = self::URL_API.'token?grant_type=client_credential&';

	/**
	 * 获取全局AccessToken
	 * @return string|false return token string if seccess, otherwise failed.
	 * */
	public function getAccessToken()
	{

		// 内置内存获取
		if ($this->access_token && $this->expires_in>=time()) {
			return $this->access_token;
		}

		// 缓存获取: 判断缓存时间并获取数据
		$res = self::cache();
		if (@$res->access_token) {
			$this->expires_in = $res->expires_in;
			$this->access_token = $res->access_token;
			return $this->{__FUNCTION__}();
		} elseif ($res===false) {
			return false;
		}

		// 接口获取: 缓存至本地
		$url = self::URL_ACCESS_TOKEN.'appid='.Config::appId();
		$url.= '&secret='.Config::appSecret();
		if ($res=self::http($url)) {
			$res = self::cache([
				'access_token' => $res->access_token,
				'expires_in' => Config::timestamp()+Config::cacheExp()
			]);
		}

		return $res ? $this->{__FUNCTION__}() : $res;
	}



	const URL_JS_TICKET = self::URL_API.'ticket/getticket?type=jsapi&access_token=';

	/**
	 * 获取js临时票据(全局)
	 * */
	public function getJsTicket ()
	{

		// 内置内存获取
		if ($this->ticket && $this->expires_in>=time()) {
			return $this->ticket;
		}

		// 缓存中获取
		$res = self::cache();
		if (@$res->ticket) {
			$this->expires_in = $res->expires_in;
			$this->ticket = $res->ticket;
			return $this->{__FUNCTION__}();
		} elseif ($res===false) {
			return false;
		}

		// 接口获取
		if ($res=self::http(self::URL_JS_TICKET.$this->getAccessToken())) {
			$res = self::cache([
				'ticket' => $res->ticket
			]);
		}

		return $res ? $this->{__FUNCTION__}() : $res;
	}


	/**
	 * 根据js临时票据创建签名
	 * @param $url string 需要调用jssdk地前端页面
	 * @param $ticket string 临时票据，默认null，将自动使用内部方法获取
	 * */
	public function signJsConfig ($url, $ticket=null)
	{
		$param = [
			'jsapi_ticket' => $ticket ?: $this->getJsTicket(),
			'noncestr' => Config::nonceStr(),
			'timestamp' => Config::timestamp(),
			'url' => $url,
		];

		foreach ( $param as $k=>&$v ) {
			$v = $k.'='.$v ;
		}
		$param = join ( '&' , $param ) ;
		return sha1($param) ;
	}



	/**
	 * get js config for web front-end
	 * @param $url string 前端页面url
	 * @return array|false
	 * */
	public function getJsConfig ($url)
	{
		if ($ticket = $this->getJsTicket()) {
			return [
				'appId' => Config::appId() ,
				'timestamp' => Config::timestamp(),
				'nonceStr' => Config::nonceStr(),
				'signature' => $this->signJsConfig($url, $ticket)
			];
		}
		return $ticket;
	}




	/**
	 * 获取 OAuth 授权跳转URL
	 * @param $scope bool $scope 默认false，基础授权，只能获取用户openid；true，则可以获取昵称、头像、性别等信息，用户未关注公众号时需要授权。
	 * @param $state string $state 重定向后会带上state参数，企业可以填写a-zA-Z0-9的参数值
	 * @param $callback string $callback 回调URI
	 *
	 * @return string
	 */
	const URL_OAUTH = 'https://open.weixin.qq.com/connect/oauth2/authorize?';
	public function getOauthRedirectUrl ($scope=false, $state='9peak', $callback=null)
	{
		$url = self::URL_OAUTH.'appid='.Config::appId();
		$url.= '&redirect_uri='.urlencode($callback ?: Config::oauthUrl());
		$url.= '&response_type=code';
		$url.= '&scope='.($scope ? 'snsapi_userinfo' : 'snsapi_base');
		$url.= '&state='.$state;
		$url.= '#wechat_redirect';
		return $url;
	}


    /**
     * 前往访问 OAuther 跳转URL
     * @param bool $scope
     * @param string $state
     * @param null $callback
     */
    public function toOauthRedirectUrl ($scope=false, $state='9peak', $callback=null)
    {
        header('location: '.$this->getOauthRedirectUrl($scope, $state, $callback));
        exit;
    }


    /**
     * 前往OAuth跳转后所制定的URL
     * @param string $url
     */
    public function toOauthBackUrl ($url='')
    {
        $url = $url ?: Config::oauthUrl();
        header('location: '.$url);
        exit;
    }




	/**
	 * OAuth 获取JS的AccessToken
	 * @param $code string 回跳url携带的code参数
	 * @return object|false
	 * */
	const URL_OAUTH_ACCESS_TOKEN = self::URL.'sns/oauth2/access_token?appid={appid}&secret={secret}&code={code}&grant_type=authorization_code';
	public static function getOauthAccessToken ($code)
	{
		$url = str_replace('{appid}', Config::appId(), self::URL_OAUTH_ACCESS_TOKEN);
		$url = str_replace('{secret}', Config::appSecret(), $url);
		$url = str_replace('{code}', $code, $url);
		$res = self::http($url);
		return @$res->errcode ? self::debug($res) : $res;
	}




	/**
	 * Oauth 获取UserInfo
	 *
	 * */
	const URL_OAUTH_USER_INFO = self::URL.'sns/userinfo?';
	public function getOauthUserInfo ($openId, $lang='zh_CN')
	{
		$url = self::URL_OAUTH_USER_INFO;

		if ($token = $this->getAccessToken()) {
			$url.= 'access_token='.$token;
			$url.= '&openid='.$openId;
			$url.= '&lang='.$lang;
		} else {
			return false;
		}

		return self::http($url);
	}






	###### 临时素材模块


	const URL_MEDIA = self::URL_API.'media/';

	/**
	 * 获取临时素材
	 * @param $mediaId string 素材id
	 * @param $returnUrl string 是否返回URL
	 * */
	public function getMedia ($mediaId, $returnUrl)
	{
		if ($token=$this->getAccessToken()) {
			$url = self::URL_MEDIA.'get?';
			$url.= 'access_token='.$token;
			$url.= '&media_id='.$mediaId;
			return $returnUrl ? $url :self::http($url);
		}

		return false;
	}



	/**
	 * 上传临时素材
	 * @param $type string 素材类型
	 * @param $file string 文件的绝对路径
	 * */
	public function uploadMedia ($type, $file)
	{
		if ($token=$this->getAccessToken()) {
			$url = self::URL_MEDIA.'upload?';
			$url.= '&access_token='.$token;
			$url.= '&type='.$type;
			return self::http(
				$url,
				[
					'media' => new \CURLFile($file)
				],
				true
			);
		}

		return false;
	}







	const URL_CUSTOMER_SERVICE_MSG = self::URL_API.'message/custom/';

	/**
	 * 客服·发送文字消息
	 * @param $openid string
	 * @param $msg string
	 * */
	public function sendCustomerServiceText ($openid, $msg)
	{
		if ($token=$this->getAccessToken()) {
			return self::http(
				self::URL_CUSTOMER_SERVICE_MSG.'send?access_token='.$token,
				[
					'touser' => $openid,
					'msgtype' => 'text',
					'text' => [
						'content' => $msg
					],
				]
			);
		}

		return false;
	}


	/**
	 * 客服·发送图片消息
	 * @param $openid string
	 * @param $mediaId string
	 * */
	public function sendCustomerServiceImg ($openid, $mediaId)
	{
		if ($token=$this->getAccessToken()) {
			return self::http(
				self::URL_CUSTOMER_SERVICE_MSG.'send?access_token='.$token,
				[
					'touser' => $openid,
					'msgtype' => 'image',
					'image' => [
						'media_id' => $mediaId
					],
				]
			);
		}

		return false;
	}



	/**
	 * 客服·发送图片消息
	 * @param $openid string
	 * @param $mediaId string
	 * */
	public function sendCustomerServiceVoice ($openid, $mediaId)
	{
		if ($token=$this->getAccessToken()) {
			return self::http(
				self::URL_CUSTOMER_SERVICE_MSG.'send?access_token='.$token,
				[
					'touser' => $openid,
					'msgtype' => 'voice',
					'voice' => [
						'media_id' => $mediaId
					],
				]
			);
		}
		return false;
	}


	/**
	 * 客服·发送视频消息
	 * @param $openid string
	 * @param $video array 包含media_id素材id、thumb_media_id素材预览id、title标题、description描述四项属性。
	 * */
	public function sendCustomerServiceVideo ($openid, array $video)
	{
		if ($token=$this->getAccessToken()) {
			$tpl = [
				'media_id',
				'thumb_media_id',
				'title',
				'description',
			];
			foreach ($tpl as $key) {
				if (!array_key_exists($key, $video)) {
					return self::debug('Request: 参数“'.$key.'”缺失。');
				}
			}
			return self::http(
				self::URL_CUSTOMER_SERVICE_MSG.'send?access_token='.$token,
				[
					'touser' => $openid,
					'msgtype' => 'video',
					'video' => $video,
				]
			);
		}
		return false;
	}



	### 消息模板

	const URL_TEMPLATE_MSG = self::URL_API.'template/';



	/**
	 * 获取模板列表
	 * @return array|false
	 * */
	public function getAllTemplateMsg ()
	{
		if ($token=$this->getAccessToken()) {
			return self::http(self::URL_TEMPLATE_MSG.'get_all_private_template?access_token='.$token);
		}
		return false;
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
			'value' => $val,
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
	 * @param $openid string
	 * @param $tplId string 模板id
	 * @param $dat array 消息内容
	 *
	 * */
	public function sendTemplateMsg ($openid, $tplId, array $dat)
	{
		if ($token=$this->getAccessToken()) {
			return self::http(
				self::URL_API.'message/template/send?access_token='.$token,
				array_merge(
					[
						'touser' => $openid,
						'template_id' => $tplId,
					],
					$dat
				)
			);
		}
		return false;
	}


}
