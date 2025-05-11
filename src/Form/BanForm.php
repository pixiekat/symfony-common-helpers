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

class BanForm extends AbstractType {
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
      ->add('ipAddress', FormTypes\TextType::class, [
        'attr' => [
          'placeholder' => 'Enter IP address',
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\NotBlank(),
          new Assert\Ip(),
        ],
        'label' => 'IP Address',
      ])
      ->add('expiresAt', FormTypes\DateTimeType::class, [
        'widget' => 'single_text',
        'html5' => true,
        'attr' => [
          'class' => 'form-control',
          'placeholder' => 'Select expiration date',
        ],
        'label' => 'Expiration Date',
        'required' => false,
      ])
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Filter',
        'attr' => [
          'class' => 'btn btn-primary',
        ],
      ])
    ;
  }

  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'data_class' => Entity\Ban::class,
    ]);
  }
}
