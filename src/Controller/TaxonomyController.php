<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Form;
use Pixiekat\SymfonyHelpers\Interfaces;
use Pixiekat\SymfonyHelpers\Repository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/taxonomy')]
#[IsGranted(Interfaces\Security\Voter\TaxonomyVoterInterface::TAXONOMY_ADMINISTER, message: 'You do not have permission to administer taxonomy.')]
class TaxonomyController extends AbstractController {

  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly LoggerInterface $logger,
    private readonly Security $security,
  ) {}

  #[Route('/', name: 'pixiekat_symfony_helpers_taxonomy_index', methods: ['GET'])]
  #[Route('/vocabulary', name: 'pixiekat_symfony_helpers_taxonomy_vocabulary_list', methods: ['GET'])]
  public function vocabulary_list(): Response {
    $vocabularies = $this->entityManager->getRepository(Entity\Vocabulary::class)->findAllSortedByLabel();
    return $this->render('@PixiekatSymfonyHelpers/taxonomy/vocabulary_list.html.twig', [
      'vocabularies' => $vocabularies ?? [],
    ]);
  }

  #[Route('/vocabulary/add', name: 'pixiekat_symfony_helpers_taxonomy_vocabulary_add', methods: ['GET', 'PUT', 'POST'])]
  public function vocabulary_add(Request $request): Response {
    $vocabulary = new Entity\Vocabulary;
    $form = $this->createForm(Form\VocabularyType::class, $vocabulary, []);
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      dump("form submitted");
    }
    if ($form->isSubmitted() && $form->isValid()) {
      dump("form is valid");
      try {
        dump("trying to create vocabulary");
        $labelName = strtolower($vocabulary->getName());
        $labelName = preg_replace('/[^a-z0-9_]+/', '_', $labelName);
        $labelName = preg_replace('/_+/', '_', $labelName);
        $vocabulary->setLabelName($labelName);

        $this->entityManager->persist($vocabulary);
        $this->entityManager->flush();

        $this->logger->info('Vocabulary {vocabulary} created by user {user}.', [
          'vocabulary' => $vocabulary->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Vocabulary created successfully.');
      }
      catch (\Exception $e) {
        dump("error creating vocabulary: " . $e->getMessage());
        $this->logger->error('Error creating vocabulary: {message}', [
          'message' => $e->getMessage(),
        ]);
        $this->addFlash('error', 'An error occurred while creating the vocabulary.');
      }
      dump("redirecting to term list");
      return $this->redirectToRoute('pixiekat_symfony_helpers_taxonomy_vocabulary_list', ['vocabulary' => $vocabulary->getID()]);
    }

    return $this->render('@PixiekatSymfonyHelpers/taxonomy/vocabulary_add.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  #[Route('/vocabulary/{vocabulary}', name: 'pixiekat_symfony_helpers_taxonomy_vocabulary_edit', methods: ['GET', 'PUT', 'POST'])]
  public function vocabulary_edit(
    #[MapEntity(mapping: ['vocabulary' => 'id'])] Entity\Vocabulary $vocabulary,
    Request $request,
  ): Response {
    $form = $this->createForm(Form\VocabularyType::class, $vocabulary, []);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $labelName = strtolower($vocabulary->getName());
        $labelName = preg_replace('/[^a-z0-9_]+/', '_', $labelName);
        $labelName = preg_replace('/_+/', '_', $labelName);
        $vocabulary->setLabelName($labelName);

        $this->entityManager->persist($vocabulary);
        $this->entityManager->flush();

        $this->logger->info('Vocabulary {vocabulary} created by user {user}.', [
          'vocabulary' => $vocabulary->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Vocabulary created successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error creating vocabulary: {message}', [
          'message' => $e->getMessage(),
        ]);
        $this->addFlash('error', 'An error occurred while creating the vocabulary.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_taxonomy_vocabulary_list', ['vocabulary' => $vocabulary->getID()]);
    }

    return $this->render('@PixiekatSymfonyHelpers/taxonomy/vocabulary_edit.html.twig', [
      'form' => $form->createView(),
      'vocabulary' => $vocabulary,
    ]);
  }

  #[Route('/vocabulary/{vocabulary}/terms', name: 'pixiekat_symfony_helpers_taxonomy_term_list', methods: ['GET'])]
  public function term_list(#[MapEntity(mapping: ['vocabulary' => 'id'])] Entity\Vocabulary $vocabulary): Response {
    $terms = $vocabulary->getTerms() ?? [];
    return $this->render('@PixiekatSymfonyHelpers/taxonomy/term_list.html.twig', [
      'terms' => $terms,
      'vocabulary' => $vocabulary,
    ]);
  }

  #[Route('/vocabulary/{vocabulary}/term/add', name: 'pixiekat_symfony_helpers_taxonomy_term_add', methods: ['GET', 'PUT', 'POST'])]
  public function term_add(#[MapEntity(mapping: ['vocabulary' => 'id'])] Entity\Vocabulary $vocabulary, Request $request): Response {
    $term = new Entity\Term;
    $term->setVocabulary($vocabulary);
    $term->setWeight(0);
    $form = $this->createForm(Form\TermType::class, $term, []);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->persist($term);
        $this->entityManager->flush();

        $this->logger->info('Term {term} added to vocabulary {vocabulary} by user {user}.', [
          'term' => $term->getName(),
          'vocabulary' => $vocabulary->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Term added successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error adding term to vocabulary: {message}', [
          'message' => $e->getMessage(),
        ]);
        $this->addFlash('error', 'An error occurred while adding the term.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_taxonomy_term_list', ['vocabulary' => $vocabulary->getID()]);
    }

    return $this->render('@PixiekatSymfonyHelpers/taxonomy/term_add.html.twig', [
      'form' => $form->createView(),
      'vocabulary' => $vocabulary,
    ]);
  }

  #[Route('/vocabulary/{vocabulary}/term/{term}', name: 'pixiekat_symfony_helpers_taxonomy_term_edit', methods: ['GET', 'PUT', 'POST'])]
  public function term_edit(
    #[MapEntity(mapping: ['vocabulary' => 'id'])] Entity\Vocabulary $vocabulary,
    #[MapEntity(mapping: ['term' => 'id'])] Entity\Term $term,
    Request $request
  ): Response{
    $form = $this->createForm(Form\TermType::class, $term, []);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $this->entityManager->persist($term);
      $this->entityManager->flush();

      $this->logger->info('Term {term} in vocabulary {vocabulary} edited by user {user}.', [
        'term' => $term->getName(),
        'vocabulary' => $vocabulary->getName(),
        'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
      ]);
      $this->addFlash('success', 'Term edited successfully.');

      return $this->redirectToRoute('pixiekat_symfony_helpers_taxonomy_term_list', ['vocabulary' => $vocabulary->getID()]);
    }

    return $this->render('@PixiekatSymfonyHelpers/taxonomy/term_edit.html.twig', [
      'form' => $form->createView(),
      'term' => $term,
      'vocabulary' => $vocabulary,
    ]);
  }

  #[Route('/vocabulary/{vocabulary}/term/{term}/delete', name: 'pixiekat_symfony_helpers_taxonomy_term_delete', methods: ['GET', 'DELETE', 'POST'])]
  public function term_delete(
    #[MapEntity(mapping: ['vocabulary' => 'id'])] Entity\Vocabulary $vocabulary,
    #[MapEntity(mapping: ['term' => 'id'])] Entity\Term $term,
    Request $request
  ): Response {
    $form = $this->createForm(Form\ConfirmDeleteType::class, null, []);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->has('cancel') && $form->get('cancel')->isClicked()) {
      return $this->redirectToRoute('pixiekat_symfony_helpers_taxonomy_term_list', ['vocabulary' => $vocabulary->getID()]);
    }

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->remove($term);
        $this->entityManager->flush();

        $this->logger->info('Term {term} in vocabulary {vocabulary} deleted by user {user}.', [
          'term' => $term->getName(),
          'vocabulary' => $vocabulary->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Term deleted successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error deleting term from vocabulary: {message}', [
          'message' => $e->getMessage(),
        ]);
        $this->addFlash('error', 'An error occurred while deleting the term.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_taxonomy_term_list', ['vocabulary' => $vocabulary->getID()]);
    }

    return $this->render('@PixiekatSymfonyHelpers/taxonomy/term_delete.html.twig', [
      'form' => $form->createView(),
      'term' => $term,
      'vocabulary' => $vocabulary,
    ]);
  }

  #[Route('/vocabulary/{vocabulary}/delete', name: 'pixiekat_symfony_helpers_taxonomy_vocabulary_delete', methods: ['GET', 'DELETE', 'POST'])]
  public function vocabulary_delete(
    #[MapEntity(mapping: ['vocabulary' => 'id'])] Entity\Vocabulary $vocabulary,
    Request $request
  ): Response {
    $form = $this->createForm(Form\ConfirmDeleteType::class, null, []);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->has('cancel') && $form->get('cancel')->isClicked()) {
      return $this->redirectToRoute('pixiekat_symfony_helpers_taxonomy_vocabulary_list');
    }

    if ($form->isSubmitted() && $form->isValid()) {
      try {
        $this->entityManager->remove($vocabulary);
        $this->entityManager->flush();

        $this->logger->info('Vocabulary {vocabulary} deleted by user {user}.', [
          'vocabulary' => $vocabulary->getName(),
          'user' => $this->security->getUser()?->getUserIdentifier() ?? 'anonymous',
        ]);
        $this->addFlash('success', 'Vocabulary deleted successfully.');
      }
      catch (\Exception $e) {
        $this->logger->error('Error deleting vocabulary: {message}', [
          'message' => $e->getMessage(),
        ]);
        $this->addFlash('error', 'An error occurred while deleting the vocabulary.');
      }

      return $this->redirectToRoute('pixiekat_symfony_helpers_taxonomy_vocabulary_list');
    }

    return $this->render('@PixiekatSymfonyHelpers/taxonomy/vocabulary_delete.html.twig', [
      'form' => $form->createView(),
      'vocabulary' => $vocabulary,
    ]);
  }
}
