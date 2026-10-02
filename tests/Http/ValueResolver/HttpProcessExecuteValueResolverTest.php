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

namespace CleverAge\UiProcessBundle\Tests\Http\ValueResolver;

use CleverAge\UiProcessBundle\Http\Model\HttpProcessExecution;
use CleverAge\UiProcessBundle\Http\ValueResolver\HttpProcessExecuteValueResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[CoversClass(HttpProcessExecuteValueResolver::class)]
#[UsesClass(HttpProcessExecution::class)]
class HttpProcessExecuteValueResolverTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/'.uniqid('http_value_resolver_test_', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->storageDir);
    }

    public function testEmptyRequest(): void
    {
        $execution = $this->resolve(Request::create('/http/process/execute', 'POST'));

        self::assertEquals(new HttpProcessExecution(), $execution);
    }

    public function testJsonContent(): void
    {
        $request = Request::create('/http/process/execute', 'POST', content: (string) json_encode([
            'code' => 'test.process',
            'input' => 'data.csv',
            'context' => ['foo' => 'bar'],
            'queue' => false,
        ]));

        self::assertEquals(
            new HttpProcessExecution('test.process', 'data.csv', ['foo' => 'bar'], false),
            $this->resolve($request)
        );
    }

    public function testInvalidJsonContent(): void
    {
        $request = Request::create('/http/process/execute', 'POST', content: '{"code": ');

        self::assertEquals(new HttpProcessExecution(), $this->resolve($request));
    }

    public function testFormData(): void
    {
        $request = Request::create('/http/process/execute', 'POST', [
            'code' => 'test.process',
            'input' => 'data.csv',
            'context' => ['foo' => 'bar'],
            'queue' => '0',
        ]);

        self::assertEquals(
            new HttpProcessExecution('test.process', 'data.csv', ['foo' => 'bar'], false),
            $this->resolve($request)
        );
    }

    public function testFormDataIsQueuedByDefault(): void
    {
        $request = Request::create('/http/process/execute', 'POST', ['code' => 'test.process']);

        self::assertEquals(new HttpProcessExecution('test.process', null, [], true), $this->resolve($request));
    }

    public function testQueryParametersWithFormData(): void
    {
        $request = Request::create(
            '/http/process/execute?code=test.process&input=data.csv&context[foo]=bar',
            'POST',
            ['queue' => '1']
        );

        self::assertEquals(
            new HttpProcessExecution('test.process', 'data.csv', ['foo' => 'bar'], true),
            $this->resolve($request)
        );
    }

    public function testUploadedFileInput(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'upload');
        self::assertIsString($file);
        file_put_contents($file, 'id;name');
        $request = Request::create(
            '/http/process/execute',
            'POST',
            ['code' => 'test.process'],
            files: ['input' => new UploadedFile($file, 'data.csv', 'text/csv', null, true)]
        );

        $execution = $this->resolve($request);
        unlink($file);

        self::assertSame('test.process', $execution->code);
        self::assertIsString($execution->input);
        self::assertStringStartsWith($this->storageDir.\DIRECTORY_SEPARATOR, $execution->input);
        self::assertStringEndsWith('_data.csv', $execution->input);
        self::assertSame('id;name', file_get_contents($execution->input));
    }

    public function testInvalidFormDataContext(): void
    {
        // The context must be an array in form data
        $request = Request::create('/http/process/execute', 'POST', ['code' => 'test.process', 'context' => 'foo']);

        self::assertEquals(new HttpProcessExecution(), $this->resolve($request));
    }

    private function resolve(Request $request): HttpProcessExecution
    {
        $resolver = new HttpProcessExecuteValueResolver(
            $this->storageDir,
            new Serializer([new ObjectNormalizer()], [new JsonEncoder()])
        );
        $values = $resolver->resolve(
            $request,
            new ArgumentMetadata('httpProcessExecution', HttpProcessExecution::class, false, false, null)
        );
        $values = \is_array($values) ? $values : iterator_to_array($values, false);

        self::assertCount(1, $values);
        self::assertInstanceOf(HttpProcessExecution::class, $values[0]);

        return $values[0];
    }
}
