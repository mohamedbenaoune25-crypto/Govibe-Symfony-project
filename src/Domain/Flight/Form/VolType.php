<?php

namespace App\Domain\Flight\Form;

use App\Domain\Flight\Entity\Vol;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VolType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('flightId', TextType::class, [
                'label' => 'Numéro de Vol',
                'attr' => ['placeholder' => 'ex: AF1234']
            ])
            ->add('airline', TextType::class, [
                'label' => 'Compagnie Aérienne',
                'attr' => ['placeholder' => 'ex: Air France']
            ])
            ->add('departureAirport', TextType::class, [
                'label' => 'Aéroport de Départ',
                'attr' => ['placeholder' => 'ex: CDG, Paris']
            ])
            ->add('destination', TextType::class, [
                'label' => 'Destination',
                'attr' => ['placeholder' => 'ex: JFK, New York']
            ])
            ->add('departureTime', TimeType::class, [
                'label' => 'Heure de départ',
                'widget' => 'single_text',
            ])
            ->add('arrivalTime', TimeType::class, [
                'label' => 'Heure d\'arrivée estimée',
                'widget' => 'single_text',
            ])
            ->add('classeChaise', ChoiceType::class, [
                'label' => 'Classe du Siège',
                'choices' => [
                    'Économique' => 'Économique',
                    'Premium Économique' => 'Premium Économique',
                    'Affaires' => 'Affaires',
                    'Première' => 'Première',
                ]
            ])
            ->add('prix', IntegerType::class, [
                'label' => 'Prix (en €)',
                'attr' => ['min' => 1]
            ])
            ->add('availableSeats', IntegerType::class, [
                'label' => 'Sièges Disponibles',
                'attr' => ['min' => 0]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Détails supplémentaires...']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Vol::class,
        ]);
    }
}
