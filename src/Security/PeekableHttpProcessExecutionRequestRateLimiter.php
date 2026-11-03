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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * HttpProcessExecutionRequestRateLimiter of a peekable firewall limiter (the default one).
 *
 * @internal
 */
final class PeekableHttpProcessExecutionRequestRateLimiter extends HttpProcessExecutionRequestRateLimiter implements PeekableRequestRateLimiterInterface
{
    public function peek(Request $request): RateLimit
    {
        if ($this->isHttpProcessExecution($request)) {
            return $this->createAcceptedRateLimit();
        }

        \assert($this->limiter instanceof PeekableRequestRateLimiterInterface);

        return $this->limiter->peek($request);
    }
}
