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
	}




	/**
	 * 获取缓存
	 * @param $key string. the specific key you want
	 * @return string|object, return the value of the key you want, otherwise return all the cache when the key param is null.
	 */
	private static function get_cache ($key=null)
	{
		$file = Config::cacheFile();
		$file = file_exists($file) ? file_get_contents($file) : null;
		$file = json_decode($file ?: '{}');
		return $key ? (@$file->expires_in>=Config::timestamp() ? @$file->$key : null) : $file;
	}



	/**
	 * 保存缓存
	 * @param $key string|array, the specific key you want to save, if it's a array , all the key,value will be saved
	 * @param $val mixed|null, thg value for key, if key is an array, it will be ignored.
	 * */
	private static function save_cache ($key, $val=null)
	{
		if (is_string($key)) {
			$dat = self::get_cache();
			$dat->$key = $val;
		} else {
			$dat = [];
			foreach ($key as $k=>&$val) {
				$dat[$k]= $val;
			}
		}
		$file = Config::cacheFile();
		$res = file_put_contents($file, json_encode($dat));
		if (is_executable($file)) {
			@chmod($file, 0660);
		}
		return $res;
	}




	/**
	 * 获取全局AccessToken
	 *
	 * @return object|null return object if seccess, otherwise failed.
	 * */
	public function getAccessToken()
	{
		// 缓存获取
		if ($token=self::get_cache('access_token')) {
			return $token;
		}

		// 接口获取
		$res = $this->reqAccessToken();
		if (@$res->errcode) {
			return self::debug($res);
		}

		self::save_cache([
			'access_token' => $res->access_token,
			'expires_in' => Config::timestamp()+Config::cacheExp()
		]);
//		echo 777;
//		print_r($res);exit;
		return $res->access_token;
	}





	/**
	 * get js config for web front-end
	 * */
	public function getJsConfig ($url)
	{
		$ticket = self::get_cache('ticket');
		if (!$ticket) {
			$token = $this->getAccessToken();
			if (!$token) return;
			$res = $this->reqJsTicket($token);
			if (!$res || @$res->errcode) return self::debug($res);
			$ticket = $res->ticket;
			self::save_cache('ticket', $ticket);
		}
		return $this->makeJsConfig($url, $ticket);
	}



	############## 素材

	public function getMedia ($id, $url=false)
	{
		return self::reqMedia($this->getAccessToken(), $id, (bool)$url);
	}



}
