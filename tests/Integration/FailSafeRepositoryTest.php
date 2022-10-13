<?php

namespace Tests\Integration;

use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use TenantCloud\LaravelBetterCache\FailSafe\FailSafeRepository;
use Tests\TestCase;
use TiMacDonald\Log\LogEntry;
use TiMacDonald\Log\LogFake;

/**
 * @see FailSafeRepository
 */
class FailSafeRepositoryTest extends TestCase
{
	private Repository $store;

	protected function setUp(): void
	{
		parent::setUp();

		$this->store = cache()->store('redis_fail_safe');
	}

	public function testHas(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->has('test'));
		self::assertTrue($this->store->missing('test'));

		$log->assertLoggedTimes(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to check if item exists in cache.',
			2
		);
	}

	public function testGet(): void
	{
		Log::swap($log = new LogFake());

		self::assertNull($this->store->get('test'));
		self::assertSame(123, $this->store->get('test', 123));
		self::assertSame(456, $this->store->get('test', fn () => 456));

		$log->assertLoggedTimes(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to get items from cache.',
			3
		);
	}

	public function testMany(): void
	{
		Log::swap($log = new LogFake());

		self::assertSame([
			'test1' => 123,
			'test2' => 456,
			'test3' => null,
			'test4' => null,
			'test5' => null,
			'test6' => null,
		], $this->store->many([
			'test1' => 123,
			'test2' => fn () => 456,
			'test3',
			'test4',
			'test5',
			'test6',
		]));
		self::assertSame([
			'test7' => 123,
		], $this->store->getMultiple([
			'test7',
		], 123));

		$log->assertLoggedTimes(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to get items from cache.',
			2
		);
	}

	public function testPut(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->put('test', 'asd', CarbonInterval::minute()));
		self::assertFalse($this->store->set('test', 'asd', CarbonInterval::minute()));

		$log->assertLoggedTimes(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to put items into cache.',
			2
		);
	}

	public function testPutMany(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->putMany([
			'test1' => null,
			'test2' => null,
			'test3' => null,
			'test4' => null,
			'test5' => null,
			'test6' => null,
		], CarbonInterval::minute()));
		self::assertFalse($this->store->setMultiple([
			'test7' => null,
		], CarbonInterval::minute()));

		$log->assertLoggedTimes(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to put items into cache.',
			2
		);
	}

	public function testForever(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->forever('test', 'asd'));

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to put items into cache.'
		);
	}

	public function testAdd(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->add('test', 'asd', CarbonInterval::minute()));

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to add items into cache.'
		);
	}

	public function testRemember(): void
	{
		Log::swap($log = new LogFake());

		self::assertSame(123, $this->store->remember('test', CarbonInterval::minute(), fn () => 123));

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to remember items into cache.'
		);
	}

	public function testRememberForever(): void
	{
		Log::swap($log = new LogFake());

		self::assertSame(123, $this->store->rememberForever('test', fn () => 123));

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to remember items into cache.'
		);
	}

	public function testPull(): void
	{
		Log::swap($log = new LogFake());

		self::assertNull($this->store->pull('test'));
		self::assertSame(123, $this->store->pull('test', 123));
		self::assertSame(456, $this->store->pull('test', fn () => 456));

		$log->assertLoggedTimes(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to pull items from cache.',
			3
		);
	}

	public function testIncrement(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->increment('test'));

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to increment key in cache.'
		);
	}

	public function testDecrement(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->decrement('test'));

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to decrement key in cache.'
		);
	}

	public function testForget(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->forget('test'));
		self::assertFalse($this->store->delete('test'));

		$log->assertLoggedTimes(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to forget items from cache.',
			2
		);
	}

	public function testForgetMany(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->deleteMultiple([
			'test1',
			'test2',
			'test3',
			'test4',
			'test5',
			'test6',
		]));

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to forget items from cache.'
		);
	}

	public function testFlush(): void
	{
		Log::swap($log = new LogFake());

		self::assertFalse($this->store->flush());
		self::assertFalse($this->store->clear());

		$log->assertLoggedTimes(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to flush cache.',
			2
		);
	}

	public function testFlushStale(): void
	{
		Log::swap($log = new LogFake());

		$this->store->flushStale();

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to flush stale cache.'
		);
	}

	public function testTagList(): void
	{
		Log::swap($log = new LogFake());

		self::assertEmpty($this->store->tagList());

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to get tag list from cache.'
		);
	}

	public function testTags(): void
	{
		Log::swap($log = new LogFake());

		self::assertNull($this->store->tags('one')->get('test'));
		self::assertFalse($this->store->tags('one')->put('test', '123'));
		self::assertEmpty($this->store->tags('one')->entries());

		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to get items from cache.'
		);
		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to put items into cache.'
		);
		$log->assertLogged(
			fn (LogEntry $log) => $log->level === 'error' &&
				$log->message === 'Failed to get entries list from cache.'
		);
	}
}
