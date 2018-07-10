<?php

namespace ImLiam\ThrottleSimultaneousRequests\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use ImLiam\ThrottleSimultaneousRequests\Tests\TestCase;
use ImLiam\ThrottleSimultaneousRequests\ThrottleSimultaneousRequests;

class ThrottleSimultaneousRequestsTest extends TestCase
{
    /**
     * Call a middleware class with a given callback.
     *
     * @param \Closure $callback
     * @param integer $limit
     * @param string $requestSignature
     * @return Illuminate\Http\Request|null
     */
    protected function callMiddleware(\Closure $callback, int $limit, $requestSignature = 'testabc'): ?Request
    {
        $request = new Request;
        $middleware = new ThrottleSimultaneousRequests($request);
        $signature = $middleware->setRequestSignature($request, $requestSignature);

        $response = $middleware->handle($request, function() use($callback, $signature) {
            return $callback(func_get_args(), $signature);
        }, $limit);

        return $middleware->terminate($request, $response);
    }

    /**
     * Set the current request amount for the given signature.
     *
     * @param mixed $signature
     * @param integer $currentRequestAmount
     * @return string
     */
    protected function setCurrentRequestAmount($signature, int $currentRequestAmount): string
    {
        $signature = 'concurrent:' . sha1($signature);
        Cache::put($signature, $currentRequestAmount, 60);

        return $signature;
    }

    /** @test */
    public function it_can_run_consecutive_requests()
    {
        $this->callMiddleware(function($response, $signature) {
            $this->assertEquals(1, Cache::get($signature));
        }, 2);

        $this->callMiddleware(function($response, $signature) {
            $this->assertEquals(1, Cache::get($signature));
        }, 2);

        $this->callMiddleware(function($response, $signature) {
            $this->assertEquals(1, Cache::get($signature));
        }, 2);
    }

    /** @test */
    public function it_can_not_run_more_concurrent_requests_than_allowed()
    {
        $signature = 'testabc';
        $this->setCurrentRequestAmount($signature, 5);

        $this->expectException(\Illuminate\Http\Exceptions\ThrottleRequestsException::class);

        $this->callMiddleware(function($response, $signature) {
            $this->assertEquals(1, Cache::get($signature));
        }, 2, $signature);
    }

    /** @test */
    public function it_wont_make_a_signature_without_a_real_request()
    {
        $this->expectException(\RuntimeException::class);

        $this->callMiddleware(function($response, $signature) {
            $this->fail('Next middleware was called when it should not have been.');
        }, 2, null);
    }

    /** @test */
    public function it_will_allow_different_users_to_run_requests_simultaneously()
    {
        $signatureOne = 'signatureOne';
        $signatureTwo = 'signatureTwo';
        $this->setCurrentRequestAmount($signatureTwo, 5);

        $this->callMiddleware(function($response, $signature) {
            $this->assertEquals(1, Cache::get($signature));
        }, 3, $signatureOne);

        $this->expectException(\Illuminate\Http\Exceptions\ThrottleRequestsException::class);

        $this->callMiddleware(function($response, $signature) {
            $this->fail('Next middleware was called when it should not have been.');
        }, 3, $signatureTwo);
    }

    /** @test */
    public function it_can_have_the_limit_changed()
    {
        $limit = 5;
        $signature = 'testabc';

        $this->setCurrentRequestAmount($signature, 4);

        $this->callMiddleware(function($response, $signature) {
            $this->assertEquals(5, Cache::get($signature));
        }, $limit, $signature);

        $this->expectException(\Illuminate\Http\Exceptions\ThrottleRequestsException::class);
        $this->setCurrentRequestAmount($signature, 6);

        $this->callMiddleware(function($response, $signature) {
            $this->fail('Next middleware was called when it should not have been.');
        }, $limit, $signature);
    }
}
