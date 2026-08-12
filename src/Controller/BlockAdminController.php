<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Form;
use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Repository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin CRUD for blocks and the items inside them.
 *
 * Structured to mirror TaxonomyController — a parent entity with a list of
 * children — because blocks and items sit in exactly the same relationship as
 * vocabularies and terms, and there is no value in a second way of doing it.
 *
 * The class-level IsGranted is the coarse gate that keeps the whole section
 * behind one permission; the per-action checks inside the templates are what
 * decide which buttons a given administrator actually sees.
 */
#[Route('/admincp/blocks')]
#[IsGranted(Interfaces\Security\Voter\BlockVoterInterface::BLOCK_ADMINISTER, message: 'You do not have permission to administer blocks.')]
class BlockAdminController extends AbstractController {

  /**
   * Constructor.
   *
   * @param EntityManagerInterface $entityManager Persistence.
   * @param Repository\BlockRepository $blocks Block lookups.
   * @param LoggerInterface $logger Audit trail.
   * @param Security $security Used to attribute changes in the log.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly Repository\BlockRepository $blocks,
    private readonly LoggerInterface $logger,
    private readonly Security $security,
  ) {  }

  /**
   * Lists every block, enabled or not.
   *
   * @return Response The rendered list.
   */
  #[Route('/', name: 'pixiekat_symfony_helpers_block_list', methods: ['GET'])]
  public function list(): Response {
    return $this->render('@PixiekatSymfonyHelpers/block/admin/list.html.twig', [
      // Fetch-joined: the template counts items per block, which would
      // otherwise cost one extra query per row.
      'blocks' => $this->blocks->findAllWithItems(),
    ]);
  }

