<?php

namespace Tests\Integration;

use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\Repository;
use TenantCloud\LaravelBetterCache\Redis\BetterRedisStore;
use Tests\TestCase;

/**
 * @see BetterRedisStore
 */
class BetterRedisStoreTest extends TestCase
{
	private Repository $store;

	protected function setUp(): void
	{
		parent::setUp();

		$this->store = cache()->store('redis');
		$this->store->flush();

		$this->store->put('forever-key', 'value1');
		$this->store->tags('one')->put('one-forever-key', 'value2');
		$this->store->tags('one')->put('one-expired-key', 'value3', CarbonInterval::seconds(3));
		$this->store->tags('one', 'two')->put('one-two-valid-key', 'value4', CarbonInterval::hour());
		$this->store->tags('three')->put('three-expired-key', 'value5', CarbonInterval::second(3));
	}

	public function testListsTags(): void
	{
		self::assertEqualsCanonicalizing([
			'one',
			'two',
			'three',
		], $this->store->tagList()->all());

		self::assertEqualsCanonicalizing([
			'one',
			'two',
			'three',
		], $this->store->tagList(1)->all());
	}

	public function testListsEntriesPerTag(): void
	{
		self::assertEqualsCanonicalizing([
			'6f30f7fbfb773cf3267427217f52dc5ea273d207:one-forever-key',
			'6f30f7fbfb773cf3267427217f52dc5ea273d207:one-expired-key',
			'3736b253c5b5319377688508d969a59f25399d30:one-two-valid-key',
		], $this->store->tags('one')->entries()->all());

		self::assertEqualsCanonicalizing([
			'3736b253c5b5319377688508d969a59f25399d30:one-two-valid-key',
		], $this->store->tags('two')->entries()->all());

		self::assertSame('value2', $this->store->get('6f30f7fbfb773cf3267427217f52dc5ea273d207:one-forever-key'));
	}

	public function testFlushesPerTag(): void
	{
		$this->store->tags('one')->flush();

		$this->assertEqualsCanonicalizing([
			'laravel_database_laravel_cache_:forever-key',
			'laravel_database_laravel_cache_:b38fc2bfdff6993380416397ad311eaa5aa8a356:three-expired-key',
			'laravel_database_laravel_cache_:tag:two:entries',
			'laravel_database_laravel_cache_:tag:three:entries',
		], $this->store->connection()->keys('*'));

		self::assertEqualsCanonicalizing([
			'two',
			'three',
		], $this->store->tagList()->all());

		self::assertEmpty($this->store->tags('one')->entries()->all());
		self::assertEqualsCanonicalizing([
			'3736b253c5b5319377688508d969a59f25399d30:one-two-valid-key',
		], $this->store->tags('two')->entries()->all());
		self::assertEqualsCanonicalizing([
			'b38fc2bfdff6993380416397ad311eaa5aa8a356:three-expired-key',
		], $this->store->tags('three')->entries()->all());

		self::assertSame('value1', $this->store->get('forever-key'));
		self::assertNull($this->store->tags(['one'])->get('one-forever-key'));
		self::assertNull($this->store->tags(['one'])->get('one-expired-key'));
		self::assertNull($this->store->tags(['one', 'two'])->get('one-two-valid-key'));
		self::assertSame('value5', $this->store->tags(['three'])->get('three-expired-key'));
	}

	public function testFlushesStaleTags(): void
	{
		sleep(4);

		$this->store->flushStale();

		$this->assertEqualsCanonicalizing([
			'laravel_database_laravel_cache_:forever-key',
			'laravel_database_laravel_cache_:6f30f7fbfb773cf3267427217f52dc5ea273d207:one-forever-key',
			'laravel_database_laravel_cache_:3736b253c5b5319377688508d969a59f25399d30:one-two-valid-key',
			'laravel_database_laravel_cache_:tag:one:entries',
			'laravel_database_laravel_cache_:tag:two:entries',
		], $this->store->connection()->keys('*'));

		self::assertEqualsCanonicalizing([
			'one',
			'two',
		], $this->store->tagList()->all());

		self::assertEqualsCanonicalizing([
			'6f30f7fbfb773cf3267427217f52dc5ea273d207:one-forever-key',
			'3736b253c5b5319377688508d969a59f25399d30:one-two-valid-key',
		], $this->store->tags('one')->entries()->all());
		self::assertEqualsCanonicalizing([
			'3736b253c5b5319377688508d969a59f25399d30:one-two-valid-key',
		], $this->store->tags('two')->entries()->all());

		self::assertSame('value1', $this->store->get('forever-key'));
		self::assertSame('value2', $this->store->tags(['one'])->get('one-forever-key'));
		self::assertNull($this->store->tags(['one'])->get('one-expired-key'));
		self::assertSame('value4', $this->store->tags(['one', 'two'])->get('one-two-valid-key'));
		self::assertNull($this->store->tags(['three'])->get('three-expired-key'));
	}
}
