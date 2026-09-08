<?php

namespace App\Repositories;

use App\Contracts\Repositories\SeoMonitoringRepository;
use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockProvider;
use LogicException;

class CacheSeoMonitoringRepository implements SeoMonitoringRepository
{
    public function __construct(private readonly CacheManager $cache) {}

    public function get(string $key): ?array
    {
        $value = $this->cache->store(config('seo-monitoring.cache_store'))->get($this->key($key));

        return is_array($value) ? $value : null;
    }

    public function put(string $key, array $value): void
    {
        $this->cache->store(config('seo-monitoring.cache_store'))->put($this->key($key), $value, now()->addDays(45));
    }

    public function synchronized(string $key, Closure $callback): mixed
    {
        $cache = $this->cache->store(config('seo-monitoring.cache_store'));
        if (! $cache instanceof Repository || ! $cache->getStore() instanceof LockProvider) {
            throw new LogicException('SEO monitoring requires a cache store with atomic locks.');
        }

        return $cache->getStore()->lock($this->key($key).':lock', 600)->get($callback);
    }

    private function key(string $key): string
    {
        return 'seo-monitoring:'.config('seo-monitoring.merchant.account_id').':'.config('seo-monitoring.merchant.source_id').':'.$key;
    }
}
