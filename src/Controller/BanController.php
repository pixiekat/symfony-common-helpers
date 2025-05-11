<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Interfaces;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/admin/ban')]
class BanController extends AbstractController {

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly Security $security,
  ) {}

  #[IsGranted(Interfaces\Security\Voter\BanVoterInterface::BAN_LIST_BANS)]
  #[Route('/', name: 'pixiekat_symfony_helpers_ban')]
  #[Route('/list', name: 'pixiekat_symfony_helpers_ban_list')]
  public function list(): Response {
    $bans = $this->entityManager->getRepository(Entity\Ban::class)->findAllActiveBans();
    return $this->render('@PixiekatSymfonyHelpers/ban/list.html.twig', [
      'bans' => $bans,
    ]);
  }
}
