<?php

namespace Tests\Unit\Console;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\NullStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Mockery;
use TenantCloud\LaravelBetterCache\Console\FlushStaleCommand;
use Tests\TestCase;

/**
 * @see FlushStaleCommand
 */
class FlushStaleCommandTest extends TestCase
{
	public function testFlushesStale(): void
	{
		$store = new Repository(
			Mockery::mock(Store::class)
				->expects()
				->flushStale()
				->getMock()
		);

		$this->mock(CacheManager::class)
			->expects('store')
			->with(null)
			->andReturn($store);

		$this->artisan(FlushStaleCommand::class)
			->assertSuccessful()
			->expectsOutput('Flushed stale cache data successfully.');
	}

	public function testFailsGracefullyWhenFlushingStaleIsNotSupported(): void
	{
		$store = new Repository(new NullStore());

		$this->mock(CacheManager::class)
			->expects('store')
			->with(null)
			->andReturn($store);

		$this->artisan(FlushStaleCommand::class)
			->assertExitCode(1)
			->expectsOutput('Given store does not support flushing stale data. Make sure the correct store name was given.');
	}
}
