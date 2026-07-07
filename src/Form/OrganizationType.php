<?php

namespace App\Form;

use App\Entity\Organization;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrganizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Le slug est dérivé du nom dans le contrôleur ; createdAt est posé au constructeur.
        $builder
            ->add('name', TextType::class, ['label' => 'Nom de l’organisation'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organization::class,
        ]);
    }
}
