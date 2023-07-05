<?php

namespace TenantCloud\LaravelBetterCache\FailSafe;

use Closure;
use DateInterval;
use DateTimeInterface;
use Generator;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\ForwardsCalls;
use RuntimeException;
use Tests\Integration\FailingFailSafeRepositoryTest;
use Tests\Integration\FailSafeRepositoryTest;
use Throwable;

/**
 * Secures all cache operations to be fail-safe.
 *
 * Unfortunately, due to a bad design of Laravel, we have to extend Repository (instead of implementing it)
 * because there are typehints inside Laravel itself that require Repository impl (not contract).
 *
 * @see FailSafeRepositoryTest
 * @see FailingFailSafeRepositoryTest
 */
class FailSafeRepository extends Repository
{
	use ForwardsCalls;

	public function __construct(
		private readonly Repository $delegate,
		private readonly Closure $reportFail,
	) {
		parent::__construct($this->delegate->getStore());
	}

	public function has($key): bool
	{
		return $this->wrap(
			fn () => $this->delegate->has($key),
			'check if item exists in cache',
			false
		);
	}

	public function missing($key): bool
	{
		return !$this->has($key);
	}

	/**
	 * @param string|array<int|string, mixed> $key
	 */
	public function get($key, mixed $default = null): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->get($key, $default),
			'get items from cache',
			fn () => value($default)
		);
	}

	/**
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
	 * @param iterable<int, string> $keys
	 *
	 * @return iterable<string, mixed>
	 */
	public function getMultiple($keys, mixed $default = null): iterable
	{
		return $this->wrap(
			fn () => $this->delegate->getMultiple($keys, $default),
			'get items from cache',
			static fn () => array_fill_keys(collect($keys)->all(), value($default))
		);
	}

	/**
	 * @param string|array<string, mixed> $key
	 * @param mixed|null                  $ttl
	 */
	public function put($key, $value, $ttl = null): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->put($key, $value, $ttl),
			'put items into cache',
			false
		);
	}

	public function set($key, $value, $ttl = null): bool
	{
		return $this->put($key, $value, $ttl);
	}

	/**
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
	 * @param iterable<string, mixed> $values
	 * @param mixed|null              $ttl
	 */
	public function setMultiple($values, $ttl = null): bool
	{
		return $this->wrap(
			fn () => $this->delegate->setMultiple($values, $ttl),
			'put items into cache',
			false
		);
	}

	public function forever($key, $value): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->forever($key, $value),
			'put items into cache',
			false
		);
	}

	public function add($key, $value, $ttl = null): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->add($key, $value, $ttl),
			'add items into cache',
			false
		);
	}

	public function remember($key, $ttl, Closure $callback): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->remember($key, $ttl, $callback),
			'remember items into cache',
			fn () => $callback()
		);
	}

	public function sear($key, Closure $callback): mixed
	{
		return $this->rememberForever($key, $callback);
	}

	public function rememberForever($key, Closure $callback): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->rememberForever($key, $callback),
			'remember items into cache',
			fn () => $callback()
		);
	}

	public function pull($key, $default = null): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->pull($key, $default),
			'pull items from cache',
			fn () => value($default)
		);
	}

	public function increment($key, $value = 1): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->increment($key, $value),
			'increment key in cache',
			false
		);
	}

	public function decrement($key, $value = 1): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->decrement($key, $value),
			'decrement key in cache',
			false
		);
	}

	public function forget($key): mixed
	{
		return $this->wrap(
			fn () => $this->delegate->forget($key),
			'forget items from cache',
			false
		);
	}

	public function delete($key): bool
	{
		return $this->forget($key);
	}

	public function deleteMultiple($keys): bool
	{
		return $this->wrap(
			fn () => $this->delegate->deleteMultiple($keys),
			'forget items from cache',
			false
		);
	}

	public function clear(): bool
	{
		return $this->flush();
	}

	public function flush(): bool
	{
		return $this->wrap(
			fn () => $this->delegate->flush(),
			'flush cache',
			false
		);
	}

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
	 * @param string|array<string> $names
	 */
	public function tags(...$names): self
	{
		return new self(
			$this->delegate->tags(...$names),
			$this->reportFail
		);
	}

	/**
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

	public function offsetExists($key): bool
	{
		return $this->wrap(
			fn () => isset($this->delegate[$key]),
			'check if item exists in cache',
			false
		);
	}

	public function offsetGet($key): mixed
	{
		return $this->wrap(
			fn () => $this->delegate[$key],
			'get items from cache',
			null
		);
	}

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

	public function supportsTags()
	{
		return $this->delegate->supportsTags();
	}

	public function getDefaultCacheTime()
	{
		return $this->delegate->getDefaultCacheTime();
	}

	public function setDefaultCacheTime($seconds)
	{
		$this->delegate->setDefaultCacheTime($seconds);

		return $this;
	}

	public function getStore()
	{
		return $this->delegate->getStore();
	}

	public function getEventDispatcher()
	{
		return $this->delegate->getEventDispatcher();
	}

	public function setEventDispatcher(Dispatcher $events)
	{
		$this->delegate->setEventDispatcher($events);
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

	/**
	 * Forward any custom methods to the original repository. We can't put a try-catch here because we don't know the expected return type.
	 *
	 * @param array<int, mixed> $parameters
	 */
	public function __call($method, $parameters): mixed
	{
		return $this->forwardCallTo($this->delegate, $method, $parameters);
	}
}
