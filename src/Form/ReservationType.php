<?php

namespace App\Form;

use App\Entity\Reservation;
use App\Entity\Personne;
use App\Entity\Chambre;
use App\Entity\Hotel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('prixTotal', NumberType::class, [
                'label' => 'Prix total',
                'required' => true,
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'EN_ATTENTE',
                    'Confirmée' => 'CONFIRMEE',
                    'Annulée' => 'ANNULEE',
                ],
                'required' => true,
            ])
            ->add('user', EntityType::class, [
                'class' => Personne::class,
                'choice_label' => 'email', // assuming Personne has email
                'label' => 'Utilisateur',
                'required' => true,
            ])
            ->add('chambre', EntityType::class, [
                'class' => Chambre::class,
                'choice_label' => function (Chambre $chambre) {
                    return $chambre->getType() . ' - ' . $chambre->getHotel()->getNom();
                },
                'label' => 'Chambre',
                'required' => true,
            ])
            ->add('hotel', EntityType::class, [
                'class' => Hotel::class,
                'choice_label' => 'nom',
                'label' => 'Hôtel',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}