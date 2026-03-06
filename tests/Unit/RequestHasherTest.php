<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Unit;

use Ademakanaky\EnterpriseIdempotency\Drivers\CacheDriver;
use Illuminate\Http\Request;
use Ademakanaky\EnterpriseIdempotency\Tests\TestCase;

class RequestHasherTest extends TestCase
{
    /**
     * @throws \ReflectionException
     */
    public function test_hash_changes_on_body_change()
    {
        $request1 = Request::create('/test', 'POST', [], [], [], [], '{"foo":"bar"}');
        $request2 = Request::create('/test', 'POST', [], [], [], [], '{"foo":"baz"}');

        $driver = new CacheDriver();

        $hash1 = $this->callProtectedHash($driver, $request1);
        $hash2 = $this->callProtectedHash($driver, $request2);

        $this->assertNotEquals($hash1, $hash2);
    }

    /**
     * @throws \ReflectionException
     */
    protected function callProtectedHash($object, $request)
    {
        $method = new \ReflectionMethod($object, 'hash');
        $method->setAccessible(true);
        return $method->invoke($object, $request);
    }
}
