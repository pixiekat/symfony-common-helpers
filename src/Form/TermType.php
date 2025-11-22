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

class TermType extends AbstractType {
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
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
      ->add('weight', FormTypes\IntegerType::class, [
        'label' => 'Weight',
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'Enter weight (lower numbers appear first)',
        ],
        'constraints' => [
          new Assert\NotBlank([
            'message' => 'Weight should not be blank.',
          ]),
          new Assert\Type([
            'type' => 'integer',
            'message' => 'Weight must be an integer.',
          ]),
          new Assert\Range([
            'min' => -50,
            'max' => 50,
            'notInRangeMessage' => 'Weight must be between {{ min }} and {{ max }}.',
          ]),
        ],
      ])
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Save Term',
        'attr' => [
          'class' => 'btn btn-primary',
        ],
      ])
    ;
  }

  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'data_class' => Entity\Term::class,
    ]);
  }
}
