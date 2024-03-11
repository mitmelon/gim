<?php
namespace Manomite\Engine;
use \Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use \Symfony\Component\RateLimiter\RateLimiterFactory;

class Ratelimit {

	public function limit($type = 'login', $policy = 'token_bucket', $limit = 5, $interval = 30){

		$factory = new RateLimiterFactory([
			'id' => $type,
			'policy' => $policy,
			'limit' => $limit,
			'rate' => ['interval' => $interval.' minutes'],
		], new InMemoryStorage());
		$limiter = $factory->create();
		// blocks until 1 token is free to use for this process
		//$limiter->reserve(1)->wait();
		if ($limiter->consume(1)->isAccepted()) {
			return true;
		}
		return false;
	}

}
