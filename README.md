# Better cache for Laravel

## Better redis driver
Laravel's implementation of cache tags for Redis driver doesn't clean up after itself:
```php
Cache::tags(['one'])->put('key2', 'value', 2);
Cache::tags(['one', 'two'])->put('key1', 'value', 2);
```

After 2 seconds, this is what `KEYS *` would return you:
```
0)	laravel:tag:two:key
1)	laravel:tag:one:key
2)	laravel:6307238514508800660377:standard_ref
3)	laravel:63072380e1b1c629296883:standard_ref
```

These stay in Redis forever and take up valuable space. To solve the problem, this
package uses different data structures in Redis to allow for efficient clean up of tags.

The shortcome is that this package provides a new `better_redis` driver (which acts)
exactly the same as the normal `redis`, except stores the tags in a different format,
and a `cache:flush-stale` command which cleans up stale tags, making `KEYS *` now 
return 0 keys (yay!):
```
(empty array)
```

## Fail safe

Laravel's implementation of cache doesn't allow failures - if your Redis dies, your app dies too.
New `fail_safe` driver aims to solve this by catching and logging all exceptions and instead
returning null/false as if the value was simply not found in cache:

```php
// config/cache.php
[
	'fail_safe' => [
		'delegate' => [
			'driver' => 'redis',
			'connection' => 'cache',
			'lock_connection' => 'default',
		]
	]
]

// code
Cache::forever('key', 'value');
// redis died here
Cache::get('key'); // returns null and logs the exception
```
