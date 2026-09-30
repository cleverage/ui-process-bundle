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
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(HttpProcessExecutionAuthenticator::class)]
class HttpProcessExecutionAuthenticatorTest extends TestCase
{
    public function testSupportsHttpProcessExecuteRoute(): void
    {
        $request = Request::create('/http/process/execute', Request::METHOD_POST);
        $request->attributes->set('_route', 'http_process_execute');

        self::assertTrue($this->createAuthenticator()->supports($request));
    }

    public function testDoesNotSupportGetRequest(): void
    {
        $request = Request::create('/http/process/execute', Request::METHOD_GET);
        $request->attributes->set('_route', 'http_process_execute');

        self::assertFalse($this->createAuthenticator()->supports($request));
    }

    public function testDoesNotSupportOtherRoutes(): void
    {
        $request = Request::create('/process', Request::METHOD_POST, ['_route' => 'http_process_execute']);
        $request->attributes->set('_route', 'process_list');

        self::assertFalse($this->createAuthenticator()->supports($request));
    }

    private function createAuthenticator(): HttpProcessExecutionAuthenticator
    {
        return new HttpProcessExecutionAuthenticator($this->createStub(EntityManagerInterface::class));
    }
}
