<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Http\Model;

use CleverAge\ProcessUiBundle\Validator\IsValidProcessCode;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;

final readonly class HttpProcessExecution
{
    public function __construct(
        #[Sequentially(constraints: [new NotNull(message: 'Process code is required.'), new IsValidProcessCode()])]
        public ?string $code = null,
        public ?string $input = null,
        public array $context = []
    ) {
    }
}
