<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Controller\Admin\Process;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessUiBundle\Entity\User;
use CleverAge\ProcessUiBundle\Form\Type\LaunchType;
use CleverAge\ProcessUiBundle\Manager\ProcessConfigurationsManager;
use CleverAge\ProcessUiBundle\Message\ProcessExecuteMessage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/process/launch',
    name: 'process_launch',
    requirements: ['process' => '\w+'],
    methods: ['POST', 'GET']
)]
#[IsGranted('ROLE_USER')]
class LaunchAction extends AbstractController
{
    public function __invoke(
        RequestStack $requestStack,
        MessageBusInterface $messageBus,
        #[Autowire(param: 'upload_directory')] string $uploadDirectory,
        #[ValueResolver('process')] ProcessConfiguration $processConfiguration,
        ProcessConfigurationsManager $configurationsManager,
        AdminContext $context,
    ): Response {
        $uiOptions = $configurationsManager->getUiOptions($requestStack->getMainRequest()->get('process'));
        $form = $this->createForm(
            LaunchType::class,
            null,
            [
                'constraints' => $uiOptions['constraints'],
                'process_code' => $requestStack->getMainRequest()?->get('process'),
            ]
        );
        if (false === $form->isSubmitted()) {
            $default = $uiOptions['default'];
            if (false === $form->get('input')->getConfig()->getType()->getInnerType() instanceof TextType
                && isset($default['input'])
            ) {
                unset($default['input']);
            }
            $form->setData($default);
        }
        $form->handleRequest($requestStack->getMainRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var mixed|UploadedFile $file */
            $input = $form->get('input')->getData();
            if ($input instanceof UploadedFile) {
                $filename = sprintf('%s/%s.%s', $uploadDirectory, Uuid::v4(), $input->getClientOriginalExtension());
                (new Filesystem())->dumpFile($filename, $input->getContent());
                $input = $filename;
            }

            $message = new ProcessExecuteMessage(
                $form->getConfig()->getOption('process_code'),
                $input,
                array_merge(
                    ['execution_user' => $this->getUser()?->getEmail()],
                    $form->get('context')->getData()
                )
            );
            $messageBus->dispatch($message);
            $this->addFlash(
                'success',
                'Process has been added to queue. It will start as soon as possible'
            );

            return $this->redirectToRoute('process', ['routeName' => 'process_list']);
        }
        $context->getAssets()->addJsAsset(Asset::fromEasyAdminAssetPackage('field-collection.js')->getAsDto());

        return $this->render(
            '@CleverAgeProcessUi/admin/process/launch.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    protected function getUser(): ?User
    {
        /** @var User $user */
        $user = parent::getUser();

        return $user;
    }
}
