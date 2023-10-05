<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Controller\Admin\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LoginController extends AbstractController
{
    #[Route('/process/login', name: 'process_login')]
    public function __invoke(): Response
    {
        return $this->render(
            '@CleverAgeProcessUi/admin/login.html.twig',
            [
                'page_title' => 'Login',
                'target_path' => '/process',
            ]
        );
    }
}
