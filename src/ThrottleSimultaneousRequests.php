<?php

namespace ImLiam\ThrottleSimultaneousRequests;

use Closure;
use RuntimeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class ThrottleSimultaneousRequests
{
    /**
     * Amount of time (in minutes) the last request will be stored,
     * in the cache, in the case the middleware never terminates.
     *
     * @var integer
     */
    protected $cacheForMinutes = 60;

    /**
     * The limit of concurrent requests the current user can run.
     *
     * @var integer
     */
    protected $limit;

    /**
     * The current user's signature.
     *
     * @var string
     */
    protected $signature;

    /**
     * Prefix to be on the request signature.
     *
     * @var string
     */
    protected $prefix = 'concurrent:';

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  integer  $limit
     * @return mixed
     */
    public function handle($request, Closure $next, $limit)
    {
        $this->limit = (int) $limit;
        $this->setRequestSignature($request);

        if ($this->limit <= Cache::get($this->signature)) {
            throw new ThrottleRequestsException('Too Many Attempts.', null, $this->getHeaders());
        }

        $this->increment();

        return $next($request);
    }

    /**
     * Handle the outgoing response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return mixed
     */
    public function terminate($request, $response)
    {
        $this->decrement();

        return $response;
    }

    /**
     * Get the number of remaining concurrent requests the user can run.
     */
    protected function getRemainingRequests(int $limit): int
    {
        return max(0, $limit - Cache::get($this->signature));
    }

    /**
     * Get headers to denote the current rate limits the user has.
     */
    protected function getHeaders(): array
    {
        return [
            'X-RateLimit-Limit' => $this->limit,
            'X-RateLimit-Remaining' => $this->getRemainingRequests($this->limit),
        ];
    }

    /**
     * Manually set the signature for the current request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param string|null $signature
     * @return string
     */
    public function setRequestSignature($request, $signature = null)
    {
        if (!empty($this->signature)) {
            return $signature;
        }

        $signature = $this->prefix . sha1($signature ?? $this->resolveRequestSignature($request));
        $this->signature = $signature;

        return $signature;
    }

    /**
     * Resolve the request signature for the current requesting user.
     *
     * @param \Illuminate\Http\Request $request
     * @return string
     * @throws \RuntimeException
     */
    protected function resolveRequestSignature($request)
    {
        if (!empty($this->signature)) {
            return $this->signature;
        }

        if ($user = $request->user()) {
            return $user->getAuthIdentifier();
        }

        if ($route = $request->route()) {
            return $route->getDomain().'|'.$request->ip();
        }

        throw new RuntimeException('Unable to generate the request signature. Route unavailable.');
    }

    /**
     * Increment the count of currently running requests for the current user by 1.
     *
     * @return integer
     */
    protected function increment(): int
    {
        $value = 1;

        if (Cache::has($this->signature)) {
            $value = Cache::get($this->signature) + 1;
        }

        Cache::put($this->signature, $value, $this->cacheForMinutes);

        return $value;
    }

    /**
     * Decrement the count of currently running requests for the current user by 1.
     */
    protected function decrement(): int
    {
        if (! Cache::has($this->signature)) {
            return 0;
        }

        $value = Cache::get($this->signature) - 1;

        if ($value === 0) {
            Cache::forget($this->signature);
            return 0;
        }

        Cache::put($this->signature, $value);

        return $value;
    }
}
