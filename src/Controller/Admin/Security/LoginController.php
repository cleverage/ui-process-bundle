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

namespace CleverAge\UiProcessBundle\Controller\Admin\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LoginController extends AbstractController
{
    #[Route('/process/login', name: 'process_login')]
    public function __invoke(): Response
    {
        return $this->render(
            '@CleverAgeUiProcess/admin/login.html.twig',
            [
                'page_title' => 'Login',
                'target_path' => '/process',
            ]
        );
    }
}
