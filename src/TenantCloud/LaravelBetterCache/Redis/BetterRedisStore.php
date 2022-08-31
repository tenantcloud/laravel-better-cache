<?php

namespace TenantCloud\LaravelBetterCache\Redis;

use Illuminate\Cache\RedisStore;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Tests\Integration\BetterRedisStoreTest;

/**
 * @see BetterRedisStoreTest
 */
class BetterRedisStore extends RedisStore
{
	/**
	 * @inheritDoc
	 */
	public function tags($names): BetterRedisTaggedCache
	{
		return new BetterRedisTaggedCache(
			$this,
			new RedisTagSet($this, is_array($names) ? $names : func_get_args())
		);
	}

	public function tagKey(string $tag): string
	{
		return "tag:{$tag}:entries";
	}

	/**
	 * @return LazyCollection<int, string>
	 */
	public function tagList(int $chunkSize = 1000): LazyCollection
	{
		$prefix = $this->connectionPrefix() . $this->getPrefix();

		return LazyCollection::make(function () use ($chunkSize, $prefix) {
			$cursor = $defaultCursorValue = '0';

			do {
				[$cursor, $valuesChunk] = $this->connection()->scan(
					$cursor,
					['match' => $prefix . $this->tagKey('*'), 'count' => $chunkSize]
				);

				// PhpRedis client returns false if set does not exist or empty.
				if ($valuesChunk === null) {
					break;
				}

				$valuesChunk = array_unique($valuesChunk);

				if (empty($valuesChunk)) {
					continue;
				}

				foreach ($valuesChunk as $value) {
					yield $value;
				}
			} while (((string) $cursor) !== $defaultCursorValue);
		})->map(fn (string $tagKey) => Str::match('/^' . preg_quote($prefix) . 'tag:(.*):entries$/', $tagKey));
	}

	public function flushStale(): void
	{
		// Iterate over every tag and remove any expired entries from them in bulk.
		foreach ($this->tagList()->chunk(1000) as $tags) {
			$this->tags($tags->all())->flushStale();
		}
	}

	/**
	 * Returns a global prefix defined for the connection in the config.
	 *
	 * For whatever reason both predis and phpredis ignore it for `SCAN` command by default, though predis has an option to use it.
	 */
	private function connectionPrefix(): string
	{
		$connection = $this->connection();

		return match (true) {
			$connection instanceof PhpRedisConnection => $connection->_prefix(''),
			$connection instanceof PredisConnection   => $connection->getOptions()->prefix ?: '',
			default                                   => '',
		};
	}
}
