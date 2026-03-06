<?php

namespace Ademakanaky\EnterpriseIdempotency\Tests\Unit;

use Ademakanaky\EnterpriseIdempotency\Drivers\HybridDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Ademakanaky\EnterpriseIdempotency\Tests\TestCase;

class UserResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_resolve_user_with_config_callable(): void
    {
        config()->set('idempotency.user_resolver', fn($request) => 'custom-user');

        $driver = new HybridDriver();

        $request = Request::create('/test', 'GET');

        $user = $this->invokeMethod($driver, 'resolveUser', [$request]);

        $this->assertEquals('custom-user', $user);
    }

    public function test_can_resolve_user_fallbacks_to_ip(): void
    {
        config()->set('idempotency.user_resolver', null);

        $driver = new HybridDriver();

        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $user = $this->invokeMethod($driver, 'resolveUser', [$request]);

        $this->assertEquals('127.0.0.1', $user);
    }

    /**
     * Helper to call protected/private methods
     * @throws \ReflectionException
     */
    private function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}