<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Twig\Runtime;

use Monolog\Level;
use Twig\Extension\RuntimeExtensionInterface;

class LogLevelExtensionRuntime implements RuntimeExtensionInterface
{
    public function getLabel(int $value): string
    {
        return Level::from($value)->getName();
    }

    public function getCssClass(string|int $value): string
    {
        return is_int($value) ?
        match ($value) {
            Level::Warning->value => 'warning',
            Level::Error->value, Level::Emergency->value, Level::Critical->value, Level::Alert->value => 'danger',
            Level::Debug->value, Level::Info->value => 'success',
            default => ''
        }
        : match ($value) {
            Level::Warning->name => 'warning',
            Level::Error->name, Level::Emergency->name, Level::Critical->name, Level::Alert->name => 'danger',
            Level::Debug->name, Level::Info->name => 'success',
            default => ''
        };
    }
}
