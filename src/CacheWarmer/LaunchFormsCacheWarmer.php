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

namespace CleverAge\UiProcessBundle\CacheWarmer;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\Existence;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;

/**
 * Resolves the launch form of the processes declaring the "form" UI option, and builds it with the form factory of the
 * application, each time the container is built (not optional: also run when the container is rebuilt in debug mode),
 * so that an invalid launch form fails there instead of on the launch page:
 * - its structure (types, keys, file fields, default values of the enum fields, constraints, conflicts with the
 *   deprecated options), checked by LaunchFormOptionsResolver;
 * - the options of the form types (with the type extensions of the application), including the "constraints" option;
 * - the default values, transformed by the fields;
 * - the validators of the constraints (of the fields and of the whole form, nested constraints included).
 *
 * The values submitted by the user are converted after the submission.
 *
 * @internal
 */
final readonly class LaunchFormsCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private ProcessConfigurationsManager $processConfigurationsManager,
        private FormFactoryInterface $formFactory,
        // Null when the validator is not enabled
        private ?ConstraintValidatorFactoryInterface $constraintValidatorFactory = null,
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $configurations = [...$this->processConfigurationsManager->getPublicProcesses(), ...$this->processConfigurationsManager->getPrivateProcesses()];
        foreach ($configurations as $configuration) {
            $ui = $configuration->getOptions()['ui'] ?? null;
            if (\is_array($ui) && \array_key_exists('form', $ui)) {
                $this->checkLaunchForm($configuration);
            }
        }

        return [];
    }

    private function checkLaunchForm(ProcessConfiguration $configuration): void
    {
        $processCode = $configuration->getCode();
        // The deprecations of the launch form are triggered when it is displayed
        set_error_handler(static fn (): bool => true, \E_USER_DEPRECATED);
        try {
            $launchForm = $this->processConfigurationsManager->getLaunchFormOptions($processCode);
            if (null === $launchForm) {
                return;
            }
            $form = $this->formFactory->create(LaunchType::class, null, [
                'constraints' => $launchForm['constraints'],
                'process_code' => $processCode,
                'launch_form' => $launchForm,
            ]);
            $form->setData($launchForm['data']);

            $constraints = $launchForm['constraints'];
            foreach ([$launchForm['input'], ...array_values($launchForm['context'])] as $field) {
                if (\is_array($field['options']['constraints'] ?? null)) {
                    $constraints = [...$constraints, ...$field['options']['constraints']];
                }
            }
            $this->checkConstraintValidators($constraints);
        } catch (\Throwable $e) {
            throw new \LogicException(\sprintf('The launch form ("form" UI option) of the process "%s" is invalid: %s', $processCode, $e->getMessage()), 0, $e);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param array<mixed> $constraints
     */
    private function checkConstraintValidators(array $constraints): void
    {
        if (!$this->constraintValidatorFactory instanceof ConstraintValidatorFactoryInterface) {
            return;
        }
        foreach ($constraints as $constraint) {
            if (!$constraint instanceof Constraint) {
                continue;
            }
            // Required and Optional only hold the constraints of a field of a Collection
            if (!$constraint instanceof Existence) {
                $this->constraintValidatorFactory->getInstance($constraint);
            }
            // The constraints of Collection, All, AtLeastOneOf, Sequentially...
            if ($constraint instanceof Composite) {
                $this->checkConstraintValidators($constraint->getNestedConstraints());
            }
        }
    }
}
