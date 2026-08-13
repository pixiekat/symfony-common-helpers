<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Form;

use Pixiekat\SymfonyHelpers\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type as FormTypes;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Create/edit form for a Block.
 */
class BlockType extends AbstractType {

  /**
   * {@inheritdoc}
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
      ->add('name', FormTypes\TextType::class, [
        'label' => 'Machine name',
        'help' => 'The handle you pass to place_block(), e.g. social_links. Renaming it breaks every template that uses it.',
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'social_links',
        ],
        'constraints' => [
          new Assert\NotBlank(),
          new Assert\Length(
            max: 255,
            maxMessage: 'Machine name cannot be longer than {{ limit }} characters.',
          ),
          // Enforced here rather than left to convention: the name ends up in
          // CSS class names and Twig calls, so anything outside this set turns
          // into a rendering bug a long way from where it was typed.
          new Assert\Regex(
            pattern: '/^[a-z][a-z0-9_]*$/',
            message: 'Machine name must be lowercase and may contain only letters, numbers and underscores, starting with a letter.',
          )
        ],
      ])
      ->add('label', FormTypes\TextType::class, [
        'label' => 'Label',
        'required' => false,
        'help' => 'The heading shown above the block. Leave blank for no heading.',
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'Socials',
        ],
        'constraints' => [
          new Assert\Length(
            max: 255,
            maxMessage: 'Label cannot be longer than {{ limit }} characters.',
          ),
        ],
      ])
      ->add('description', FormTypes\TextareaType::class, [
        'label' => 'Description',
        'required' => false,
        'help' => 'An admin-only note about what this block is for. Never rendered publicly.',
        'attr' => [
          'class' => 'form-control',
          'rows' => 2,
        ],
      ])
      ->add('body', FormTypes\TextareaType::class, [
        'label' => 'Body',
        'required' => false,
        'help' => 'Optional markup, rendered before the items. Treated as trusted HTML.',
        'attr' => [
          'class' => 'form-control',
          'rows' => 6,
        ],
      ])
      ->add('template', FormTypes\TextType::class, [
        'label' => 'Template override',
        'required' => false,
        'help' => 'A Twig path to use instead of the default block template. Leave blank for the default.',
        'attr' => [
          'class' => 'form-control',
          'placeholder' => '_partials/_social-links.html.twig',
        ],
        'constraints' => [
          new Assert\Length(
            max: 255,
            maxMessage: 'Template override cannot be longer than {{ limit }} characters.',
          ),
        ],
      ])
      ->add('weight', FormTypes\IntegerType::class, [
        'label' => 'Weight',
        'help' => 'Lower numbers sort first, for templates that loop over several blocks.',
        'attr' => [
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\NotNull(),
          new Assert\Range(
            min: -50,
            max: 50,
            notInRangeMessage: 'Weight must be between {{ min }} and {{ max }}.',
          )
        ],
      ])
      ->add('enabled', FormTypes\CheckboxType::class, [
        'label' => 'Enabled',
        'required' => false,
        'help' => 'A disabled block renders as nothing, without removing it.',
        'attr' => [
          'class' => 'form-check-input',
        ],
      ])
      ->add('locked', FormTypes\CheckboxType::class, [
        'label' => 'Locked',
        'required' => false,
        'help' => 'Locked blocks cannot be deleted from this screen.',
        'attr' => [
          'class' => 'form-check-input',
        ],
      ])
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Save Block',
        'attr' => [
          'class' => 'btn btn-primary',
        ],
      ])
    ;
  }

  /**
   * {@inheritdoc}
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'data_class' => Entity\Block::class,
    ]);
  }
}
