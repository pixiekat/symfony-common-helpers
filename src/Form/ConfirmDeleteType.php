<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Form;

use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Repository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type as FormTypes;

class ConfirmDeleteType extends AbstractType {
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Delete',
        'attr' => [
          'class' => 'btn btn-danger',
        ],
      ])
      ->add('cancel', FormTypes\SubmitType::class, [
        'label' => 'Cancel',
        'attr' => [
          'class' => 'btn btn-secondary',
          'onclick' => 'window.history.back(); return false;',
        ],
      ])
    ;
  }

  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'data_class' => null,
    ]);
  }
}
