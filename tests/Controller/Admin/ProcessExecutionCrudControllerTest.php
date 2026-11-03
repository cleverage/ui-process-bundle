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

namespace CleverAge\UiProcessBundle\Tests\Controller\Admin;

use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProcessExecutionCrudController::class)]
class ProcessExecutionCrudControllerTest extends TestCase
{
    public function testPassingATranslatorIsDeprecated(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            new ProcessExecutionCrudController($this->createStub(ProcessExecutionRepository::class), '/logs', $this->createStub(TranslatorInterface::class));
            new ProcessExecutionCrudController($this->createStub(ProcessExecutionRepository::class), '/logs');
        } finally {
            restore_error_handler();
        }

        self::assertSame(
            ['Since cleverage/ui-process-bundle 3.3: Passing a translator to "'.ProcessExecutionCrudController::class.'::__construct()" is deprecated, it is not used. The argument will be removed in 4.0.'],
            $deprecations
        );
    }
}
