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

namespace CleverAge\UiProcessBundle\Tests\Security;

use CleverAge\UiProcessBundle\Security\HttpProcessExecutionAuthenticator;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionRequestRateLimiter;
use CleverAge\UiProcessBundle\Security\PeekableHttpProcessExecutionRequestRateLimiter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;

#[CoversClass(HttpProcessExecutionRequestRateLimiter::class)]
#[CoversClass(PeekableHttpProcessExecutionRequestRateLimiter::class)]
#[UsesClass(HttpProcessExecutionAuthenticator::class)]
class HttpProcessExecutionRequestRateLimiterTest extends TestCase
{
    public function testCreateKeepsThePeekableInterface(): void
    {
        $authenticator = $this->createAuthenticator();

        $limiter = HttpProcessExecutionRequestRateLimiter::create($this->createStub(PeekableRequestRateLimiterInterface::class), $authenticator);
        self::assertInstanceOf(PeekableHttpProcessExecutionRequestRateLimiter::class, $limiter);

        // The LoginThrottlingListener of Symfony counts every attempt with a limiter which is not peekable
        $limiter = HttpProcessExecutionRequestRateLimiter::create($this->createStub(RequestRateLimiterInterface::class), $authenticator);
        self::assertNotInstanceOf(PeekableRequestRateLimiterInterface::class, $limiter);
    }

    public function testHttpProcessExecutionIsNotCounted(): void
    {
        $inner = $this->createMock(PeekableRequestRateLimiterInterface::class);
        $inner->expects(self::never())->method('consume');
        $inner->expects(self::never())->method('peek');
        $inner->expects(self::never())->method('reset');
        $limiter = HttpProcessExecutionRequestRateLimiter::create($inner, $this->createAuthenticator());
        self::assertInstanceOf(PeekableRequestRateLimiterInterface::class, $limiter);
        $request = $this->createHttpProcessExecutionRequest();

        foreach ([$limiter->peek($request), $limiter->consume($request)] as $limit) {
            // Accepted, untouched: the listener does not reset the limiter after a success
            self::assertTrue($limit->isAccepted());
            self::assertGreaterThan(0, $limit->getRemainingTokens());
            self::assertSame($limit->getLimit(), $limit->getRemainingTokens());
        }
        $limiter->reset($request);
    }

    public function testHttpProcessExecutionIsNotCountedByANonPeekableLimiter(): void
    {
        $inner = $this->createMock(RequestRateLimiterInterface::class);
        $inner->expects(self::never())->method('consume');
        $inner->expects(self::never())->method('reset');
        $limiter = HttpProcessExecutionRequestRateLimiter::create($inner, $this->createAuthenticator());
        $request = $this->createHttpProcessExecutionRequest();

        self::assertTrue($limiter->consume($request)->isAccepted());
        $limiter->reset($request);
    }

    public function testOtherRequestsAreCounted(): void
    {
        $request = Request::create('/process/login', Request::METHOD_POST);
        $request->attributes->set('_route', 'cleverage_ui_process_login');
        $rejected = new RateLimit(0, new \DateTimeImmutable('+1 minute'), false, 5);
        $inner = $this->createMock(PeekableRequestRateLimiterInterface::class);
        $inner->expects(self::once())->method('peek')->with($request)->willReturn($rejected);
        $inner->expects(self::once())->method('consume')->with($request)->willReturn($rejected);
        $inner->expects(self::once())->method('reset')->with($request);
        $limiter = HttpProcessExecutionRequestRateLimiter::create($inner, $this->createAuthenticator());
        self::assertInstanceOf(PeekableRequestRateLimiterInterface::class, $limiter);

        self::assertSame($rejected, $limiter->peek($request));
        self::assertSame($rejected, $limiter->consume($request));
        $limiter->reset($request);
    }

    public function testOtherRequestsAreCountedByANonPeekableLimiter(): void
    {
        $request = Request::create('/process/login', Request::METHOD_POST);
        $rejected = new RateLimit(0, new \DateTimeImmutable('+1 minute'), false, 5);
        $inner = $this->createMock(RequestRateLimiterInterface::class);
        $inner->expects(self::once())->method('consume')->with($request)->willReturn($rejected);
        $inner->expects(self::once())->method('reset')->with($request);
        $limiter = HttpProcessExecutionRequestRateLimiter::create($inner, $this->createAuthenticator());

        self::assertSame($rejected, $limiter->consume($request));
        $limiter->reset($request);
    }

    private function createAuthenticator(): HttpProcessExecutionAuthenticator
    {
        return new HttpProcessExecutionAuthenticator($this->createStub(EntityManagerInterface::class));
    }

    private function createHttpProcessExecutionRequest(): Request
    {
        $request = Request::create('/http/process/execute', Request::METHOD_POST);
        $request->attributes->set('_route', 'cleverage_ui_process_http_execute');

        return $request;
    }
}
