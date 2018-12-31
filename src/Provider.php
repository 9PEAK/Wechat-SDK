<?php

namespace Peak\SDK\Wechat;

trait Provider
{

	protected function registerWechatSdk ()
	{
		$this->app->singleton(
			Core::class,
			function (){
				return new Core ();
			}
		);
	}

}