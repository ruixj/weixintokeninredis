<?php
class RedisLock
{
    private $redisconn;
    private $randval;
    private $lockName;
    public function __construct($redisconn,$lockName)
    {
        $this->redisconn = $redisconn; 
        $this->randval   = rand(1,100000);
        $this->lockName  = $lockName; 
    }    
    
    public function acquireLock($ttl=10)
    {
        $ok = $this->redisconn->set($this->lockName,$this->randval,array('nx','ex'=>$ttl)); 
        return $ok; 
    }

    public function unlock()
    {
        $script=' if redis.call("get",KEYS[1]) == ARGV[1]
                  then 
                      return redis.call("del",KEYS[1])
                  else
                      return 0
                  end
                ';
        return $this->redisconn->eval($script,[$this->lockName],[$this->randval]);
            
    }
        
    
}

