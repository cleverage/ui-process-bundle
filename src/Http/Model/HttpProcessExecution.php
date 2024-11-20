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
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;

final readonly class HttpProcessExecution
{
    public function __construct(
        #[Sequentially(constraints: [new NotNull(message: 'Process code is required.'), new IsValidProcessCode()])]
        public ?string $code = null,
        public ?string $input = null,
        public array $context = [],
    ) {
    }
}
