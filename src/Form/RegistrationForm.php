<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Form;

use App\Entity as AppEntity;
use Pixiekat\SymfonyHelpers\Entity;
use Pixiekat\SymfonyHelpers\Repository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type as FormTypes;

final class RegistrationForm extends AbstractType {
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
      ->add('emailAddress', FormTypes\EmailType::class, [
        'attr' => [
          'placeholder' => 'Enter email address',
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\NotBlank(),
          new Assert\Email(),
        ],
        'label' => 'Email Address',
      ])
      ->add('password', FormTypes\PasswordType::class, [
        'attr' => [
          'placeholder' => 'Enter password',
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\NotBlank(),
          new Assert\Length(['min' => 6]),
        ],
        'label' => 'Password',
      ])
      ->add('firstName', FormTypes\TextType::class, [
        'attr' => [
          'placeholder' => 'Enter first name',
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\NotBlank(),
          new Assert\Length(['max' => 255]),
        ],
        'label' => 'First Name',
      ])
      ->add('lastName', FormTypes\TextType::class, [
        'attr' => [
          'placeholder' => 'Enter last name',
          'class' => 'form-control',
        ],
        'constraints' => [
          new Assert\NotBlank(),
          new Assert\Length(['max' => 255]),
        ],
        'label' => 'Last Name',
      ])
      ->add('submit', FormTypes\SubmitType::class, [
        'label' => 'Register Account',
        'attr' => [
          'class' => 'btn btn-primary',
        ],
      ])
      ->add('cancel', FormTypes\SubmitType::class, [
        'label' => 'Cancel',
        'attr' => [
          'class' => 'btn btn-link',
        ],
      ])
    ;
  }

  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'data_class' => AppEntity\User::class,
    ]);
  }
}
