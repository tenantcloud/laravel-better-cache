<?php

namespace TenantCloud\LaravelBetterCache\FailSafe;

use ArrayAccess;
use Closure;
use DateInterval;
use DateTimeInterface;
use Generator;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\ForwardsCalls;
use RuntimeException;
use Tests\Integration\FailSafeRepositoryTest;
use Throwable;

/**
 * @see FailSafeRepositoryTest
 *
 * @implements ArrayAccess<mixed, mixed>
 */
class FailSafeRepository implements Repository, ArrayAccess
{
	use ForwardsCalls;

	public function __construct(
		private readonly Repository $delegate,
		private readonly Closure $reportFail,
	) {
	}

	/**
	 * Forward any custom methods to the original repository. We can't put a try-catch here because we don't know the expected return type.
	 *
	 * @param array<int, mixed> $arguments
	 */
	public function __call(string $name, array $arguments): mixed
	{
		return $this->forwardCallTo($this->delegate, $name, $arguments);
	}

	/**
	 * @inheritDoc
	 */
	public function has(string $key): bool
	{
		return $this->wrap(
			fn () => $this->delegate->has($key),
			'check if item exists in cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function missing(string $key): bool
	{
		return !$this->has($key);
	}

	/**
	 * @inheritDoc
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->get($key, $default),
			'get items from cache',
			fn () => value($default)
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<int|string, mixed> $keys
	 *
	 * @return array<string, mixed>
	 */
	public function many(array $keys): array
	{
		return $this->wrap(
			function () use ($keys) {
				/* @var array<string, mixed> */
				return $this->delegate->many($keys);
			},
			'get items from cache',
			static fn () => collect($keys)->mapWithKeys(fn ($value, $key) => [is_string($key) ? $key : $value => is_string($key) ? value($value) : null])->all()
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @param iterable<int, string> $keys
	 */
	public function getMultiple(iterable $keys, mixed $default = null): iterable
	{
		return $this->wrap(
			fn () => $this->delegate->getMultiple($keys, $default),
			'get items from cache',
			static fn () => array_fill_keys(collect($keys)->all(), value($default))
		);
	}

	/**
	 * @inheritDoc
	 */
	public function put($key, $value, $ttl = null): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->put($key, $value, $ttl),
			'put items into cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
	{
		return $this->put($key, $value, $ttl);
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<string, mixed>                    $values
	 * @param DateTimeInterface|DateInterval|int|null $ttl
	 */
	public function putMany(array $values, $ttl = null): bool
	{
		return $this->wrap(
			function () use ($values, $ttl) {
				/* @var bool */
				return $this->delegate->putMany($values, $ttl);
			},
			'put items into cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @param iterable<string, mixed> $values
	 */
	public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
	{
		return $this->wrap(
			fn () => $this->delegate->setMultiple($values, $ttl),
			'put items into cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function forever($key, $value): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->forever($key, $value),
			'put items into cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function add($key, $value, $ttl = null): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->add($key, $value, $ttl),
			'add items into cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function remember($key, $ttl, Closure $callback): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->remember($key, $ttl, $callback),
			'remember items into cache',
			fn () => $callback()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function sear($key, Closure $callback): mixed
	{
		return $this->rememberForever($key, $callback);
	}

	/**
	 * @inheritDoc
	 */
	public function rememberForever($key, Closure $callback): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->rememberForever($key, $callback),
			'remember items into cache',
			fn () => $callback()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function pull($key, $default = null): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->pull($key, $default),
			'pull items from cache',
			fn () => value($default)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function increment($key, $value = 1): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->increment($key, $value),
			'increment key in cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function decrement($key, $value = 1): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->decrement($key, $value),
			'decrement key in cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function forget($key): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->forget($key),
			'forget items from cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function delete(string $key): bool
	{
		return $this->forget($key);
	}

	/**
	 * @inheritDoc
	 */
	public function deleteMultiple(iterable $keys): bool
	{
		return $this->wrap(
			fn () => $this->delegate->deleteMultiple($keys),
			'forget items from cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function clear(): bool
	{
		return $this->flush();
	}

	/**
	 * @inheritDoc
	 */
	public function flush(): bool
	{
		return $this->wrap(
			fn () => $this->delegate->clear(),
			'flush cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function flushStale(): void
	{
		$this->wrap(
			function () {
				$this->delegate->flushStale();
			},
			'flush stale cache',
			null
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @param string|array<string> $names
	 */
	public function tags(...$names): Repository
	{
		return new self(
			$this->delegate->tags($names),
			$this->reportFail
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @return LazyCollection<int, string>
	 */
	public function tagList(int $chunkSize = 1000): LazyCollection
	{
		return $this->wrap(
			function () use ($chunkSize) {
				/** @var LazyCollection<int, string> $list */
				$list = $this->delegate->tagList($chunkSize);

				return LazyCollection::make(function () use ($list): Generator {
					try {
						yield from $list;
					} catch (Throwable $e) {
						($this->reportFail)(new RuntimeException('Failed to get tag list from cache.', previous: $e));
					}
				});
			},
			'get tag list from cache',
			fn () => LazyCollection::empty()
		);
	}

	/**
	 * @inheritDoc
	 *
	 * @return LazyCollection<int, string>
	 */
	public function entries(int $chunkSize = 1000): LazyCollection
	{
		return $this->wrap(
			function () use ($chunkSize) {
				/** @var LazyCollection<int, string> $list */
				$list = $this->delegate->entries($chunkSize);

				return LazyCollection::make(function () use ($list): Generator {
					try {
						yield from $list;
					} catch (Throwable $e) {
						($this->reportFail)(new RuntimeException('Failed to get entries list from cache.', previous: $e));
					}
				});
			},
			'get entries list from cache',
			fn () => LazyCollection::empty()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetExists($key): bool
	{
		return $this->wrap(
			fn () => isset($this->delegate[$key]),
			'check if item exists in cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetGet($key): mixed
	{
		return $this->wrap(
			fn () => $this->delegate[$key],
			'get items from cache',
			null
		);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetSet($key, $value): void
	{
		$this->wrap(
			function () use ($key, $value) {
				$this->delegate[$key] = $value;
			},
			'put items into cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetUnset($key): void
	{
		$this->wrap(
			function () use ($key) {
				unset($this->delegate[$key]);
			},
			'forget items from cache',
			false
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getStore(): Store
	{
		return $this->delegate->getStore();
	}

	/**
	 * @template R
	 *
	 * @param callable(): R   $call
	 * @param R|callable(): R $return
	 *
	 * @return R
	 */
	private function wrap(callable $call, string $errorMessage, mixed $return): mixed
	{
		try {
			return $call();
		} catch (Throwable $e) {
			($this->reportFail)(new RuntimeException("Failed to {$errorMessage}.", previous: $e));

			return value($return);
		}
	}
}
