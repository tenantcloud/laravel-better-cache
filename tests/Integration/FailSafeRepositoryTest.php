<?php

namespace Tests\Integration;

use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use TenantCloud\LaravelBetterCache\FailSafe\FailSafeRepository;
use Tests\TestCase;
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

		Log::swap(new LogFake());

		$this->store = cache()->store('redis_fail_safe');
		$this->store->clear();
	}

	protected function tearDown(): void
	{
		Log::assertNothingLogged();

		parent::tearDown();
	}

	public function testHas(): void
	{
		$this->store->put('test1', 'asd');

		self::assertTrue($this->store->has('test1'));
		self::assertFalse($this->store->missing('test1'));

		self::assertFalse($this->store->has('test2'));
		self::assertTrue($this->store->missing('test2'));
	}

	public function testGet(): void
	{
		$this->store->put('test1', 'asd');

		self::assertSame('asd', $this->store->get('test1'));

		self::assertNull($this->store->get('test2'));
		self::assertSame('asd', $this->store->get('test2', 'asd'));
		self::assertSame('qwe', $this->store->get('test2', fn () => 'qwe'));
	}

	public function testMany(): void
	{
		$this->store->put('test1', 'asd');

		self::assertSame([
			'test1' => 'asd',
			'test'  => null,
		], $this->store->many([
			'test1',
			'test',
		]));

		self::assertSame([
			'test1' => 'asd',
			'test2' => 'qwe',
			'test3' => null,
			'test4' => null,
			'test5' => null,
			'test6' => null,
		], $this->store->many([
			'test1' => 'asd',
			'test2' => fn () => 'qwe',
			'test3',
			'test4',
			'test5',
			'test6',
		]));
		self::assertSame([
			'test7' => 'asd',
		], $this->store->getMultiple([
			'test7',
		], 'asd'));
	}

	public function testPut(): void
	{
		self::assertTrue($this->store->put('test1', 'asd1', CarbonInterval::minute()));
		self::assertTrue($this->store->set('test2', 'asd2', CarbonInterval::minute()));
		self::assertTrue($this->store->put('test3', 'expired', CarbonInterval::second()));

		sleep(2);

		self::assertSame('asd1', $this->store->get('test1'));
		self::assertSame('asd2', $this->store->get('test2'));
		self::assertNull($this->store->get('test3'));
	}

	public function testPutMany(): void
	{
		self::assertTrue($this->store->putMany([
			'test1' => 'asd1',
			'test2' => 'asd2',
			'test3' => null,
		], CarbonInterval::minute()));
		self::assertTrue($this->store->setMultiple([
			'test2' => 'expired',
		], CarbonInterval::second()));

		sleep(2);

		self::assertSame([
			'test1' => 'asd1',
			'test2' => null,
			'test3' => null,
		], $this->store->many([
			'test1',
			'test2',
			'test3',
		]));
	}

	public function testForever(): void
	{
		self::assertTrue($this->store->forever('test', 'asd'));

		self::assertSame('asd', $this->store->get('test'));
	}

	public function testAdd(): void
	{
		self::assertTrue($this->store->add('test', 'asd', CarbonInterval::minute()));
		self::assertFalse($this->store->add('test', 'other value', CarbonInterval::minute()));

		self::assertSame('asd', $this->store->get('test'));
	}

	public function testRemember(): void
	{
		self::assertSame('asd', $this->store->remember('test1', CarbonInterval::minute(), fn () => 'asd'));
		self::assertSame('qwe', $this->store->remember('test2', CarbonInterval::second(), fn () => 'qwe'));

		sleep(2);

		self::assertSame('asd', $this->store->get('test1'));
		self::assertNull($this->store->get('test2'));
	}

	public function testRememberThatThrows(): void
	{
		self::assertThrows(function () {
			$this->store->remember('test1', CarbonInterval::minute(), fn () => throw new RuntimeException('Test'));
		}, RuntimeException::class, 'Test');
	}

	public function testRememberForever(): void
	{
		self::assertSame('asd', $this->store->rememberForever('test', fn () => 'asd'));

		self::assertSame('asd', $this->store->get('test'));
	}

	public function testRememberForeverThatThrows(): void
	{
		self::assertThrows(function () {
			$this->store->rememberForever('test1', fn () => throw new RuntimeException('Test'));
		}, RuntimeException::class, 'Test');
	}

	public function testPull(): void
	{
		$this->store->set('test1', 'asd');

		self::assertSame('asd', $this->store->pull('test1'));
		self::assertNull($this->store->pull('test1'));
		self::assertNull($this->store->pull('test2'));

		self::assertSame('asd', $this->store->pull('test3', 'asd'));
		self::assertSame('qwe', $this->store->pull('test3', fn () => 'qwe'));
	}

	public function testIncrement(): void
	{
		$this->store->put('test1', 3);

		self::assertSame(4, $this->store->increment('test1'));
		self::assertSame(1, $this->store->increment('test2'));
	}

	public function testDecrement(): void
	{
		$this->store->put('test1', 3);

		self::assertSame(2, $this->store->decrement('test1'));
		self::assertSame(-1, $this->store->decrement('test2'));
	}

	public function testForget(): void
	{
		$this->store->put('test1', 'asd');
		$this->store->put('test2', 'asd');

		self::assertTrue($this->store->forget('test1'));
		self::assertTrue($this->store->delete('test2'));

		self::assertNull($this->store->get('test1'));
		self::assertNull($this->store->get('test2'));
	}

	public function testForgetMany(): void
	{
		$this->store->put('test1', 'asd');
		$this->store->put('test2', 'asd');
		$this->store->put('test3', 'asd');

		self::assertTrue($this->store->deleteMultiple([
			'test1',
			'test2',
		]));

		self::assertFalse($this->store->deleteMultiple([
			'test3',
			'test4',
		]));

		self::assertSame([
			'test1' => null,
			'test2' => null,
			'test3' => null,
			'test4' => null,
		], $this->store->many([
			'test1',
			'test2',
			'test3',
			'test4',
		]));
	}

	public function testFlush(): void
	{
		$this->store->put('test', 'asd');

		self::assertTrue($this->store->flush());

		self::assertFalse($this->store->has('test'));
		$this->store->put('test', 'asd');

		self::assertTrue($this->store->clear());

		self::assertFalse($this->store->has('test'));
	}

	public function testFlushStaleTags(): void
	{
		$this->store->flushStaleTags();
	}

	public function testTags(): void
	{
		$this->store->put('test', 'asd');

		self::assertNull($this->store->tags('one')->get('test'));

		$this->store->tags('one')->put('test', 'asd1');
		$this->store->tags('two')->put('test', 'asd2');

		self::assertSame('asd', $this->store->get('test'));
		self::assertSame('asd1', $this->store->tags('one')->get('test'));
		self::assertSame('asd2', $this->store->tags('two')->get('test'));

		$this->store->tags('one')->flush();

		self::assertSame('asd', $this->store->get('test'));
		self::assertNull($this->store->tags('one')->get('test'));
		self::assertSame('asd2', $this->store->tags('two')->get('test'));
	}
}
