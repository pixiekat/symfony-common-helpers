<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserLoginType extends AbstractType {
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    $builder
      ->add('_username', FormType\EmailType::class, [
        'label' => 'Username',
        'required' => true,
        'attr' => [
            'class' => 'form-control',              // Bootstrap floating labels need this
            'placeholder' => 'Enter Username',      // and this
            'autocomplete' => 'username',           // lets password managers autofill
        ],
        'row_attr' => ['class' => 'form-floating mb-3'],
        ])
      ->add('_password', FormType\PasswordType::class, [
        'label' => 'Password',
        'attr' => [
            'class' => 'form-control',
            'placeholder' => 'Enter Password',
            'autocomplete' => 'current-password',
        ],
        'required' => true,
        'row_attr' => [
          'class' => 'form-floating mb-3',
        ],
      ])
      ->add('submit', FormType\SubmitType::class, [
        'label' => 'Log In',
        'attr' => [
          'class' => 'btn btn-primary btn-lg btn-center',
        ],
        'row_attr' => [
          'class' => 'text-center',
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
