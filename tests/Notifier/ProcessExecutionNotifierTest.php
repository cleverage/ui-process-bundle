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

namespace CleverAge\UiProcessBundle\Tests\Notifier;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Event\ProcessExecutionEndedEvent;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use CleverAge\UiProcessBundle\Notifier\ProcessExecutionNotification;
use CleverAge\UiProcessBundle\Notifier\ProcessExecutionNotifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Channel\ChannelPolicy;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

#[CoversClass(ProcessExecutionNotifier::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(ProcessExecutionEndedEvent::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(NotificationTrigger::class)]
#[UsesClass(ProcessExecutionNotification::class)]
class ProcessExecutionNotifierTest extends TestCase
{
    private const DEFAULT_OPTIONS = [
        'enabled' => true,
        'statuses' => ['failed', 'finish_with_report'],
        'channels' => [],
        'recipients' => [],
    ];

    /** @var \ArrayObject<int, array{Notification, RecipientInterface, ?string}> */
    private \ArrayObject $sent;

    /** @var \ArrayObject<int, array{string, string|\Stringable, array<mixed>}> */
    private \ArrayObject $logs;

    protected function setUp(): void
    {
        $this->sent = new \ArrayObject();
        $this->logs = new \ArrayObject();
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame(
            [ProcessExecutionEndedEvent::class => 'onProcessExecutionEnded'],
            ProcessExecutionNotifier::getSubscribedEvents()
        );
    }

    /**
     * @return iterable<string, array{ProcessExecutionStatus, array<string, int>, bool}>
     */
    public static function provideDefaultStatuses(): iterable
    {
        yield 'failed' => [ProcessExecutionStatus::Failed, [], true];
        yield 'finish with report' => [ProcessExecutionStatus::Finish, ['Warning' => 1], true];
        yield 'finish' => [ProcessExecutionStatus::Finish, [], false];
        yield 'started' => [ProcessExecutionStatus::Started, [], false];
    }

    /**
     * @param array<string, int> $report
     */
    #[DataProvider('provideDefaultStatuses')]
    public function testDefaultStatuses(ProcessExecutionStatus $status, array $report, bool $expectedSent): void
    {
        $processExecution = $this->createProcessExecution('test.process', $status, $report);

        $this->createNotifier()->onProcessExecutionEnded(new ProcessExecutionEndedEvent($processExecution));

        self::assertCount($expectedSent ? 1 : 0, $this->sent);
    }

    public function testNotificationIsSentToTheAdminRecipientsWithTheChannelPolicy(): void
    {
        $error = new \RuntimeException('Process error');
        $processExecution = $this->createProcessExecution('test.process', ProcessExecutionStatus::Failed);
        $admin = new Recipient('admin@example.com');

        $this->createNotifier(adminRecipients: [$admin])->onProcessExecutionEnded(new ProcessExecutionEndedEvent($processExecution, $error));

        self::assertCount(1, $this->sent);
        [$notification, $recipient, $transport] = $this->getSent()[0];
        self::assertInstanceOf(ProcessExecutionNotification::class, $notification);
        self::assertSame($processExecution, $notification->processExecution);
        self::assertSame(NotificationTrigger::Failed, $notification->trigger);
        self::assertSame('Process error', $notification->getException()?->getMessage());
        self::assertSame($admin, $recipient);
        self::assertNull($transport);
    }

    public function testNotificationWithoutRecipient(): void
    {
        $this->createNotifier()->onProcessExecutionEnded(
            new ProcessExecutionEndedEvent($this->createProcessExecution('test.process', ProcessExecutionStatus::Failed))
        );

        self::assertCount(1, $this->sent);
        self::assertInstanceOf(NoRecipient::class, $this->getSent()[0][1]);
    }

