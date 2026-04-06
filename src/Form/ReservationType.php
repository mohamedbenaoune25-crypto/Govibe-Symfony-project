<?php

namespace App\Form;

use App\Entity\Reservation;
use App\Entity\Chambre;
use App\Entity\Hotel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\EntityRepository;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $hotel = $options['hotel'];

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
            ->add('chambre', EntityType::class, [
                'class' => Chambre::class,
                'query_builder' => $hotel instanceof Hotel
                    ? function (EntityRepository $repository) use ($hotel) {
                        return $repository->createQueryBuilder('c')
                            ->andWhere('c.hotel = :hotel')
                            ->setParameter('hotel', $hotel)
                            ->orderBy('c.type', 'ASC');
                    }
                    : null,
                'choice_label' => function (Chambre $chambre) {
                    return $chambre->getType() . ' - ' . $chambre->getHotel()->getNom();
                },
                'label' => 'Chambre',
                'required' => true,
            ])
        ;

        if (!$hotel instanceof Hotel) {
            $builder->add('hotel', EntityType::class, [
                'class' => Hotel::class,
                'choice_label' => 'nom',
                'label' => 'Hôtel',
                'required' => true,
            ]);
        }

        if ($hotel instanceof Hotel) {
            $builder->remove('statut');
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'hotel' => null,
        ]);

        $resolver->setAllowedTypes('hotel', ['null', Hotel::class]);
    }
}