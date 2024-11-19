<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Controller\Admin;

use CleverAge\ProcessUiBundle\Entity\User;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class UserCrudController extends AbstractCrudController
{
    /** @param array<string, string> $roles */
    public function __construct(private array $roles)
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
            ->setFormTypeOptions(['multiple' => true, 'expanded' => true]);
        yield FormField::addTab('Intl.')->setIcon('fa fa-flag');
        yield TimezoneField::new('timezone');
        yield LocaleField::new('locale');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setIcon('fa fa-plus')
                    ->setLabel(false)
                    ->addCssClass('');
            })->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-edit')
                    ->setLabel(false)
                    ->addCssClass('text-warning');
            })->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash-o')
                    ->setLabel(false)
                    ->addCssClass('');
            })->update(Crud::PAGE_INDEX, Action::BATCH_DELETE, function (Action $action) {
                return $action->setLabel('Delete')
                    ->addCssClass('');
            })->add(Crud::PAGE_EDIT, Action::new('generateToken')->linkToCrudAction('generateToken'));
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
                ->setController(UserCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($user->getId())
                ->generateUrl()
        );
    }
}
