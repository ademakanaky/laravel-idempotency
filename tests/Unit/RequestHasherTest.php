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
    public function test_hash_is_stable_for_nested_json_with_different_key_order(): void
    {
        $request1 = Request::create('/test', 'POST', [], [], [], [], '{"meta":{"b":2,"a":1},"items":[{"z":9,"a":1},{"b":2,"a":1}]}');
        $request2 = Request::create('/test', 'POST', [], [], [], [], '{"items":[{"a":1,"z":9},{"a":1,"b":2}],"meta":{"a":1,"b":2}}');

        $driver = new CacheDriver();

        $hash1 = $this->callProtectedHash($driver, $request1);
        $hash2 = $this->callProtectedHash($driver, $request2);

        $this->assertSame($hash1, $hash2);
    }

    /**
     * @throws \ReflectionException
     */
    public function test_hash_changes_when_nested_json_array_order_changes(): void
    {
        $request1 = Request::create('/test', 'POST', [], [], [], [], '{"items":[{"id":1},{"id":2}]}');
        $request2 = Request::create('/test', 'POST', [], [], [], [], '{"items":[{"id":2},{"id":1}]}');

        $driver = new CacheDriver();

        $hash1 = $this->callProtectedHash($driver, $request1);
        $hash2 = $this->callProtectedHash($driver, $request2);

        $this->assertNotSame($hash1, $hash2);
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
