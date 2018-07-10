# Laravel Throttle Simultaneous Requests Middleware

[![Latest Version on Packagist](https://img.shields.io/packagist/v/imliam/laravel-throttle-simultaneous-requests.svg)](https://packagist.org/packages/imliam/laravel-throttle-simultaneous-requests)
[![Total Downloads](https://img.shields.io/packagist/dt/imliam/laravel-throttle-simultaneous-requests.svg)](https://packagist.org/packages/imliam/laravel-throttle-simultaneous-requests)
[![License](https://img.shields.io/github/license/imliam/laravel-throttle-simultaneous-requests.svg)](LICENSE.md)

Throttle the current user's requests based on how many requests are currently being executed, in case any are time consuming before giving a response.

This helps when some endpoints are more resource-intensive than others, and stops users from retrying requests that may not have even completed yet.

This forces users of your API to interact in a different way by queuing their requests appropriately instead of spamming until they reach the request limit.

When performing an action only the current user can perform, this also helps to ensure that the endpoint has a form of *idempotency* and any side effects can only occur once until a subsequent request is made.

<!-- TOC -->

- [Laravel Throttle Simultaneous Requests Middleware](#laravel-throttle-simultaneous-requests-middleware)
    - [Installation](#installation)
    - [Usage](#usage)
        - [Why not use queues?](#why-not-use-queues)
        - [Why is no `Retry-After` header sent?](#why-is-no-retry-after-header-sent)
    - [Testing](#testing)
    - [Changelog](#changelog)
    - [Contributing](#contributing)
        - [Security](#security)
    - [Credits](#credits)
    - [License](#license)

<!-- /TOC -->

## Installation

You can install the package with [Composer](https://getcomposer.org/) using the following command:

```bash
composer require imliam/laravel-throttle-simultaneous-requests:^1.0.0
```

Once installed to your project, add the middleware to your `App\Http\Kernel::$routeMiddleware` array.

```php
protected $routeMiddleware = [
    // ...
    'simultaneous' => \ImLiam\ThrottleSimultaneousRequests\ThrottleSimultaneousRequests::class,
];
```

## Usage

You can use the middleware like any other. For example, to limit a particular endpoint to only 3 concurrent requests by the same user:

``` php
Route::get('/')->middleware('simultaneous:3');
```

### Why not use queues?

Queues have their place to defer time consuming tasks to a later date, however they are not always the most appropriate solution for a task. A given task could require use of limited hardware resources, or require some other kind of processing that does not make sense to run concurrently.

[See how Stripe use concurrent request limiters...](https://stripe.com/blog/rate-limiters)

### Why is no `Retry-After` header sent?

Most typical rate limiting solutions limit a user to a number of requests within a set time period, such as 100 requests per minute, so include a `Retry-After` header to let the requestor know when they are available to try again.

This middleware does not add such a header to the response, due to the nature of the request taking a longer amount of time to complete there is no guaranteed time where the requestor can retry the request. Instead, it is up to the requestor to determine when to retry.

## Testing

``` bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email liam@liamhammett.com instead of using the issue tracker.

## Credits

- [Liam Hammett](https://github.com/imliam)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
