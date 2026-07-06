<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * Single-file upload. The `file` field is unmapped: the controller reads it, copies the
 * bytes to disk and fills the Document metadata (filename / mimeType / size) itself.
 */
class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Pièce jointe',
                'mapped' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypesMessage: 'Format non autorisé (PDF, image, texte, bureautique).',
                        mimeTypes: [
                            'application/pdf',
                            'image/*',
                            'text/plain',
                            'text/csv',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ],
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // No data_class: this form only carries the unmapped file field.
        $resolver->setDefaults([]);
    }
}
