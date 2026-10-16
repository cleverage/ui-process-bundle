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
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckLaunchFormsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Form\ContextValueNormalizer;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionAuthenticator;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\Entity;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\PureEnum;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\StringBackedEnum;
use CleverAge\UiProcessBundle\Twig\Extension\LogLevelExtension;
use CleverAge\UiProcessBundle\Twig\Extension\MD5Extension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExecutionExtension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExtension;
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExecutionExtensionRuntime;
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(LaunchAction::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(ListAction::class)]
#[UsesClass(User::class)]
#[UsesClass(LaunchType::class)]
#[UsesClass(ProcessContextType::class)]
#[UsesClass(ContextValueNormalizer::class)]
#[UsesClass(LaunchFormOptionsResolver::class)]
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
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(CheckLaunchFormsPass::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(DeprecatedEasyAdminRouteAliasLoader::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
class LaunchActionTest extends FunctionalTestCase
{
    public function testLaunchWithoutForm(): void
    {
        $this->login();

        $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.process');

        self::assertResponseRedirects();
        self::assertStringContainsString('routeName=cleverage_ui_process_list', (string) $this->client->getResponse()->headers->get('Location'));
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

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->getDispatchedMessages());
        // Default values of the UI options
        self::assertSame('default input', $crawler->filter('input[name="launch[input]"]')->attr('value'));
        self::assertSame('key1', $crawler->filter('input[name="launch[context][extra][0][key]"]')->attr('value'));

        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['input'] = 'my input';
        $values['launch']['context'] = ['extra' => [['key' => 'key1', 'value' => 'value1'], ['key' => 'key2', 'value' => 'value2']]];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame('test.form', $messages[0]->code);
        self::assertSame('my input', $messages[0]->input);
        self::assertSame(['execution_user' => 'user@example.com', 'key1' => 'value1', 'key2' => 'value2'], $messages[0]->context);
    }

    public function testLaunchWithDeclaredFields(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_fields');

        self::assertResponseIsSuccessful();
        // Default value of the declared field
        self::assertSame(',', $crawler->filter('select[name="launch[context][delimiter]"] option[selected]')->attr('value'));

        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['delimiter' => ';', 'dry_run' => '1', 'extra' => [['key' => 'k', 'value' => 'v']]];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame('test.form_fields', $messages[0]->code);
        self::assertSame(['execution_user' => 'user@example.com', 'delimiter' => ';', 'dry_run' => true, 'k' => 'v'], $messages[0]->context);
    }

    public function testLaunchWithFileUpload(): void
    {
        $this->login();
        $file = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents((string) $file, 'line1');

        // test.upload has no "default" UI option: the form must be displayed with empty default values
        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.upload');
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

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_constraints');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['extra' => [['key' => 'key1', 'value' => 'value1'], ['key' => '', 'value' => 'value2']]];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        // The form is displayed again with the error (LaunchAction renders an invalid form with a 200)
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.invalid-feedback', 'This value should not be blank.');
        self::assertSame([], $this->getDispatchedMessages());
    }

    /**
     * The deprecated format is unchanged: same initial values, same dispatched input and context (only the HTML names
     * of the free rows change, "launch[context][extra][N]").
     */
    public function testDeprecatedFormatIsUnchanged(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form');
        self::assertResponseIsSuccessful();
        self::assertSame('value1', $crawler->filter('input[name="launch[context][extra][0][value]"]')->attr('value'));
        self::assertCount(0, $crawler->filter('[name^="launch[context][0]"]'));

        // Submitted with its initial values
        $form = $crawler->selectButton('Launch')->form();
        $this->client->request($form->getMethod(), $form->getUri(), $form->getPhpValues());

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame('default input', $messages[0]->input);
        self::assertSame(['execution_user' => 'user@example.com', 'key1' => 'value1'], $messages[0]->context);
    }

    /**
     * The deprecated global "constraints" option still applies to the free rows of the context.
     */
    public function testDeprecatedConstraintsOnTheFreeContext(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_constraints');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();

        $values['launch']['context'] = ['extra' => [['key' => 'key1', 'value' => '']]];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.invalid-feedback', 'This value should not be blank.');
        self::assertSame([], $this->getDispatchedMessages());

        $values['launch']['context'] = ['extra' => [['key' => 'key1', 'value' => 'value1']]];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame(['execution_user' => 'user@example.com', 'key1' => 'value1'], $messages[0]->context);
    }

    public function testInvalidChoice(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_fields');
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['delimiter' => '|'];
        $crawler = $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseIsSuccessful();
        self::assertSame('The selected choice is invalid.', $this->getFieldError($crawler, 'launch_context_delimiter'));
        self::assertSame([], $this->getDispatchedMessages());
    }

    /**
     * A project-specific constraint, given by its FQCN: its violation is displayed under the field.
     */
    public function testFieldConstraintClass(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_custom_constraint');
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['count' => '3'];
        $crawler = $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseIsSuccessful();
        self::assertSame('The value 3 is not even.', $this->getFieldError($crawler, 'launch_context_count'));
        self::assertSame([], $this->getDispatchedMessages());

        $values['launch']['context'] = ['count' => '4'];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame(['execution_user' => 'user@example.com', 'count' => 4], $messages[0]->context);
    }

    public function testExtraContextRowWithADeclaredKey(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_fields');
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['delimiter' => ';', 'extra' => [['key' => 'delimiter', 'value' => 'zz']]];
        $crawler = $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseIsSuccessful();
        self::assertSame(
            'The context key "delimiter" is a field of the launch form, use this field instead.',
            $this->getFieldError($crawler, 'launch_context_extra_0_key')
        );
        self::assertSame([], $this->getDispatchedMessages());
    }

    public function testDateFields(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_fields_date');
        self::assertResponseIsSuccessful();
        // A string default value, with "input: string"
        self::assertSame('2026-01-02', $crawler->filter('input[name="launch[context][to]"]')->attr('value'));
        self::assertSame('', (string) $crawler->filter('input[name="launch[context][from]"]')->attr('value'));

        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['from' => '2026-03-04', 'to' => '2026-05-06'];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame(
            [
                'execution_user' => 'user@example.com',
                // A DateTimeImmutable (in the default timezone of PHP), converted to the ATOM format
                'from' => (new \DateTimeImmutable('2026-03-04 00:00:00'))->format(\DATE_ATOM),
                'to' => '2026-05-06',
            ],
            $messages[0]->context
        );
    }

    public function testEmptyDeclaredField(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_fields_date');
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['from' => '', 'to' => '2026-05-06'];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        // The key of an empty declared field is in the context
        self::assertSame(['execution_user' => 'user@example.com', 'from' => null, 'to' => '2026-05-06'], $messages[0]->context);
    }

    public function testDeclaredFileInput(): void
    {
        $this->login();
        $file = (string) tempnam(sys_get_temp_dir(), 'upload');
        // Detected as "text/csv" by fileinfo: the "extensions" option of the File constraint also checks the mime type
        file_put_contents($file, "id,name,city\n1,foo,bar\n2,baz,qux\n3,a,b\n");

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_upload');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[type="file"][name="launch[input]"]'));
        $form = $crawler->selectButton('Launch')->form();

        // An extension refused by the File constraint of the input
        $crawler = $this->client->request(
            $form->getMethod(),
            $form->getUri(),
            $form->getPhpValues(),
            ['launch' => ['input' => new UploadedFile($file, 'data.txt', 'text/plain', null, true)]]
        );
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"txt"', $this->getFieldError($crawler, 'launch_input'));
        self::assertSame([], $this->getDispatchedMessages());

        $this->client->request(
            $form->getMethod(),
            $form->getUri(),
            $form->getPhpValues(),
            ['launch' => ['input' => new UploadedFile($file, 'data.csv', 'text/csv', null, true)]]
        );
        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertIsString($messages[0]->input);
        self::assertStringEndsWith('.csv', $messages[0]->input);
        self::assertSame("id,name,city\n1,foo,bar\n2,baz,qux\n3,a,b\n", file_get_contents($messages[0]->input));
        // The default value of the declared field
        self::assertSame(['execution_user' => 'user@example.com', 'delimiter' => ','], $messages[0]->context);
        unlink($file);
    }

    /**
     * A CSV file with a semicolon delimiter is detected as "text/plain": the File constraint of the input lists the
     * accepted mime types of the "csv" extension.
     */
    public function testDeclaredFileInputWithASemicolonDelimiter(): void
    {
        $this->login();
        $file = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($file, "id;name;city\n1;foo;bar\n2;baz;qux\n");

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_upload');
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['delimiter' => ';'];
        $this->client->request(
            $form->getMethod(),
            $form->getUri(),
            $values,
            ['launch' => ['input' => new UploadedFile($file, 'data.csv', 'text/csv', null, true)]]
        );

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertIsString($messages[0]->input);
        self::assertSame("id;name;city\n1;foo;bar\n2;baz;qux\n", file_get_contents($messages[0]->input));
        self::assertSame(['execution_user' => 'user@example.com', 'delimiter' => ';'], $messages[0]->context);
        unlink($file);
    }

    public function testExtraContextNotAllowed(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_strict');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="launch[context][delimiter]"]'));
        self::assertCount(0, $crawler->filter('[name^="launch[context][extra]"]'));
        self::assertCount(0, $crawler->filter('[data-ea-collection-field]'));

        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();

        // A free row submitted anyway
        $values['launch']['context'] = ['delimiter' => ';', 'extra' => [['key' => 'k', 'value' => 'v']]];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.invalid-feedback', 'This form should not contain extra fields.');
        self::assertSame([], $this->getDispatchedMessages());

        $values['launch']['context'] = ['delimiter' => ';'];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame(['execution_user' => 'user@example.com', 'delimiter' => ';'], $messages[0]->context);
    }

    public function testGlobalConstraintOnADeclaredField(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_global_constraints');
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['code' => ''];
        $crawler = $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseIsSuccessful();
        // The violation of the "form.constraints" option is displayed under the declared field
        self::assertSame('This value should not be blank.', $this->getFieldError($crawler, 'launch_context_code'));
        self::assertSame([], $this->getDispatchedMessages());

        $values['launch']['context'] = ['code' => 'ABC'];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame(['execution_user' => 'user@example.com', 'code' => 'ABC'], $messages[0]->context);
    }

    /**
     * The default value of an enum field is an enum case, given with the "!php/enum" YAML tag.
     */
    public function testEnumFieldDefault(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_enum_default');
        self::assertResponseIsSuccessful();
        self::assertSame(',', $crawler->filter('select[name="launch[context][delimiter]"] option[selected]')->attr('value'));

        $this->client->submit($crawler->selectButton('Launch')->form());

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        // The value of the case of the backed enum
        self::assertSame(['execution_user' => 'user@example.com', 'delimiter' => StringBackedEnum::Comma->value], $messages[0]->context);
    }

    public function testPureEnumField(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_enum');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['choice' => $crawler->filter('select[name="launch[context][choice]"] option')->attr('value')];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        // The name of the case of the pure enum
        self::assertSame(['execution_user' => 'user@example.com', 'choice' => PureEnum::Foo->name], $messages[0]->context);
    }

    /**
     * A field whose data is an object that cannot be converted (as an EntityType on an entity that is not
     * \Stringable): the error is displayed under the field, nothing is dispatched.
     */
    public function testContextValueThatCannotBeConverted(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_object');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['context'] = ['entity' => '42'];
        $crawler = $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseIsSuccessful();
        self::assertSame(
            \sprintf('The value of "entity" ("%s") cannot be used as a process context value: use a form type returning a scalar (e.g. a ChoiceType with scalar choices), or make the class implement \Stringable.', Entity::class),
            $this->getFieldError($crawler, 'launch_context_entity')
        );
        self::assertSame([], $this->getDispatchedMessages());
    }

    public function testInputThatCannotBeConverted(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_object');
        $form = $crawler->selectButton('Launch')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['launch']['input'] = '7';
        $crawler = $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseIsSuccessful();
        self::assertSame(
            \sprintf('The value of "input" ("%s") cannot be used as a process context value: use a form type returning a scalar (e.g. a ChoiceType with scalar choices), or make the class implement \Stringable.', Entity::class),
            $this->getFieldError($crawler, 'launch_input')
        );
        self::assertSame([], $this->getDispatchedMessages());
    }

    /**
     * The UI options are resolved once per launch request: the deprecations of the old format are triggered once.
     */
    public function testDeprecationsOfTheLaunchForm(): void
    {
        $this->login();

        $deprecations = $this->collectDeprecations(fn () => $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form'));
        self::assertResponseIsSuccessful();
        self::assertSame([
            'Since cleverage/ui-process-bundle 3.3: The "entrypoint_type" UI option of the process "test.form" is deprecated, use "form.input.type" instead.',
            'Since cleverage/ui-process-bundle 3.3: The "default" UI option of the process "test.form" is deprecated, use "form.input.default" and "form.context.<key>.default" instead.',
        ], $deprecations);

        // A process with the "form" UI option triggers no deprecation
        $deprecations = $this->collectDeprecations(fn () => $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=test.form_fields'));
        self::assertResponseIsSuccessful();
        self::assertSame([], $deprecations);
    }

    public function testMissingProcessCode(): void
    {
        $this->login();

        $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch');

        self::assertResponseStatusCodeSame(500);
    }

    public function testUnknownProcess(): void
    {
        $this->login();

        $this->client->request('GET', '/process?routeName=cleverage_ui_process_launch&process=unknown');

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

        self::assertResponseRedirects('/process?routeName=cleverage_ui_process_launch&process=test.form');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="launch[input]"]'));
    }

    /**
     * The error message displayed under the form field with the given id.
     */
    private function getFieldError(Crawler $crawler, string $id): string
    {
        $errors = $crawler->filter('#'.$id)->closest('.form-widget')?->filter('.invalid-feedback');
        self::assertNotNull($errors);
        self::assertCount(1, $errors, \sprintf('One error is expected under the field "%s".', $id));

        return trim($errors->text());
    }
}
