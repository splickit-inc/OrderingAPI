<?php

use phpFastCache\CacheManager;

class SplickitCache
{

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $memcached_server;

    /**
     * @var ExtendedCacheItemPoolInterface
     */
    private $instance_cache;

    /**
     * @var ExtendedCacheItemInterface
     */
    private $cached_string;

    /**
     * @var string
     */
    private $environment_file;

    function __construct()
    {
        $this->memcached_server = getProperty('memcached_server');
        $this->environment_file = getProperty('environment_file');
        $this->instance_cache = $this->getInstanceCache();
    }

    function processCachBustRequest($url)
    {
        if (preg_match("%/brands/([0-9]{3,15})%", $url, $matches)) {
            $cache_key = "brand-".$matches[1];
            myerror_log("we have a bust cache call on the portal for $cache_key");
            $this->deleteCache($cache_key);
        } else if (preg_match("%/skins/([0-9]{3,15})%", $url, $matches)) {
            $skin_adapter = new SkinAdapter(getM());
            $cache_key = $skin_adapter->deleteSkinCacheFromSkinId($matches[1]);
        } else {
            $this->instanceFlushAll();
            $cache_key = "Entire Cache";
        }
        return Resource::dummyfactory(['result' => 'success',"resource" => $cache_key]);
    }

    function getCache($key)
    {
        $this->key = $key;
        $this->cached_string = $this->instance_cache->getItem($key);
        if (isLoggedInUserStoreTesterLevelOrBetter()) {
            myerror_logging(5,"caching_log: user is store tester or better. Do Not Check Cache, create new cache for $key");
            return null;
        } else if (getProperty("DO_NOT_CHECK_CACHE") == 'true') {
            myerror_log("caching_log: flag is set, do not use cache");
            return null;
        } else if ($this->memcached_server == 'none') {
            myerror_log("caching_log: memcached server set to none. bypass caching");
            return null;
        }
        $value = $this->cached_string->get();
        if (strpos($key,'DESCRIBE') < 1) {
            if (substr($key,0,4) == 'menu') {
                $log_string = 'menu';
            } else if (is_array($value)) {
                $log_string = json_encode($value);
            } else if (is_a($value,'Resource')) {
                $log_string = json_encode($value->getDataFieldsReally());
            } else {
                $log_string = "$value";
            }
            myerror_log("caching_log IO: getting SPLICKIT CACHE for key: $key - $log_string",5);
            myerror_log("caching_log: getting cache from server: ".$this->memcached_server,5);
        }
        return $value;
    }

    function setCache($key,$value,$expires_in_seconds)
    {
        if ($_SERVER['DO_NOT_SAVE_TO_CACHE']) {
            myerror_log("bypassing set cache because server is set to do not save");
            return;
        } else if ($this->memcached_server == 'none') {
            myerror_log("caching_log: memcached server set to none. bypass caching");
            return;
        }
        $this->cached_string = $this->instance_cache->getItem($key);
        if (strpos($key,'DESCRIBE') < 1) {
            if (substr($key,0,4) == 'menu') {
                $log_string = 'menu';
                //$log_string = json_encode($value);
            } else if (is_array($value)) {
                $log_string = json_encode($value);
            } else if (is_a($value,'Resource')) {
                $log_string = json_encode($value->getDataFieldsReally());
            } else {
                $log_string = "$value";
            }


            myerror_log("caching_log IO: SAVING SPLICKIT CACHE for key: $key  -  $log_string",3);
            myerror_log("caching_log: saving to server: " . $this->memcached_server,3);

        }
        $this->cached_string->set($value)->expiresAfter($expires_in_seconds);
        $this->instance_cache->save($this->cached_string);
    }

    function deleteCache($key)
    {
        myerror_log("caching_log IO: DELETING SPLICKIT CACHE for key: $key ");
        $this->instance_cache->deleteItem($key);
    }

    function isLocalCache()
    {
        return $this->environment_file == "unit_test" || $this->environment_file == "unit_test_ide";
    }

    private function getInstanceCache()
    {
        if ($this->isLocalCache()) {
            // must get a new instance each time while unit tests run
            return new phpFastCache\Drivers\Memcached\Driver(['servers' => [
                [
                    'host' =>$this->memcached_server,
                    'port' => 11211,
                    // 'sasl_user' => false, // optional
                    // 'sasl_password' => false // optional
                ]
            ]]);
       }
       return CacheManager::getInstance('memcached',['servers' => [
            [
                'host' =>$this->memcached_server,
                'port' => 11211,
                // 'sasl_user' => false, // optional
                // 'sasl_password' => false // optional
            ]
        ]]);
    }

    public function setMemcachedServer($server)
    {
        $this->memcached_server = $server;
    }

    static function getCacheFromKey($key)
    {
        $sc = new SplickitCache();
        return $sc->getCache($key);
    }

    static function deleteCacheFromKey($key)
    {
        $sc = new SplickitCache();
        return $sc->deleteCache($key);
    }

    static function flushAll()
    {
        $splickit_cache = new SplickitCache();
        $splickit_cache->instanceFlushAll();
    }

    function instanceFlushAll()
    {
        $mem_server = getProperty('memcached_server');
        myerror_log("About to flush cache for $mem_server");
        $m = new Memcached();
        $m->addServer("$mem_server", 11211);
        return $m->flush();
    }


}

?>