    public function testConfiguredChannelsAndRecipients(): void
    {
        $this->createNotifier(['channels' => ['test/slack'], 'recipients' => [['email' => 'ops@example.com', 'phone' => null], ['email' => null, 'phone' => '+33600000000']]] + self::DEFAULT_OPTIONS)
            ->onProcessExecutionEnded(new ProcessExecutionEndedEvent($this->createProcessExecution('test.process', ProcessExecutionStatus::Failed)));

        self::assertCount(2, $this->sent);
        self::assertInstanceOf(Recipient::class, $this->getSent()[0][1]);
        self::assertSame('ops@example.com', $this->getSent()[0][1]->getEmail());
        self::assertSame('slack', $this->getSent()[0][2]);
        self::assertInstanceOf(Recipient::class, $this->getSent()[1][1]);
        self::assertSame('+33600000000', $this->getSent()[1][1]->getPhone());
    }

    public function testDisabled(): void
    {
        $this->createNotifier(['enabled' => false] + self::DEFAULT_OPTIONS)->onProcessExecutionEnded(
            new ProcessExecutionEndedEvent($this->createProcessExecution('test.process', ProcessExecutionStatus::Failed))
        );

        self::assertCount(0, $this->sent);
    }

    public function testProcessOptionsOverrideTheDefaultOptions(): void
    {
        $notifier = $this->createNotifier(['enabled' => false] + self::DEFAULT_OPTIONS, processOptions: [
            'test.process' => ['notification' => ['enabled' => true, 'statuses' => ['finish'], 'channels' => ['test/teams']]],
            'test.disabled' => ['notification' => ['enabled' => false]],
        ]);

        self::assertSame(
            ['enabled' => true, 'statuses' => ['finish'], 'channels' => ['test/teams'], 'recipients' => []],
            $notifier->getOptions('test.process')
        );
        self::assertSame(['enabled' => false] + self::DEFAULT_OPTIONS, $notifier->getOptions('test.disabled'));
        self::assertSame(['enabled' => false] + self::DEFAULT_OPTIONS, $notifier->getOptions('unknown.process'));

        $notifier->onProcessExecutionEnded(new ProcessExecutionEndedEvent($this->createProcessExecution('test.process', ProcessExecutionStatus::Finish)));
        $notifier->onProcessExecutionEnded(new ProcessExecutionEndedEvent($this->createProcessExecution('test.process', ProcessExecutionStatus::Failed)));
        $notifier->onProcessExecutionEnded(new ProcessExecutionEndedEvent($this->createProcessExecution('test.disabled', ProcessExecutionStatus::Failed)));

        self::assertCount(1, $this->sent);
        self::assertSame(NotificationTrigger::Finish, $this->getNotification(0)->trigger);
        self::assertSame('teams', $this->getSent()[0][2]);
    }

    public function testWithoutNotifier(): void
    {
        $notifier = new ProcessExecutionNotifier($this->createConfigurationsManager([]), self::DEFAULT_OPTIONS, null, $this->createLogger());

        $notifier->onProcessExecutionEnded(new ProcessExecutionEndedEvent($this->createProcessExecution('test.process', ProcessExecutionStatus::Failed)));

        self::assertCount(1, $this->logs);
        self::assertSame('warning', $this->getLogs()[0][0]);
        self::assertStringContainsString('the notifier is not enabled', (string) $this->getLogs()[0][1]);
    }

    public function testSendingErrorIsLogged(): void
    {
        // Unknown channel: the notifier throws a LogicException
        $this->createNotifier(['channels' => ['sms']] + self::DEFAULT_OPTIONS)->onProcessExecutionEnded(
            new ProcessExecutionEndedEvent($this->createProcessExecution('test.process', ProcessExecutionStatus::Failed))
        );

        self::assertCount(0, $this->sent);
        self::assertCount(1, $this->logs);
        self::assertSame('error', $this->getLogs()[0][0]);
        self::assertSame('test.process', $this->getLogs()[0][2]['process']);
        self::assertSame('The "sms" channel does not exist.', $this->getLogs()[0][2]['error']);
    }

