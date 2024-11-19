<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Form\Type;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessUiBundle\Manager\ProcessConfigurationsManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LaunchType extends AbstractType
{
    public function __construct(
        private readonly ProcessConfigurationRegistry $registry,
        private readonly ProcessConfigurationsManager $configurationsManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $code = $options['process_code'];
        $configuration = $this->registry->getProcessConfiguration($code);
        $uiOptions = $this->configurationsManager->getUiOptions($code);
        $builder->add(
            'input',
            'file' === $uiOptions['entrypoint_type'] ? FileType::class : TextType::class,
            [
                'required' => !(null === $configuration->getEntryPoint()),
            ]
        );
        $builder->add(
            'context',
            CollectionType::class,
            [
                'entry_type' => ProcessContextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
            ]
        );
        $builder->get('context')->addModelTransformer(new CallbackTransformer(
            function ($data) {
                return null === $data ? [] : $data;
            },
            function ($data) {
                return array_column($data ?? [], 'value', 'key');
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('process_code');
    }

    public function getParent(): string
    {
        return FormType::class;
    }
}
