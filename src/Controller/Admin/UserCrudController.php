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

namespace CleverAge\UiProcessBundle\Controller\Admin;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\LocaleField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimezoneField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_SUPER_ADMIN')]
class UserCrudController extends AbstractCrudController
{
    /** @param array<string, array<string, string>|string> $roles */
    public function __construct(
        private array $roles,
        private readonly ProcessConfigurationRegistry $processConfigurationRegistry,
        private readonly TranslatorInterface $translator,
    ) {
        foreach ($this->processConfigurationRegistry->getProcessConfigurations() as $config) {
            $this->roles[$config->getCode()] = [
                $this->translator->trans('View process').' '.$config->getCode() => 'ROLE_PROCESS_VIEW#'.$config->getCode(),
                $this->translator->trans('Execute process').' '.$config->getCode() => 'ROLE_PROCESS_EXECUTE#'.$config->getCode(),
            ];
        }
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud->showEntityActionsInlined();
        $crud->setEntityPermission('ROLE_SUPER_ADMIN');

        return $crud;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Credentials')->setIcon('fa fa-key');
        yield EmailField::new('email');
        yield TextField::new('password', 'New password')
            ->onlyOnForms()
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions(
                [
                    'type' => PasswordType::class,
                    'first_options' => [
                        'label' => 'New password',
                        'hash_property_path' => 'password',
                        'always_empty' => false,
                    ],
                    'second_options' => ['label' => 'Repeat password'],
                    'mapped' => false,
                ]
            );
        yield FormField::addTab('Informations')->setIcon('fa fa-user');
        yield TextField::new('firstname');
        yield TextField::new('lastname');

        yield FormField::addTab('Roles')->setIcon('fa fa-theater-masks');
        yield ChoiceField::new('roles', false)
            ->setChoices($this->roles)
            ->setFormTypeOptions(['multiple' => true, 'expanded' => false]);
        yield FormField::addTab('Intl.')->setIcon('fa fa-flag');
        yield TimezoneField::new('timezone');
        yield LocaleField::new('locale');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setIcon('fa fa-plus')
                ->setLabel(false)
                ->addCssClass(''))->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action->setIcon('fa fa-edit')
                ->setLabel(false)
                ->addCssClass('text-warning'))->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action->setIcon('fa fa-trash-o')
                ->setLabel(false)
                ->addCssClass(''))->update(Crud::PAGE_INDEX, Action::BATCH_DELETE, fn (Action $action) => $action->setLabel('Delete')
                ->addCssClass(''))->add(Crud::PAGE_EDIT, Action::new('generateToken')->linkToCrudAction('generateToken'))
                ->add(Crud::PAGE_INDEX, Action::new('ConnectAs')->linkToUrl(function (User $user) {
                    return $this->generateUrl('process', ['_switch_user' => $user->getEmail()], UrlGenerator::ABSOLUTE_URL);
                })->setLabel(false)->setIcon('fa-solid fa-right-to-bracket'))->setPermission('ConnectAs', 'ROLE_SUPER_ADMIN');
    }

    public function generateToken(AdminContext $adminContext, AdminUrlGenerator $adminUrlGenerator): Response
    {
        /** @var User $user */
        $user = $adminContext->getEntity()->getInstance();
        $token = md5(uniqid(date('YmdHis')));
        $user->setToken((new Pbkdf2PasswordHasher())->hash($token));
        $this->persistEntity(
            $this->container->get('doctrine')->getManagerForClass($adminContext->getEntity()->getFqcn()),
            $user
        );
        $this->addFlash('success', 'New token generated '.$token.' (keep it in secured area. This token will never be displayed anymore)');

        return $this->redirect(
            $adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($user->getId())
                ->generateUrl()
        );
    }
}
