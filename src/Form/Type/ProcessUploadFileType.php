<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProcessUploadFileType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('process_code');
    }

    public function getParent(): string
    {
        return FileType::class;
    }
}
