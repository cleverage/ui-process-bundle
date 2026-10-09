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

namespace CleverAge\UiProcessBundle\Http\Model;

use CleverAge\UiProcessBundle\Validator\IsValidProcessCode;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class HttpProcessExecution
{
    /**
     * @param string|array<string|int, mixed> $context
     */
    public function __construct(
        #[Sequentially(constraints: [new NotNull(message: 'Process code is required.'), new IsValidProcessCode()])]
        public ?string $code = null,
        public ?string $input = null,
        #[AtLeastOneOf(constraints: [new Json(), new Type('array')])]
        public string|array $context = [],
        public bool $queue = true,
    ) {
    }

    /**
     * A JSON context must decode to an array: the Json constraint also accepts scalars ("1", "null"...) and "".
     */
    #[Callback]
    public function validateContext(ExecutionContextInterface $context): void
    {
        if (!\is_string($this->context)) {
            return;
        }
        try {
            $decoded = json_decode($this->context, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            if ('' !== $this->context) {
                return; // Reported by the Json constraint
            }
            $decoded = null;
        }
        if (!\is_array($decoded)) {
            $context->buildViolation('Context must be a JSON object or array.')
                ->atPath('context')
                ->addViolation();
        }
    }
}
