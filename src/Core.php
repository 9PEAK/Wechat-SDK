<?php

namespace Peak\SDK\Wechat;

class Core extends SDK
{


	function __construct(array $config)
	{
		parent::__construct($config['app_id'], $config['app_secret']);

		Config::oauthUrl($config['oauth_url']);
		Config::cacheName($config['cache_name']);
		Config::cachePath($config['cache_path']);
		Config::cacheExp($config['cache_exp']);

		self::$cache = new \Peak\Plugin\FileCache(Config::cacheFile());
	}


	private static $cache;


	/**
	 * 获取/设置缓存
	 * @param $key mixed 默认空，表示获取所有缓存数据，否则如果$key为string，则表示获取指定key的值；如果传入的$key是数组，则表示存储数据，仅更新传入的数据。
	 * @return mixed|false 异常时返回false，否则返回数据
	 * */
	private static function cache ($key=null)
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
		return $key ? (@$dat->expires_in>=Config::timestamp() ? @$dat->$key : null) : $dat;
	}



	protected $token, $ticket;


	/**
	 * 获取全局AccessToken
	 *
	 * @return object|null return object if seccess, otherwise failed.
	 * */
	public function getAccessToken()
	{
		// 缓存获取
		if ($token=self::cache('access_token')) {
			return $token;
		}

		// 接口获取
		if ($res=$this->reqAccessToken()) {
			self::cache([
				'access_token' => $res->access_token,
				'expires_in' => Config::timestamp()+Config::cacheExp()
			]);
			return $res->access_token;
		}

	}





	/**
	 * get js config for web front-end
	 * */
	public function getJsConfig ($url)
	{
		$ticket = self::cache('ticket');
		if (!$ticket) {
			$token = $this->getAccessToken();
			if (!$token) return;

			$res = $this->reqJsTicket($token);
			if (!$res) return;

			$ticket = $res->ticket;
			self::cache('ticket', $ticket);
		}
		return $this->makeJsConfig($url, $ticket);
	}



	############## 素材

	public function getMedia ($id, $url=false)
	{
		return self::reqMedia($this->getAccessToken(), $id, (bool)$url);
	}



}
