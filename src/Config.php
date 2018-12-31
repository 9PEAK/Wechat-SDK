<?php

namespace Peak\SDK\Wechat;

class Config
{


	protected static $config = [
		'app_id' => null,
		'app_secret' => null,
		'oauth_url' => null,

		'timestamp' => null,

		'cache_name' => null,
		'cache_path' => null,
		'cache_exp' => null,
	];



	static protected function config ($key, $val)
	{
		$val && @self::$config[$key]=$val;
		return @self::$config[$key];
	}

	/**
	 * 获取设置appid
	 * */
	static function appId($val=null)
	{
		return self::config('app_id', $val);
	}


	/**
	 * 获取设置appsecret
	 * */
	static function appSecret($val=null)
	{
		return self::config('app_secret', $val);
	}


	/**
	 * 获取设置授权回跳url
	 * */
	static function oauthUrl($val=null)
	{
		return self::config('oauth_url', $val);
	}


	/**
	 * 获取设置随机字符串
	 * */
	static function nonceStr($val=null)
	{
		return self::config('nonce_str', $val) ?: self::config('nonce_str', \Peak\Plugin\Str::random(6));
	}


	/**
	 * 获取/设置时间戳
	 * */
	static function timestamp($val=null)
	{
		return self::config('timestamp', $val) ?: self::config('timestamp', time());
	}


	/**
	 * 获取设置缓存类型
	 * */
	/*static function cacheType($val=null)
	{
		return self::config('cache_type', $val) ?: self::config('cache_type', time());
	}*/


	/**
	 * 获取/设置设置缓存名
	 * */
	static function cacheName($val=null)
	{
		return self::config('cache_name', $val) ?: self::config('cache_name', 'wechat-session-'.self::appId().'.json');
	}


	/**
	 * 获取|设置缓存文件存储位置
	 * */
	static function cachePath($val=null)
	{
		return self::config('cache_path', $val);
	}


	/**
	 * 获取缓存文件（绝对路径）
	 * */
	static function cacheFile()
	{
		return self::cachePath().self::cacheName();
	}


	/**
	 * 获取|设置缓存有效时间
	 * */
	static function cacheExp($val=null)
	{
		return self::config('cache_exp', $val);
	}



}