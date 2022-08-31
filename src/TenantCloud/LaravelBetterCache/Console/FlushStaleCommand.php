<?php

namespace TenantCloud\LaravelBetterCache\Console;

use BadMethodCallException;
use Illuminate\Cache\CacheManager;
use Illuminate\Console\Command;
use Tests\Unit\TenantCloud\LaravelBetterCache\Console\FlushStaleCommandTest;

/**
 * @see FlushStaleCommandTest
 */
class FlushStaleCommand extends Command
{
	/** @inheritDoc */
	protected $signature = 'cache:flush-stale {store? : The store to clean up}';

	/** @inheritDoc */
	protected $description = 'Flushes any stale data from the cache';

	/**
	 * @inheritDoc
	 */
	public function __construct(
		private readonly CacheManager $cache
	) {
		parent::__construct();
	}

	/**
	 * @inheritDoc
	 */
	public function handle(): int
	{
		$this->laravel['events']->dispatch(
			'cache:flushing-stale',
			[$this->argument('store')]
		);

		$repository = $this->cache->store($this->argument('store'));

		try {
			$repository->flushStale();
		} catch (BadMethodCallException) {
			$this->warn('Given store does not support flushing stale data. Make sure the correct store name was given.');

			return 1;
		}

		$this->laravel['events']->dispatch(
			'cache:flushed-stale',
			[$this->argument('store')]
		);

		$this->info('Flushed stale cache data successfully.');

		return 0;
	}
}
