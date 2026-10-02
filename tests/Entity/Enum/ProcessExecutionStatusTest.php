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

namespace CleverAge\UiProcessBundle\Tests\Entity\Enum;

use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProcessExecutionStatus::class)]
class ProcessExecutionStatusTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame(['started', 'finish', 'failed'], array_column(ProcessExecutionStatus::cases(), 'value'));
    }

    public function testTrans(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())
            ->method('trans')
            ->with('enum.process_execution_status.failed', [], 'enums', 'fr')
            ->willReturn('Echec');

        self::assertSame('Echec', ProcessExecutionStatus::Failed->trans($translator, 'fr'));
    }
}
