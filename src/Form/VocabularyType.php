<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Form;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Repository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type as FormTypes;

class VocabularyType extends AbstractType {
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
      ->add('label', FormTypes\TextType::class, [
        'label' => 'Label',
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'Enter vocabulary label',
        ],
        'constraints' => [
          new Assert\NotBlank([
            'message' => 'Label should not be blank.',
          ]),
          new Assert\Length([
            'max' => 255,
            'maxMessage' => 'Label cannot be longer than {{ limit }} characters.',
          ]),
        ],
      ])
      ->add('name', FormTypes\TextType::class, [
        'label' => 'Name',
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'Enter vocabulary name',
        ],
        'constraints' => [
          new Assert\NotBlank([
            'message' => 'Name should not be blank.',
          ]),
          new Assert\Length([
            'max' => 255,
            'maxMessage' => 'Name cannot be longer than {{ limit }} characters.',
          ]),
        ],
      ])
      ->add('description', FormTypes\TextareaType::class, [
        'label' => 'Description',
        'required' => false,
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'Enter vocabulary description (optional)',
          'rows' => 4,
        ],
      ])
      ->add('enabled', FormTypes\CheckboxType::class, [
        'label' => 'Enabled',
        'required' => false,
        'attr' => [
          'class' => 'form-check-input',
        ],
      ])
      ->add('locked', FormTypes\CheckboxType::class, [
        'label' => 'Locked',
        'required' => false,
        'attr' => [
          'class' => 'form-check-input',
        ],
      ])
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Save Vocabulary',
        'attr' => [
          'class' => 'btn btn-primary',
        ],
      ])
    ;
  }

  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'data_class' => Entity\Vocabulary::class,
    ]);
  }
}
