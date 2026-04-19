<?php
namespace App\Form;

use App\Entity\Activite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'activité',
                'attr' => [
                    'placeholder' => 'Ex: Parachutisme', 
                    'class' => 'form-control rounded-pill',
                    'minlength' => 3,
                    'maxlength' => 150
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Détailler l\'activité...', 
                    'class' => 'form-control rounded-4', 
                    'rows' => 4,
                    'minlength' => 10
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'activité',
                'choices'  => [
                    'Sport' => 'Sport',
                    'Culture' => 'Culture',
                    'Aventure' => 'Aventure',
                    'Détente' => 'Detente',
                ],
                'attr' => ['class' => 'form-select rounded-pill']
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'attr' => ['placeholder' => 'Ex: Tunis, Marsa...', 'class' => 'form-control rounded-pill']
            ])
            ->add('latitude', TextType::class, [
                'label' => 'Latitude',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 36.8065', 'class' => 'form-control rounded-pill']
            ])
            ->add('longitude', TextType::class, [
                'label' => 'Longitude',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 10.1815', 'class' => 'form-control rounded-pill']
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix (TND)',
                'currency' => 'TND',
                'attr' => [
                    'class' => 'form-control rounded-pill',
                    'min' => 0,
                    'step' => '0.01'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices'  => [
                    'Confirmé' => 'Confirmed',
                    'En attente' => 'Pending',
                    'Annulé' => 'Cancelled',
                ],
                'attr' => ['class' => 'form-select rounded-pill']
            ])
            ->add('ambiance', TextType::class, [
                'label' => 'Ambiance',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Relaxante, Festif...', 'class' => 'form-control rounded-pill']
            ])
            ->add('bestMoment', ChoiceType::class, [
                'label' => 'Meilleur moment',
                'choices' => [
                    'Matin' => 'morning',
                    'Après-midi' => 'afternoon',
                    'Soirée' => 'evening',
                    'Nuit' => 'night'
                ],
                'attr' => ['class' => 'form-select rounded-pill']
            ])
            ->add('weatherType', ChoiceType::class, [
                'label' => 'Type de météo',
                'choices' => [
                    'Ensoleillé' => 'sunny',
                    'Pluvieux' => 'rainy',
                    'Indifférent' => 'both'
                ],
                'attr' => ['class' => 'form-select rounded-pill']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activite::class,
        ]);
    }
}