    /**
     * @param array{'enabled': bool, 'statuses': string[], 'channels': list<string>, 'recipients': array<array{'email': ?string, 'phone': ?string}>} $defaultOptions
     * @param array<string, array<string, mixed>>                                                                                                    $processOptions
     * @param RecipientInterface[]                                                                                                                   $adminRecipients
     */
    private function createNotifier(array $defaultOptions = self::DEFAULT_OPTIONS, array $processOptions = [], array $adminRecipients = []): ProcessExecutionNotifier
    {
        $channel = new class($this->sent) implements ChannelInterface {
            /**
             * @param \ArrayObject<int, array{Notification, RecipientInterface, ?string}> $sent
             */
            public function __construct(private readonly \ArrayObject $sent)
            {
            }

            public function notify(Notification $notification, RecipientInterface $recipient, ?string $transportName = null): void
            {
                $this->sent->append([$notification, $recipient, $transportName]);
            }

            public function supports(Notification $notification, RecipientInterface $recipient): bool
            {
                return true;
            }
        };
        $policy = new ChannelPolicy([
            Notification::IMPORTANCE_HIGH => ['test'],
            Notification::IMPORTANCE_MEDIUM => ['test'],
            Notification::IMPORTANCE_LOW => ['test'],
        ]);
        $notifier = new Notifier(['test' => $channel], $policy);
        foreach ($adminRecipients as $recipient) {
            $notifier->addAdminRecipient($recipient);
        }

        return new ProcessExecutionNotifier($this->createConfigurationsManager($processOptions), $defaultOptions, $notifier, $this->createLogger());
    }

    /**
     * @param array<string, array<string, mixed>> $processOptions
     */
    private function createConfigurationsManager(array $processOptions): ProcessConfigurationsManager
    {
        $rawConfiguration = [];
        foreach ($processOptions as $code => $options) {
            $rawConfiguration[$code] = [
                'options' => $options,
                'entry_point' => null,
                'end_point' => null,
                'description' => '',
                'help' => '',
                'public' => true,
                'tasks' => [
                    'data' => [
                        'service' => '@CleverAge\ProcessBundle\Task\DummyTask',
                        'options' => [],
                        'description' => '',
                        'help' => '',
                        'outputs' => [],
                        'errors' => [],
                        'error_outputs' => [],
                        'error_strategy' => null,
                        'log_level' => null,
                    ],
                ],
            ];
        }

        return new ProcessConfigurationsManager(new ProcessConfigurationRegistry($rawConfiguration, 'stop'));
    }

    private function createLogger(): AbstractLogger
    {
        return new class($this->logs) extends AbstractLogger {
            /**
             * @param \ArrayObject<int, array{string, string|\Stringable, array<mixed>}> $logs
             */
            public function __construct(private readonly \ArrayObject $logs)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                /** @var string $levelName */
                $levelName = $level;
                $this->logs->append([$levelName, $message, $context]);
            }
        };
    }

    /**
     * @param array<string, int> $report
     */
    private function createProcessExecution(string $code, ProcessExecutionStatus $status, array $report = []): ProcessExecution
    {
        $processExecution = new ProcessExecution($code, 'test.log');
        $processExecution->setStatus($status);
        foreach ($report as $key => $value) {
            $processExecution->addReport($key, $value);
        }
        $processExecution->end();

        return $processExecution;
    }

    /**
     * @return array<int, array{Notification, RecipientInterface, ?string}>
     */
    private function getSent(): array
    {
        return $this->sent->getArrayCopy();
    }

    /**
     * @return array<int, array{string, string|\Stringable, array<mixed>}>
     */
    private function getLogs(): array
    {
        return $this->logs->getArrayCopy();
    }

    private function getNotification(int $index): ProcessExecutionNotification
    {
        $notification = $this->getSent()[$index][0];
        self::assertInstanceOf(ProcessExecutionNotification::class, $notification);

        return $notification;
    }
}
