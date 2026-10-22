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

namespace CleverAge\UiProcessBundle\Tests\App\Form;

use CleverAge\UiProcessBundle\Tests\Form\Fixtures\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A form type whose data is an object that is not \Stringable, as an EntityType on an entity without __toString():
 * the submitted id is transformed into an Entity.
 *
 * @template-extends AbstractType<Entity|null>
 */
class EntityIdType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (mixed $entity): string => $entity instanceof Entity ? (string) $entity->id : '',
            static fn (mixed $id): ?Entity => \is_string($id) && '' !== $id ? new Entity((int) $id) : null,
        ));
    }

    #[\Override]
    public function getParent(): string
    {
        return TextType::class;
    }
}
