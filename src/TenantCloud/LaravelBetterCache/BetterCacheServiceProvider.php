<?php

namespace TenantCloud\LaravelBetterCache;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use TenantCloud\LaravelBetterCache\Console\FlushStaleCommand;
use TenantCloud\LaravelBetterCache\Redis\BetterRedisStore;

class BetterCacheServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	public function register(): void
	{
		$this->app->booting(function () {
			$this->app->make(CacheManager::class)
				->extend('better_redis', function (Container $app, array $config) {
					/** @var CacheManager $this */
					$connection = $config['connection'] ?? 'default';

					$store = new BetterRedisStore($app['redis'], $this->getPrefix($config), $connection);

					return $this->repository(
						$store->setLockConnection($config['lock_connection'] ?? $connection)
					);
				});
		});
	}

	/**
	 * @inheritDoc
	 */
	public function boot(): void
	{
		if ($this->app->runningInConsole()) {
			$this->commands([
				FlushStaleCommand::class,
			]);
		}
	}
}
