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

namespace CleverAge\ProcessUiBundle\Controller\Crud;

use CleverAge\ProcessUiBundle\CleverAgeProcessUiBundle;
use CleverAge\ProcessUiBundle\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud->showEntityActionsInlined();
        $crud->setEntityPermission('ROLE_ADMIN');

        return $crud;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addPanel('Credentials')->setIcon('fa fa-key');
        yield EmailField::new('email');
        yield TextField::new('password', 'New password')
            ->onlyOnForms()
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'first_options' => ['label' => 'New password'],
                'second_options' => ['label' => 'Repeat password'],
            ]);

        yield FormField::addPanel('Informations')->setIcon('fa fa-user');
        yield TextField::new('firstname');
        yield TextField::new('lastname');

        yield FormField::addPanel('Roles')->setIcon('fa fa-theater-masks');
        yield ChoiceField::new('roles', false)
            ->setChoices(['ROLE_ADMIN' => 'ROLE_ADMIN', 'ROLE_USER' => 'ROLE_USER'])
            ->setFormTypeOptions(['multiple' => true, 'expanded' => true]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action
                ->setIcon(CleverAgeProcessUiBundle::ICON_NEW)
                ->setLabel(CleverAgeProcessUiBundle::LABEL_NEW)
                ->addCssClass(CleverAgeProcessUiBundle::CLASS_NEW)
            )->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action
                ->setIcon(CleverAgeProcessUiBundle::ICON_EDIT)
                ->setLabel(CleverAgeProcessUiBundle::LABEL_EDIT)
                ->addCssClass(CleverAgeProcessUiBundle::CLASS_EDIT)
            )->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action
                ->setIcon(CleverAgeProcessUiBundle::ICON_DELETE)
                ->setLabel(CleverAgeProcessUiBundle::LABEL_DELETE)
                ->addCssClass(CleverAgeProcessUiBundle::CLASS_DELETE)
            )->update(Crud::PAGE_INDEX, Action::BATCH_DELETE, fn (Action $action) => $action
                ->setLabel('Delete')
                ->addCssClass(CleverAgeProcessUiBundle::CLASS_DELETE)
            );
    }

    public function createEditFormBuilder(
        EntityDto $entityDto,
        KeyValueStore $formOptions,
        AdminContext $context,
    ): FormBuilderInterface {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        $this->addEncodePasswordEventListener($formBuilder);

        return $formBuilder;
    }

    public function createNewFormBuilder(
        EntityDto $entityDto,
        KeyValueStore $formOptions,
        AdminContext $context,
    ): FormBuilderInterface {
        $formBuilder = parent::createNewFormBuilder($entityDto, $formOptions, $context);

        $this->addEncodePasswordEventListener($formBuilder);

        return $formBuilder;
    }

    protected function addEncodePasswordEventListener(FormBuilderInterface $formBuilder): void
    {
        $formBuilder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            /** @var User $user */
            $user = $event->getData();
            $password = $user->getPassword();
            if ($password) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            }
        });
    }
}
