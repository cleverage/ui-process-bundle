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

namespace CleverAge\UiProcessBundle\Tests\Twig\Components;

use CleverAge\UiProcessBundle\Twig\Components\BootstrapModal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BootstrapModal::class)]
class BootstrapModalTest extends TestCase
{
    public function testPropertiesDefaultToNullAndAreWritable(): void
    {
        $modal = new BootstrapModal();
        self::assertNull($modal->id);
        self::assertNull($modal->title);
        self::assertNull($modal->message);
        self::assertNull($modal->confirmUrl);

        $modal->id = 'modal-id';
        $modal->title = 'Title';
        $modal->message = 'Are you sure?';
        $modal->confirmUrl = '/confirm';

        self::assertSame('modal-id', $modal->id);
        self::assertSame('Title', $modal->title);
        self::assertSame('Are you sure?', $modal->message);
        self::assertSame('/confirm', $modal->confirmUrl);
    }
}