  /**
   * Creates a block.
   *
   * @param Request $request The current request.
   *
   * @return Response The form, or a redirect once saved.
   */
  #[Route('/add', name: 'pixiekat_symfony_helpers_block_add', methods: ['GET', 'POST'])]
  #[IsGranted(Interfaces\Security\Voter\BlockVoterInterface::BLOCK_ADD, message: 'You do not have permission to add blocks.')]
  public function add(Request $request): Response {
    $block = new Entity\Block();
    // Blocks created through the UI are unlocked by default — locking is for
    // the ones a template depends on, which is a deliberate act.
    $block->setLocked(false);

    $form = $this->createForm(Form\BlockType::class, $block);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->persist($block);
        $this->entityManager->flush();

        $this->logger->info('Block {block} created by user {user}.', [
          'block' => $block->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Block created successfully.');

        // Straight to the item list: a block with no items shows nothing, so
        // adding items is almost always the next thing you want to do.
        return $this->redirectToRoute('pixiekat_symfony_helpers_block_item_list', ['block' => $block->getId()]);
      }
      catch (\Exception $e) {
        $this->logger->error('Error creating block: {message}', ['message' => $e->getMessage()]);
        $this->addFlash('error', 'An error occurred while creating the block.');
      }
    }

    return $this->render('@PixiekatSymfonyHelpers/block/admin/add.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  /**
   * Edits a block.
   *
   * @param Entity\Block $block The block being edited.
   * @param Request $request The current request.
   *
   * @return Response The form, or a redirect once saved.
   */
  #[Route('/{block}/edit', name: 'pixiekat_symfony_helpers_block_edit', methods: ['GET', 'POST'])]
  #[IsGranted(Interfaces\Security\Voter\BlockVoterInterface::BLOCK_EDIT, subject: 'block', message: 'You do not have permission to edit this block.')]
  public function edit(
    #[MapEntity(mapping: ['block' => 'id'])] Entity\Block $block,
    Request $request,
  ): Response {
    $form = $this->createForm(Form\BlockType::class, $block);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->flush();

        $this->logger->info('Block {block} edited by user {user}.', [
          'block' => $block->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Block saved successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error saving block: {message}', ['message' => $e->getMessage()]);
        $this->addFlash('error', 'An error occurred while saving the block.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_block_list');
    }

    return $this->render('@PixiekatSymfonyHelpers/block/admin/edit.html.twig', [
      'form' => $form->createView(),
      'block' => $block,
    ]);
  }

  /**
   * Deletes a block, after confirmation.
   *
   * @param Entity\Block $block The block being deleted.
   * @param Request $request The current request.
   *
   * @return Response The confirmation form, or a redirect once deleted.
   */
  #[Route('/{block}/delete', name: 'pixiekat_symfony_helpers_block_delete', methods: ['GET', 'POST', 'DELETE'])]
  #[IsGranted(Interfaces\Security\Voter\BlockVoterInterface::BLOCK_DELETE, subject: 'block', message: 'You do not have permission to delete this block.')]
  public function delete(
    #[MapEntity(mapping: ['block' => 'id'])] Entity\Block $block,
    Request $request,
  ): Response {
    $form = $this->createForm(Form\ConfirmDeleteType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->has('cancel') && $form->get('cancel')->isClicked()) {
      return $this->redirectToRoute('pixiekat_symfony_helpers_block_list');
    }

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $name = $block->getName();
        // Items go with it: the association is orphanRemoval and the foreign
        // key is ON DELETE CASCADE, so this is one call, not a loop.
        $this->entityManager->remove($block);
        $this->entityManager->flush();

        $this->logger->info('Block {block} deleted by user {user}.', [
          'block' => $name,
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Block deleted successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error deleting block: {message}', ['message' => $e->getMessage()]);
        $this->addFlash('error', 'An error occurred while deleting the block.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_block_list');
    }

    return $this->render('@PixiekatSymfonyHelpers/block/admin/delete.html.twig', [
      'form' => $form->createView(),
      'block' => $block,
    ]);
  }

  /**
   * Lists the items in a block.
   *
   * @param Entity\Block $block The owning block.
   *
   * @return Response The rendered list.
   */
  #[Route('/{block}/items', name: 'pixiekat_symfony_helpers_block_item_list', methods: ['GET'])]
  public function itemList(#[MapEntity(mapping: ['block' => 'id'])] Entity\Block $block): Response {
    return $this->render('@PixiekatSymfonyHelpers/block/admin/item_list.html.twig', [
      'block' => $block,
      'items' => $block->getItems(),
    ]);
  }

  /**
   * Adds an item to a block.
   *
   * @param Entity\Block $block The owning block.
   * @param Request $request The current request.
   *
   * @return Response The form, or a redirect once saved.
   */
  #[Route('/{block}/items/add', name: 'pixiekat_symfony_helpers_block_item_add', methods: ['GET', 'POST'])]
  #[IsGranted(Interfaces\Security\Voter\BlockVoterInterface::BLOCK_ITEM_ADD, message: 'You do not have permission to add block items.')]
  public function itemAdd(
    #[MapEntity(mapping: ['block' => 'id'])] Entity\Block $block,
    Request $request,
  ): Response {
    $item = new Entity\BlockItem();
    $item->setWeight(0);
    // addItem() rather than setBlock(): it keeps both sides of the association
    // in sync, so the in-memory block is correct too, not just the database.
    $block->addItem($item);

    $form = $this->createForm(Form\BlockItemType::class, $item);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        $this->logger->info('Item {item} added to block {block} by user {user}.', [
          'item' => $item->getName(),
          'block' => $block->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Item added successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error adding block item: {message}', ['message' => $e->getMessage()]);
        $this->addFlash('error', 'An error occurred while adding the item.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_block_item_list', ['block' => $block->getId()]);
    }

    return $this->render('@PixiekatSymfonyHelpers/block/admin/item_add.html.twig', [
      'form' => $form->createView(),
      'block' => $block,
    ]);
  }

  /**
   * Edits an item.
   *
   * @param Entity\Block $block The owning block.
   * @param Entity\BlockItem $item The item being edited.
   * @param Request $request The current request.
   *
   * @return Response The form, or a redirect once saved.
   */
  #[Route('/{block}/items/{item}/edit', name: 'pixiekat_symfony_helpers_block_item_edit', methods: ['GET', 'POST'])]
  #[IsGranted(Interfaces\Security\Voter\BlockVoterInterface::BLOCK_ITEM_EDIT, subject: 'item', message: 'You do not have permission to edit this item.')]
  public function itemEdit(
    #[MapEntity(mapping: ['block' => 'id'])] Entity\Block $block,
    #[MapEntity(mapping: ['item' => 'id'])] Entity\BlockItem $item,
    Request $request,
  ): Response {
    $form = $this->createForm(Form\BlockItemType::class, $item);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->flush();

        $this->logger->info('Item {item} in block {block} edited by user {user}.', [
          'item' => $item->getName(),
          'block' => $block->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Item saved successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error saving block item: {message}', ['message' => $e->getMessage()]);
        $this->addFlash('error', 'An error occurred while saving the item.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_block_item_list', ['block' => $block->getId()]);
    }

    return $this->render('@PixiekatSymfonyHelpers/block/admin/item_edit.html.twig', [
      'form' => $form->createView(),
      'block' => $block,
      'item' => $item,
    ]);
  }

  /**
   * Deletes an item, after confirmation.
   *
   * @param Entity\Block $block The owning block.
   * @param Entity\BlockItem $item The item being deleted.
   * @param Request $request The current request.
   *
   * @return Response The confirmation form, or a redirect once deleted.
   */
  #[Route('/{block}/items/{item}/delete', name: 'pixiekat_symfony_helpers_block_item_delete', methods: ['GET', 'POST', 'DELETE'])]
  #[IsGranted(Interfaces\Security\Voter\BlockVoterInterface::BLOCK_ITEM_DELETE, subject: 'item', message: 'You do not have permission to delete this item.')]
  public function itemDelete(
    #[MapEntity(mapping: ['block' => 'id'])] Entity\Block $block,
    #[MapEntity(mapping: ['item' => 'id'])] Entity\BlockItem $item,
    Request $request,
  ): Response {
    $form = $this->createForm(Form\ConfirmDeleteType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->has('cancel') && $form->get('cancel')->isClicked()) {
      return $this->redirectToRoute('pixiekat_symfony_helpers_block_item_list', ['block' => $block->getId()]);
    }

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $name = $item->getName();
        $this->entityManager->remove($item);
        $this->entityManager->flush();

        $this->logger->info('Item {item} deleted from block {block} by user {user}.', [
          'item' => $name,
          'block' => $block->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Item deleted successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error deleting block item: {message}', ['message' => $e->getMessage()]);
        $this->addFlash('error', 'An error occurred while deleting the item.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_block_item_list', ['block' => $block->getId()]);
    }

    return $this->render('@PixiekatSymfonyHelpers/block/admin/item_delete.html.twig', [
      'form' => $form->createView(),
      'block' => $block,
      'item' => $item,
    ]);
  }
}
