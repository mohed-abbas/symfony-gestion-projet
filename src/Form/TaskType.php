<?php

namespace App\Form;

use App\Entity\BugTask;
use App\Entity\FeatureTask;
use App\Entity\Label;
use App\Entity\Project;
use App\Entity\Sprint;
use App\Entity\StoryTask;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Dynamic task form.
 * - The `type` select drives which type-specific fields appear (bug/feature/story),
 *   rebuilt on both PRE_SET_DATA (render) and PRE_SUBMIT (submit) — 🎯 Form Events.
 * - The `assignee` and `sprint` choices are derived from the current project's members
 *   and sprints, never a global list.
 */
class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Project $project */
        $project = $options['project'];

        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'mapped' => false,
                'choices' => [
                    'Bug' => 'bug',
                    'Fonctionnalité' => 'feature',
                    'User story' => 'story',
                ],
                'data' => $options['default_type'],
                'disabled' => $options['lock_type'],
                'attr' => ['data-action' => 'change->dynamic-form#refresh'],
            ])
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => [
                    'Basse' => Task::PRIORITY_LOW,
                    'Moyenne' => Task::PRIORITY_MEDIUM,
                    'Haute' => Task::PRIORITY_HIGH,
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'À faire' => Task::STATUS_TODO,
                    'En cours' => Task::STATUS_IN_PROGRESS,
                    'En revue' => Task::STATUS_IN_REVIEW,
                    'Terminé' => Task::STATUS_DONE,
                ],
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Échéance',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('sprint', EntityType::class, [
                'label' => 'Sprint',
                'class' => Sprint::class,
                'choice_label' => 'name',
                'choices' => $project->getSprints(),
                'placeholder' => '— Backlog —',
                'required' => false,
            ])
            ->add('assignee', EntityType::class, [
                'label' => 'Assigné à',
                'class' => User::class,
                'choice_label' => 'fullName',
                'choices' => $project->getMembers(),
                'placeholder' => '— Non assigné —',
                'required' => false,
            ])
            ->add('labels', EntityType::class, [
                'label' => 'Labels',
                'class' => Label::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('save', SubmitType::class, ['label' => 'Enregistrer'])
        ;

        // Render: derive the type from the bound object (edit) or the default (new).
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $task = $event->getData();
            $type = $task instanceof Task ? $task->getType() : $options['default_type'];
            $this->addTypeFields($event->getForm(), $type);
        });

        // Submit: on edit the type is locked (read from the object); on new it comes from the select.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options): void {
            $current = $event->getForm()->getData();
            $submitted = $event->getData();
            $type = $current instanceof Task
                ? $current->getType()
                : ($submitted['type'] ?? $options['default_type']);
            $this->addTypeFields($event->getForm(), $type);
        });
    }

    /** Swap in the fields specific to the chosen task type, removing any leftover ones. */
    private function addTypeFields(FormInterface $form, string $type): void
    {
        foreach (['severity', 'stepsToReproduce', 'businessValue', 'storyPoints'] as $field) {
            if ($form->has($field)) {
                $form->remove($field);
            }
        }

        match ($type) {
            'feature' => $form->add('businessValue', TextareaType::class, [
                'label' => 'Valeur métier',
                'required' => false,
            ]),
            'story' => $form->add('storyPoints', IntegerType::class, [
                'label' => 'Story points',
                'required' => false,
            ]),
            default => $form
                ->add('severity', ChoiceType::class, [
                    'label' => 'Sévérité',
                    'choices' => [
                        'Bloquant' => BugTask::SEVERITY_BLOCKER,
                        'Majeur' => BugTask::SEVERITY_MAJOR,
                        'Mineur' => BugTask::SEVERITY_MINOR,
                    ],
                ])
                ->add('stepsToReproduce', TextareaType::class, [
                    'label' => 'Étapes de reproduction',
                    'required' => false,
                ]),
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'default_type' => 'bug',
            'lock_type' => false,
            // Instantiate the concrete subtype from the chosen type when creating.
            'empty_data' => function (FormInterface $form): Task {
                return match ($form->get('type')->getData()) {
                    'feature' => new FeatureTask(),
                    'story' => new StoryTask(),
                    default => new BugTask(),
                };
            },
        ]);
        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
        $resolver->setAllowedTypes('default_type', 'string');
        $resolver->setAllowedTypes('lock_type', 'bool');
    }
}
