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
 * Create/edit form for one item inside a Block.
 *
 * The `flags` bag is not exposed here. It holds template-specific keys whose
 * meaning depends on which template renders the block, so a generic key/value
 * editor would be a good way to produce silently broken markup. Set them in
 * code or a fixture where the intent is visible:
 *
 *   $item->setFlag('wrapper_label', 'Made with');
 */
class BlockItemType extends AbstractType {

  /**
   * {@inheritdoc}
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
      ->add('name', FormTypes\TextType::class, [
        'label' => 'Machine name',
        'help' => 'Unique within this block, e.g. bluesky_personal. Used to build the item\'s CSS class.',
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'bluesky_personal',
        ],
        'constraints' => [
          new Assert\NotBlank(),
          new Assert\Length(
            max: 255,
            maxMessage: 'Machine name cannot be longer than {{ limit }} characters.',
          ),
          new Assert\Regex(
            pattern: '/^[a-z][a-z0-9_]*$/',
            message: 'Machine name must be lowercase and may contain only letters, numbers and underscores, starting with a letter.',
          ),
        ],
      ])
      ->add('label', FormTypes\TextType::class, [
        'label' => 'Label',
        'required' => false,
        'help' => 'The visible text. Falls back to the URL if left blank.',
        'attr' => [
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\Length(
            max: 255,
            maxMessage: 'Label cannot be longer than {{ limit }} characters.',
          ),
        ],
      ])
      ->add('wrapperLabel', FormTypes\TextType::class, [
        'label' => 'Wrapper label',
        'required' => false,
        'help' => 'Optional text to display above the item.',
        'attr' => [
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\Length(
            max: 255,
            maxMessage: 'Wrapper label cannot be longer than {{ limit }} characters.',
          ),
        ],
      ])
      ->add('url', FormTypes\UrlType::class, [
        'label' => 'URL',
        'required' => false,
        'help' => 'Leave blank for a text-only entry — it will render as a span rather than a dead link.',
        // Symfony 7+ no longer silently prepends a scheme; being explicit here
        // avoids a surprise where a typed "example.com" becomes a relative link.
        'default_protocol' => null,
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'https://',
        ],
        'constraints' => [
          new Assert\Length(
            max: 512,
            maxMessage: 'URL cannot be longer than {{ limit }} characters.',
          ),
          new Assert\Url(
            message: 'That does not look like a valid URL.',
            requireTld: true,
          ),
        ],
      ])
      ->add('icon', FormTypes\TextType::class, [
        'label' => 'Icon',
        'required' => false,
        'help' => 'An icon identifier your template understands — a font codepoint, an SVG sprite id, a CSS class.',
        'attr' => [
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\Length(
            max: 255,
            maxMessage: 'Icon cannot be longer than {{ limit }} characters.',
          ),
        ],
      ])
      ->add('weight', FormTypes\IntegerType::class, [
        'label' => 'Weight',
        'help' => 'Lower numbers appear first.',
        'attr' => [
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\NotNull(),
          new Assert\Range(
            min: -50,
            max: 50,
            notInRangeMessage: 'Weight must be between {{ min }} and {{ max }}.',
          ),
        ],
      ])
      ->add('enabled', FormTypes\CheckboxType::class, [
        'label' => 'Enabled',
        'required' => false,
        'help' => 'Lets you retire an item without losing it.',
        'attr' => [
          'class' => 'form-check-input',
        ],
      ])
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Save Item',
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
      'data_class' => Entity\BlockItem::class,
    ]);
  }
}
