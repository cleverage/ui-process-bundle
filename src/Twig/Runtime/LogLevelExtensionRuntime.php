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

namespace CleverAge\UiProcessBundle\Twig\Runtime;

use Monolog\Level;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class LogLevelExtensionRuntime implements RuntimeExtensionInterface
{
    private TranslatorInterface $translator;

    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    public function getLabel(int $value): string
    {
        return Level::from($value)->getName();
    }

    public function getTranslation(string $key): string
    {
        return $this->translator->trans('enum.log_level.'.strtolower($key), domain: 'enums');
    }

    public function getCssClass(string|int $value): string
    {
        return \is_int($value) ?
        match ($value) {
            Level::Warning->value => 'warning',
            Level::Error->value, Level::Emergency->value, Level::Critical->value, Level::Alert->value => 'danger',
            Level::Debug->value, Level::Info->value => 'success',
            default => '',
        }
        : match ($value) {
            Level::Warning->name => 'warning',
            Level::Error->name, Level::Emergency->name, Level::Critical->name, Level::Alert->name => 'danger',
            Level::Debug->name, Level::Info->name => 'success',
            default => '',
        };
    }
}
