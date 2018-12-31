<?php

namespace Peak\SDK\Wechat;

class Config
{
/*
	protected static $app_id;
	protected static $app_secret;
	protected static $oauth_url;
	protected static $nonce_str;
	protected static $timestamp;

	protected static $cookie;
	protected static $cache_file;
*/

	const CONFIG = 'services.wechat.';

	static protected function config ($key, $val)
	{
		$key = self::CONFIG.$key;
		$val && config()->set($key, $val);
		return config($key);
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
		return self::config('nonce_str', $val) ?: self::config('nonce_str', \Peak\Tool\Str::random(6));
	}


	/**
	 * 获取设置随机字符串
	 * */
	static function timestamp($val=null)
	{
		return self::config('timestamp', $val) ?: self::config('timestamp', time());
	}


	/**
	 * 获取设置随机字符串
	 * */
	static function cacheType($val=null)
	{
		return self::config('cache_type', $val) ?: self::config('cache_type', time());
	}


	/**
	 * 获取设置缓存名
	 * */
	static function cacheName()
	{
		return self::config('cache_name', null)
				?: !self::appId() ?: self::config('cache_name', '9peak-wechat-cookie-'.self::appId());
	}


	/**
	 * 获取|设置缓存文件存储文件
	 * */
	static function cacheFile()
	{
		return self::config('cache_file', null)
				?: self::config('cache_file', !self::cacheName() ?: storage_path('framework/cache/').self::cacheName().'.json');
	}


	/**
	 * 获取|设置缓存有效时间
	 * */
	static function cacheExp($val=null)
	{
		return self::config('cache_exp', $val);
	}







}