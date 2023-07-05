<?php

namespace TenantCloud\LaravelBetterCache\Redis;

use Illuminate\Cache\TagSet;
use Illuminate\Support\LazyCollection;
use Tests\Integration\BetterRedisStoreTest;

/**
 * @see BetterRedisStoreTest
 */
class RedisTagSet extends TagSet
{
	public function resetTag($name): string
	{
		$this->store->forget($this->tagKey($name));

		return $this->tagId($name);
	}

	public function flushTag($name): void
	{
		$this->resetTag($name);
	}

	public function tagId($name): string
	{
		return $this->tagKey($name);
	}

	public function tagKey($name): string
	{
		return $this->store->tagKey($name);
	}

	/**
	 * @return LazyCollection<int, string>
	 */
	public function entries(): LazyCollection
	{
		return LazyCollection::make(function () {
			foreach ($this->tagIds() as $tagKey) {
				$cursor = $defaultCursorValue = '0';

				do {
					[$cursor, $valuesChunk] = $this->store->connection()->zscan(
						$this->store->getPrefix() . $tagKey,
						$cursor,
						['match' => '*', 'count' => 1000]
					);

					// PhpRedis client returns false if set does not exist or empty.
					if ($valuesChunk === null) {
						break;
					}

					$valuesChunk = array_unique(array_keys($valuesChunk));

					if (count($valuesChunk) === 0) {
						continue;
					}

					foreach ($valuesChunk as $value) {
						yield $value;
					}
				} while (((string) $cursor) !== $defaultCursorValue);
			}
		});
	}

	public function addEntry(string $key, int $ttl = 0): void
	{
		$ttl = $ttl > 0 ? now()->addSeconds($ttl)->getTimestamp() : -1;

		foreach ($this->tagIds() as $tagKey) {
			$this->store->connection()->zadd($this->store->getPrefix() . $tagKey, $ttl, $key);
		}
	}

	public function flushValues(): void
	{
		$entries = $this->entries()
			->map(fn (string $key) => $this->store->getPrefix() . $key)
			->chunk(1000);

		foreach ($entries as $itemKeys) {
			$this->store->connection()->del(...$itemKeys);
		}
	}

	public function flushStale(): void
	{
		$this->store->connection()->pipeline(function ($pipe) {
			foreach ($this->tagIds() as $tagKey) {
				$pipe->zremrangebyscore($this->store->getPrefix() . $tagKey, 0, now()->getTimestamp());
			}
		});
	}
}
