<?php

namespace Peak\SDK\Wechat;

trait Provider
{


	/**
	 * 依赖注入 注册组件
	 * */
	protected function registerWechatSdk (array $config, $app='Laravel')
	{
		switch ($app) {
			case 'Laravel':
				$this->app->singleton(
					Core::class,
					function () use (&$config) {
						return new Core ($config);
					}
				);
				break;
		}


	}

}