<?php

namespace Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Orchestra\Testbench\TestCase as BaseTestCase;
use TenantCloud\LaravelBetterCache\BetterCacheServiceProvider;

class TestCase extends BaseTestCase
{
	use WithFaker;

	/**
	 * @inheritDoc
	 */
	protected function getPackageProviders($app): array
	{
		return [
			BetterCacheServiceProvider::class,
		];
	}

	protected function resolveApplicationConfiguration($app)
	{
		parent::resolveApplicationConfiguration($app);

		$app['config']['cache.stores.redis.driver'] = 'better_redis';
	}
}
