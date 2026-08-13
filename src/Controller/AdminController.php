<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use Pixiekat\SymfonyHelpers\Interfaces as PixieInterfaces;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminController extends AbstractController {

    #[IsGranted(PixieInterfaces\Security\Voter\AdminVoterInterface::ADMIN_ADMINISTER, message: 'You do not have permission to access the admin panel.')]
    #[Route('/admin/index', name: 'pixiekat_symfony_helpers_admin_index')]
    public function index(): Response {
        return $this->render('@PixiekatSymfonyHelpers/admin/admin_index.html.twig');
    }
}
