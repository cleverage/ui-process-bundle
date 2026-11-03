<?php

declare(strict_types=1);

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\Security;

use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

/**
 * Login throttling limiter of a firewall (see LoginThrottlingPass) which does not count the requests of the HTTP API:
 * they are authenticated by a token, not by a password. The other requests (the login form) are counted by the
 * limiter of the firewall, with its settings.
 *
 * @internal
 */
class HttpProcessExecutionRequestRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        protected readonly RequestRateLimiterInterface $limiter,
        protected readonly AuthenticatorInterface $authenticator,
    ) {
    }

    /**
     * The decorated limiter is peekable or not, as the firewall limiter: the LoginThrottlingListener of Symfony counts
     * the failed attempts only with a peekable limiter, every attempt otherwise.
     */
    public static function create(RequestRateLimiterInterface $limiter, AuthenticatorInterface $authenticator): RequestRateLimiterInterface
    {
        if ($limiter instanceof PeekableRequestRateLimiterInterface) {
            return new PeekableHttpProcessExecutionRequestRateLimiter($limiter, $authenticator);
        }

        return new self($limiter, $authenticator);
    }

    public function consume(Request $request): RateLimit
    {
        if ($this->isHttpProcessExecution($request)) {
            return $this->createAcceptedRateLimit();
        }

        return $this->limiter->consume($request);
    }

    public function reset(Request $request): void
    {
        if ($this->isHttpProcessExecution($request)) {
            return;
        }

        $this->limiter->reset($request);
    }

    protected function isHttpProcessExecution(Request $request): bool
    {
        return false !== $this->authenticator->supports($request);
    }

    /**
     * An untouched limit: the listener neither rejects the request nor resets the limiter after a success.
     */
    protected function createAcceptedRateLimit(): RateLimit
    {
        return new RateLimit(1, new \DateTimeImmutable(), true, 1);
    }
}
