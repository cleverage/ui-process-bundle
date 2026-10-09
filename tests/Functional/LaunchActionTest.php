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

namespace CleverAge\UiProcessBundle\Tests\Functional;

use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\Controller\Admin\LogRecordCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\Process\LaunchAction;
use CleverAge\UiProcessBundle\Controller\Admin\Process\ListAction;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessScheduleCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionAuthenticator;
use CleverAge\UiProcessBundle\Twig\Extension\LogLevelExtension;
use CleverAge\UiProcessBundle\Twig\Extension\MD5Extension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExecutionExtension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExtension;
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExecutionExtensionRuntime;
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(LaunchAction::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(ListAction::class)]
#[UsesClass(User::class)]
#[UsesClass(LaunchType::class)]
#[UsesClass(ProcessContextType::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecuteMessage::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(HttpProcessExecutionAuthenticator::class)]
#[UsesClass(LogLevelExtension::class)]
#[UsesClass(MD5Extension::class)]
#[UsesClass(ProcessExecutionExtension::class)]
#[UsesClass(ProcessExtension::class)]
#[UsesClass(ProcessExecutionExtensionRuntime::class)]
#[UsesClass(ProcessExtensionRuntime::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
class LaunchActionTest extends FunctionalTestCase
{
    public function testLaunchWithoutForm(): void
    {
        $this->login();

        $this->client->request('GET', '/process?routeName=process_launch&process=test.process');

        self::assertResponseRedirects();
        self::assertStringContainsString('routeName=process_list', (string) $this->client->getResponse()->headers->get('Location'));
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame('test.process', $messages[0]->code);
        self::assertNull($messages[0]->input);
        self::assertSame(['execution_user' => 'user@example.com'], $messages[0]->context);

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Process has been added to queue');
    }

    public function testLaunchWithForm(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=process_launch&process=test.form');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->getDispatchedMessages());
        // Default values of the UI options
        self::assertSame('default input', $crawler->filter('input[name="launch[input]"]')->attr('value'));
        self::assertSame('key1', $crawler->filter('input[name="launch[context][0][key]"]')->attr('value'));

        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['input'] = 'my input';
        $values['launch']['context'] = [['key' => 'key1', 'value' => 'value1'], ['key' => 'key2', 'value' => 'value2']];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame('test.form', $messages[0]->code);
        self::assertSame('my input', $messages[0]->input);
        self::assertSame(['execution_user' => 'user@example.com', 'key1' => 'value1', 'key2' => 'value2'], $messages[0]->context);
    }

    public function testLaunchWithFileUpload(): void
    {
        $this->login();
        $file = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents((string) $file, 'line1');

        // test.upload has no "default" UI option: the form must be displayed with empty default values
        $crawler = $this->client->request('GET', '/process?routeName=process_launch&process=test.upload');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('input[name^="launch[context]"]'));
        self::assertCount(1, $crawler->filter('input[type="file"][name="launch[input]"]'));

        $form = $crawler->selectButton('Launch')->form();
        $this->client->request(
            $form->getMethod(),
            $form->getUri(),
            $form->getPhpValues(),
            ['launch' => ['input' => new UploadedFile((string) $file, 'data.csv', 'text/csv', null, true)]]
        );

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        // The file is saved in the upload directory, its path is the process input
        self::assertIsString($messages[0]->input);
        self::assertStringEndsWith('.csv', $messages[0]->input);
        $uploadDirectory = static::getContainer()->getParameter('upload_directory');
        self::assertIsString($uploadDirectory);
        self::assertStringStartsWith($uploadDirectory.'/', $messages[0]->input);
        self::assertSame('line1', file_get_contents($messages[0]->input));
        unlink((string) $file);
    }

    /**
     * A context row without key, on a process with constraints on the context: the empty key broke the property paths
     * of the constraints (500 "Could not parse property path").
     */
    public function testContextRowWithoutKey(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=process_launch&process=test.form_constraints');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = [['key' => 'key1', 'value' => 'value1'], ['key' => '', 'value' => 'value2']];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        // The form is displayed again with the error (LaunchAction renders an invalid form with a 200)
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.invalid-feedback', 'This value should not be blank.');
        self::assertSame([], $this->getDispatchedMessages());
    }

    public function testMissingProcessCode(): void
    {
        $this->login();

        $this->client->request('GET', '/process?routeName=process_launch');

        self::assertResponseStatusCodeSame(500);
    }

    public function testUnknownProcess(): void
    {
        $this->login();

        $this->client->request('GET', '/process?routeName=process_launch&process=unknown');

        self::assertResponseStatusCodeSame(500);
        self::assertSame([], $this->getDispatchedMessages());
    }

    /**
     * Accessed directly, not through the dashboard: redirected to the dashboard with the query parameters (the
     * template of the form needs the EasyAdmin context, it was a 500).
     */
    public function testDirectAccessRedirectsToTheDashboard(): void
    {
        $this->login();

        $this->client->request('GET', '/process/launch?process=test.form');

        self::assertResponseRedirects('/process?routeName=process_launch&process=test.form');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="launch[input]"]'));
    }
}
