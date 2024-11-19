<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Controller\Admin\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LogoutController extends AbstractController
{
    #[Route('/process/logout', name: 'process_logout')]
    public function __invoke(Security $security): Response
    {
        $security->logout();

        return $this->redirectToRoute('process_login');
    }
}
