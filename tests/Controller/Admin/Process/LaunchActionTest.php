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

namespace CleverAge\UiProcessBundle\Tests\Controller\Admin\Process;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Controller\Admin\Process\LaunchAction;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(LaunchAction::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecuteMessage::class)]
#[UsesClass(User::class)]
class LaunchActionTest extends TestCase
{
    /**
     * The "execution_user" context value is always the email of the connected user.
     */
    public function testExecutionUserCannotBeOverridden(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };
        $requestStack = new RequestStack();
        $action = new class($bus, $requestStack, new ProcessConfigurationsManager(new ProcessConfigurationRegistry([], 'stop')), new AdminContextProvider($requestStack)) extends LaunchAction {
            /**
             * @param array<string, mixed> $context
             */
            public function launch(string $processCode, mixed $input, array $context): void
            {
                $this->dispatch($processCode, $input, $context);
            }

            protected function getUser(): User
            {
                return (new User())->setEmail('user@example.com');
            }
        };

        $action->launch('demo.process', 'data.csv', ['foo' => 'bar', 'execution_user' => 'someone@example.com']);

        self::assertCount(1, $bus->messages);
        $message = $bus->messages[0];
        self::assertInstanceOf(ProcessExecuteMessage::class, $message);
        self::assertSame(['execution_user' => 'user@example.com', 'foo' => 'bar'], $message->context);
    }
}
