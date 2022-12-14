<?php

namespace TenantCloud\LaravelBetterCache\Redis;

use Illuminate\Cache\TaggedCache;
use Illuminate\Support\LazyCollection;
use Tests\Integration\BetterRedisStoreTest;

/**
 * @see BetterRedisStoreTest
 */
class BetterRedisTaggedCache extends TaggedCache
{
	/**
	 * @inheritDoc
	 */
	public function put($key, $value, $ttl = null)
	{
		if ($ttl === null) {
			return $this->forever($key, $value);
		}

		$this->tags->addEntry($this->itemKey($key), $this->getSeconds($ttl));

		return parent::put($key, $value, $ttl);
	}

	/**
	 * @inheritDoc
	 */
	public function forever($key, $value)
	{
		$this->tags->addEntry($this->itemKey($key));

		return parent::forever($key, $value);
	}

	/**
	 * @inheritDoc
	 */
	public function increment($key, $value = 1)
	{
		$this->tags->addEntry($this->itemKey($key));

		return parent::increment($key, $value);
	}

	/**
	 * @inheritDoc
	 */
	public function decrement($key, $value = 1)
	{
		$this->tags->addEntry($this->itemKey($key));

		return parent::decrement($key, $value);
	}

	/**
	 * @inheritDoc
	 */
	public function flush(): bool
	{
		$this->tags->flushValues();
		$this->tags->flush();

		return true;
	}

	public function flushStale(): void
	{
		$this->tags->flushStale();
	}

	/**
	 * @return LazyCollection<int, string>
	 */
	public function entries(): LazyCollection
	{
		return $this->tags->entries();
	}
}
