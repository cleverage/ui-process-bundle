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

namespace CleverAge\UiProcessBundle\Form;

/**
 * Converts the typed data of the launch form (enums, dates, durations, objects...) into values that can be given to a process.
 */
final class ContextValueNormalizer
{
    /**
     * @param string $name the name of the value, used in the error messages: "input", a context key, or a nested path
     *
     * @return bool|int|float|string|array<mixed>|null
     *
     * @throws \LogicException when the value cannot be converted
     */
    public function normalize(mixed $value, string $name): mixed
    {
        if (null === $value || \is_scalar($value)) {
            return $value;
        }
        if (\is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalize($item, \sprintf('%s[%s]', $name, $key));
            }

            return $normalized;
        }
        // A backed enum is also a unit enum
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DATE_ATOM);
        }
        // An ISO 8601 duration, "-" prefixed when inverted
        if ($value instanceof \DateInterval) {
            return $value->format('%rP%yY%mM%dDT%hH%iM%sS');
        }
        // A file is also stringable
        if ($value instanceof \SplFileInfo) {
            throw new \LogicException(\sprintf('The value of "%s" is a file ("%s"): file fields are only supported for the input.', $name, $value::class));
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        throw new \LogicException(\sprintf('The value of "%s" ("%s") cannot be used as a process context value: use a form type returning a scalar (e.g. "choice" with scalar choices), or make the class implement \Stringable.', $name, get_debug_type($value)));
    }
}
