<?php

namespace App\Domain\Checkout\Form;

use App\Domain\Checkout\Entity\Checkout;
use App\Domain\Flight\Entity\Vol;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('flight', EntityType::class, [
                'class' => Vol::class,
                'choice_label' => static fn (Vol $vol) => sprintf('%s → %s (%s)', $vol->getDepartureAirport(), $vol->getDestination(), $vol->getFlightId()),
                'label' => 'Vol',
                'placeholder' => 'Choisir un vol',
            ])
            ->add('passengerName', TextType::class, [
                'label' => 'Nom complet',
                'attr' => ['placeholder' => 'Nom complet du passager'],
            ])
            ->add('passengerEmail', EmailType::class, [
                'label' => 'Email',
                'attr' => ['placeholder' => 'Adresse email'],
            ])
            ->add('passengerPhone', TextType::class, [
                'label' => 'Téléphone',
                'attr' => ['placeholder' => 'Numéro de téléphone'],
            ])
            ->add('passengerNbr', IntegerType::class, [
                'label' => 'Nombre de passagers',
                'attr' => ['min' => 1],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Méthode de paiement',
                'choices' => [
                    'Carte bancaire' => 'CREDIT_CARD',
                    'Carte de débit' => 'DEBIT_CARD',
                    'PayPal' => 'PAYPAL',
                ],
            ])
            ->add('seatPreference', ChoiceType::class, [
                'label' => 'Préférence de siège',
                'choices' => [
                    'Fenêtre' => 'WINDOW',
                    'Couloir' => 'AISLE',
                    'Peu importe' => 'ANY',
                ],
            ])
            ->add('travelClass', ChoiceType::class, [
                'label' => 'Classe de voyage',
                'choices' => [
                    'Economy' => 'Economy',
                    'Premium Economy' => 'Premium Economy',
                    'Business' => 'Business',
                    'First' => 'First',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Checkout::class,
        ]);
    }
}