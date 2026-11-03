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

namespace CleverAge\UiProcessBundle\Entity;

/**
 * A context stored in a JSON column: a value that cannot be encoded (e.g. invalid UTF-8 from a Latin-1 file or a
 * multipart request) would make the insertion fail.
 *
 * @internal
 */
trait EncodableContextTrait
{
    /**
     * The context unchanged when it can be encoded, otherwise its JSON representation where the invalid UTF-8
     * sequences are replaced (by U+FFFD) and the values which cannot be encoded (e.g. a resource) are replaced by null.
     *
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    private function encodableContext(array $context): array
    {
        if (false !== json_encode($context, \JSON_PRESERVE_ZERO_FRACTION)) {
            return $context;
        }
        $json = json_encode($context, \JSON_PRESERVE_ZERO_FRACTION | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR);
        $encodable = false === $json ? null : json_decode($json, true);

        return \is_array($encodable) ? $encodable : [];
    }
}
