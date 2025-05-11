<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use Psr\Log\LoggerInterface;
use Pixiekat\SymfonyHelpers\Form;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Interfaces;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/admin/ban')]
class BanController extends AbstractController {

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly LoggerInterface $logger,
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

  #[IsGranted(Interfaces\Security\Voter\BanVoterInterface::BAN_ADD_BAN)]
  #[Route('/add', name: 'pixiekat_symfony_helpers_ban_add')]
  public function add(Request $request): Response {
    $ban = new Entity\Ban();
    $form = $this->createForm(Form\BanForm::class, $ban);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->persist($ban);
        $this->entityManager->flush();
        $this->addFlash('success', 'Ban added successfully.');
        $this->logger->info('Ban added with ID: ' . $ban->getId());
      } catch (\Exception $e) {
        $this->logger->error('Error adding ban: ' . $e->getMessage());
        $this->addFlash('error', 'An error occurred while adding the ban.');
      }
      return $this->redirectToRoute('pixiekat_symfony_helpers_ban_list');
    }
    return $this->render('@PixiekatSymfonyHelpers/ban/add.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  #[IsGranted(Interfaces\Security\Voter\BanVoterInterface::BAN_EDIT_BAN, 'ban')]
  #[Route('/edit/{id}', name: 'pixiekat_symfony_helpers_ban_edit')]
  public function edit(Entity\Ban $ban): Response {
    $form = $this->createForm(Form\BanForm::class, $ban);

    $form->handleRequest($this->request);

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->flush();
        $this->addFlash('success', 'Ban updated successfully.');
        $this->logger->info('Ban updated with ID: ' . $ban->getId());
      } catch (\Exception $e) {
        $this->logger->error('Error updating ban: ' . $e->getMessage());
        $this->addFlash('error', 'An error occurred while updating the ban.');
      }
      return $this->redirectToRoute('pixiekat_symfony_helpers_ban_list');
    }
    return $this->render('@PixiekatSymfonyHelpers/ban/edit.html.twig', [
      'ban' => $ban,
      'form' => $form->createView(),
      'csrf_token' => $this->get('security.csrf.token_manager')->getToken('ban_edit')->getValue(),
    ]);
  }

  #[IsGranted(Interfaces\Security\Voter\BanVoterInterface::BAN_REMOVE_BAN, 'ban')]
  #[Route('/remove/{id}', name: 'pixiekat_symfony_helpers_ban_remove')]
  public function remove(Entity\Ban $ban): Response {
    try {
      $id = $ban->getId();
      $this->entityManager->remove($ban);
      $this->entityManager->flush();
      $this->addFlash('success', 'Ban removed successfully.');
      $this->logger->info('Ban removed with ID: ' . $id);
    } catch (\Exception $e) {
      $this->logger->error('Error removing ban: ' . $e->getMessage());
      $this->addFlash('error', 'An error occurred while removing the ban.');
    }
    return $this->redirectToRoute('pixiekat_symfony_helpers_ban_list');
  }
}
