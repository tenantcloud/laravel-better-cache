<?php

namespace Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Orchestra\Testbench\TestCase as BaseTestCase;
use TenantCloud\LaravelBetterCache\BetterCacheServiceProvider;

class TestCase extends BaseTestCase
{
	use WithFaker;

	protected function getPackageProviders($app): array
	{
		return [
			BetterCacheServiceProvider::class,
		];
	}

	protected function resolveApplicationConfiguration($app)
	{
		parent::resolveApplicationConfiguration($app);

		$app['config']->set('cache.stores.redis.driver', 'better_redis');

		$app['config']->set('cache.stores.redis_fail_safe', [
			'driver'   => 'fail_safe',
			'delegate' => [
				'driver'     => 'better_redis',
				'connection' => 'cache',
			],
		]);

		$app['config']->set('database.redis.failing_cache', $app['config']->get('database.redis.cache'));
		$app['config']->set('database.redis.failing_cache.host', $app['config']->get('database.redis.failing_cache.host') . 'sad');
		$app['config']->set('cache.stores.failing_redis_fail_safe', [
			'driver'   => 'fail_safe',
			'delegate' => [
				'driver'     => 'better_redis',
				'connection' => 'failing_cache',
			],
		]);
	}
}
