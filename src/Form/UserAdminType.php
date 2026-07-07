<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Édition admin restreinte : rôle(s) + statut d'activation. Le reste du profil n'est pas modifiable ici.
class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => [
                    'Membre (ROLE_USER)' => 'ROLE_USER',
                    'Chef de projet (ROLE_MANAGER)' => 'ROLE_MANAGER',
                    'Administrateur (ROLE_ADMIN)' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
