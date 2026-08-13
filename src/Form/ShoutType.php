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
 * The public "say something" form.
 *
 * Unmapped (data_class => null) on purpose: the form collects a message and
 * maybe a name, and ShoutboxManager decides what becomes of them. Binding it
 * straight to the Shout entity would let a crafted request set `status` or
 * `ipAddress`, which is exactly the sort of thing form-to-entity mapping makes
 * easy to do by accident.
 */
class ShoutType extends AbstractType {

  /**
   * {@inheritdoc}
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {

    // Only shown to visitors who are not logged in. For everyone else the
    // account IS the identity, so a name field would just be a lie waiting to
    // be told.
    if ($options['include_author_name']) {
      $builder->add('authorName', FormTypes\TextType::class, [
        'label' => 'Your name',
        'required' => false,
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'Anonymous',
          'maxlength' => 64,
          // Helps password managers and mobile keyboards do the right thing,
          // and lets a returning visitor autofill rather than retype.
          'autocomplete' => 'nickname',
        ],
        'constraints' => [
          new Assert\Length(
            max: 64,
            maxMessage: 'Name cannot be longer than {{ limit }} characters.',
          ),
        ],
      ]);
    }

    $builder
      ->add('body', FormTypes\TextareaType::class, [
        'label' => 'Message',
        'attr' => [
          'class' => 'form-control',
          'rows' => 2,
          'maxlength' => Entity\Shout::MAX_BODY_LENGTH,
          'placeholder' => 'Say something…',
        ],
        'constraints' => [
          new Assert\NotBlank(
            message: 'Say something first!',
          ),
          new Assert\Length(
            max: Entity\Shout::MAX_BODY_LENGTH,
            maxMessage: 'Keep it under {{ limit }} characters.',
          ),
        ],
      ])
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Shout',
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
      'data_class' => null,
      'include_author_name' => true,
      'channel' => Entity\Shout::DEFAULT_CHANNEL,
    ]);

    $resolver->setAllowedTypes('include_author_name', 'bool');
    $resolver->setAllowedTypes('channel', 'string');
  }
}
