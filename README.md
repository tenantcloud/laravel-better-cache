# Better cache for Laravel

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

The shortcome is that this package provides the `cache:flush-stale` command,
execution of which will make `KEYS *` now 0 keys (yay!):
```
(empty array)
```

Everything else should stay as it is. 
