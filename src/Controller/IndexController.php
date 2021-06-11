<?php
namespace App\Controller;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class IndexController
 * @package App\Controller
 */
class IndexController extends AbstractController
{

    #[Route('/', name: 'process_ui_index')]
    public function indexAction(): Response
    {
        return $this->render('index/index.html.twig');
    }
}
