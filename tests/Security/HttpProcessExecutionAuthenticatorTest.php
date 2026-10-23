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
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

#[CoversClass(HttpProcessExecutionAuthenticator::class)]
class HttpProcessExecutionAuthenticatorTest extends TestCase
{
    public function testSupportsHttpProcessExecuteRoute(): void
    {
        $request = Request::create('/http/process/execute', Request::METHOD_POST);
        $request->attributes->set('_route', 'cleverage_ui_process_http_execute');

        self::assertTrue($this->createAuthenticator()->supports($request));
    }

    public function testDoesNotSupportGetRequest(): void
    {
        $request = Request::create('/http/process/execute', Request::METHOD_GET);
        $request->attributes->set('_route', 'cleverage_ui_process_http_execute');

        self::assertFalse($this->createAuthenticator()->supports($request));
    }

    public function testDoesNotSupportOtherRoutes(): void
    {
        $request = Request::create('/process', Request::METHOD_POST, ['_route' => 'cleverage_ui_process_http_execute']);
        $request->attributes->set('_route', 'cleverage_ui_process_list');

        self::assertFalse($this->createAuthenticator()->supports($request));
    }

    /**
     * @return iterable<string, array{AuthenticationException, string}>
     */
    public static function provideAuthenticationFailures(): iterable
    {
        yield 'invalid token' => [new CustomUserMessageAuthenticationException('Invalid token.'), 'Invalid token.'];
        // No message, only a message key with data
        yield 'too many attempts' => [new TooManyLoginAttemptsAuthenticationException(2), 'Too many failed login attempts, please try again in 2 minutes.'];
        // The message of an exception is not displayed, it may disclose internal details
        yield 'other exception' => [new AuthenticationException('Internal details.'), 'An authentication exception occurred.'];
    }

    #[DataProvider('provideAuthenticationFailures')]
    public function testOnAuthenticationFailure(AuthenticationException $exception, string $message): void
    {
        $response = $this->createAuthenticator()->onAuthenticationFailure(new Request(), $exception);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['message' => $message], json_decode((string) $response->getContent(), true));
    }

    private function createAuthenticator(): HttpProcessExecutionAuthenticator
    {
        return new HttpProcessExecutionAuthenticator($this->createStub(EntityManagerInterface::class));
    }
}
