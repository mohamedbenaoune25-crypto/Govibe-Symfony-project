<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\ActiviteSession;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class ActiviteSessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'label'  => false,
                'widget' => 'single_text',
                'attr'   => ['min' => (new \DateTime())->format('Y-m-d')],
                'constraints' => [
                    new NotBlank(['message' => 'La date est obligatoire.']),
                    new GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date de la session ne peut pas être dans le passé.'
                    ]),
                ],
            ])
            ->add('heure', TimeType::class, [
                'label'  => false,
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(['message' => "L'heure est obligatoire."]),
                ],
            ])
            ->add('capacite', IntegerType::class, [
                'label' => false,
                'attr'  => ['min' => 1],
                'constraints' => [
                    new NotBlank(['message' => 'La capacité est obligatoire.']),
                    new Positive(['message' => 'La capacité doit être positive.']),
                ],
            ])
            ->add('nbrPlacesRestant', IntegerType::class, [
                'label' => false,
                'attr'  => ['min' => 0],
                'constraints' => [
                    new NotBlank(['message' => 'Le nombre de places restantes est obligatoire.']),
                    new GreaterThanOrEqual(['value' => 0, 'message' => 'Les places restantes ne peuvent pas être négatives.']),
                ],
            ])
            ->add('activite', EntityType::class, [
                'label'        => false,
                'class'        => Activite::class,
                'choice_label' => 'name',
                'placeholder'  => '-- Choisir une activité --',
                'constraints'  => [
                    new NotBlank(['message' => "L'activité est obligatoire."]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActiviteSession::class,
        ]);
    }
}
