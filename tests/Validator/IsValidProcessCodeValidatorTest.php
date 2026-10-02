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

namespace CleverAge\UiProcessBundle\Tests\Validator;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCode;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCodeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<IsValidProcessCodeValidator>
 */
#[CoversClass(IsValidProcessCodeValidator::class)]
#[CoversClass(IsValidProcessCode::class)]
class IsValidProcessCodeValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): IsValidProcessCodeValidator
    {
        $configurations = [
            'demo.public' => new ProcessConfiguration('demo.public', []),
            'demo.private' => new ProcessConfiguration('demo.private', [], public: false),
        ];
        $registry = $this->createStub(ProcessConfigurationRegistry::class);
        $registry->method('hasProcessConfiguration')
            ->willReturnCallback(static fn (string $code): bool => isset($configurations[$code]));
        $registry->method('getProcessConfiguration')
            ->willReturnCallback(static fn (string $code): ProcessConfiguration => $configurations[$code]);

        return new IsValidProcessCodeValidator($registry);
    }

    public function testPublicProcess(): void
    {
        $this->validator->validate('demo.public', new IsValidProcessCode());

        $this->assertNoViolation();
    }

    public function testPrivateProcess(): void
    {
        $this->validator->validate('demo.private', new IsValidProcessCode());

        $this->buildViolation('The process "{{ value }}" is not public.')
            ->setParameter('{{ value }}', 'demo.private')
            ->assertRaised();
    }

    public function testUnknownProcess(): void
    {
        $this->validator->validate('demo.unknown', new IsValidProcessCode());

        $this->buildViolation('The process "{{ value }}" does not exist.')
            ->setParameter('{{ value }}', 'demo.unknown')
            ->assertRaised();
    }
}
