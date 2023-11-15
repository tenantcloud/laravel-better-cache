<?php

namespace TenantCloud\LaravelBetterCache;

use Exception;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use TenantCloud\LaravelBetterCache\FailSafe\FailSafeRepository;

class BetterCacheServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->booting(function () {
			$cacheManager = $this->app->make(CacheManager::class);

			$cacheManager->extend('fail_safe', function (Container $app, array $config) {
				/** @var CacheManager $this */
				if (isset($this->customCreators[$config['delegate']['driver']])) {
					$delegate = $this->callCustomCreator($config['delegate']);
				} else {
					$driverMethod = 'create' . ucfirst($config['delegate']['driver']) . 'Driver';

					if (method_exists($this, $driverMethod)) {
						$delegate = $this->{$driverMethod}($config['delegate']);
					} else {
						throw new InvalidArgumentException("Driver [{$config['delegate']['driver']}] is not supported.");
					}
				}

				return new FailSafeRepository(
					$delegate,
					fn (Exception $e) => $app->make(LoggerInterface::class)->error($e->getMessage(), [
						'exception' => $e,
					])
				);
			});
		});
	}
}
