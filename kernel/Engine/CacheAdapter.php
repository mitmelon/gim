<?php

namespace Manomite\Engine;

/**
 *  Cache class for fast page laoding and optimizations.
 *
 *  @author Manomite
 */
require_once __DIR__ . "/../../autoload.php";
class CacheAdapter
{
    public $adapter;

    public function __construct()
    {
        $path = SYSTEM_DIR . '/cache/';
        if (!is_dir($path)) {
            mkdir($path, 0600, true);
        }
        $this->adapter = new \Shieldon\SimpleCache\Cache('file', [
            'storage' => $path
        ]);
    }

    public function getCache(string $key)
    {
        if ($this->adapter->has($key)) {
            return $this->adapter->get($key);
        } else {
            return null;
        }
    }
    public function cache(string $content, string $key, int $ttl = null)
    {
        $ttl = $ttl ?: (int) CACHE_EXPIRE;
        $this->adapter->set($key, $content, $ttl);
        return $this->adapter->get($key);
    }
    public function clear()
    {
        return $this->adapter->clear();
    }
    public function delete(string $key)
    {
        return $this->adapter->delete($key);
    }
    public function getKey()
    {
        return $this->adapter->getKey();
    }
}
