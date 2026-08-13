<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Form;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Enum\ShoutStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type as FormTypes;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The moderator's edit form.
 *
 * Unlike ShoutType this IS mapped to the entity — a moderator is trusted, and
 * the whole point of the screen is to change stored fields directly.
 *
 * ipAddress and createdAt are intentionally absent: they are evidence, and a
 * moderation UI that lets you rewrite the evidence is not much of one.
 */
class ShoutAdminType extends AbstractType {

  /**
   * {@inheritdoc}
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
      ->add('authorName', FormTypes\TextType::class, [
        'label' => 'Author name',
        'required' => false,
        'help' => 'Only used for shouts posted without an account.',
        'attr' => [
          'class' => 'form-control',
          'maxlength' => 64,
        ],
      ])
      ->add('body', FormTypes\TextareaType::class, [
        'label' => 'Message',
        'attr' => [
          'class' => 'form-control',
          'rows' => 4,
        ],
        'constraints' => [
          new Assert\NotBlank(),
          new Assert\Length(
            max: Entity\Shout::MAX_BODY_LENGTH,
            maxMessage: 'Message cannot be longer than {{ limit }} characters.',
          ),
        ],
      ])
      ->add('channel', FormTypes\TextType::class, [
        'label' => 'Channel',
        'attr' => [
          'class' => 'form-control',
          'maxlength' => 64,
        ],
        'constraints' => [
          new Assert\NotBlank(
            message: 'Channel should not be blank.',
          ),
        ],
      ])
      ->add('status', FormTypes\EnumType::class, [
        'class' => ShoutStatus::class,
        'label' => 'Status',
        'choice_label' => fn(ShoutStatus $status) => $status->label(),
        'attr' => [
          'class' => 'form-select',
        ],
      ])
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Save Shout',
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
      'data_class' => Entity\Shout::class,
    ]);
  }
}
