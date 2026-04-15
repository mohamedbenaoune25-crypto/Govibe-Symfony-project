<?php

namespace App\Form;

use App\Entity\Reservation;
use App\Entity\Chambre;
use App\Entity\Hotel;
use App\Repository\ChambreRepository;
use Symfony\Component\Validator\Constraints as Assert;
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
    public function __construct(private readonly ChambreRepository $chambreRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $hotel = $options['hotel'];
        $reservation = $builder->getData();
        $selectedType = $reservation instanceof Reservation ? $reservation->getChambre()?->getType() : null;

        $typesQb = $this->chambreRepository->createQueryBuilder('c')
            ->select('DISTINCT c.type AS type')
            ->where('c.type IS NOT NULL')
            ->andWhere('c.type != :emptyType')
            ->setParameter('emptyType', '')
            ->orderBy('c.type', 'ASC');

        if ($hotel instanceof Hotel) {
            $typesQb
                ->andWhere('c.hotel = :hotel')
                ->setParameter('hotel', $hotel);
        }

        $typeChoices = [];
        foreach ($typesQb->getQuery()->getScalarResult() as $row) {
            if (!isset($row['type'])) {
                continue;
            }

            $label = trim((string) $row['type']);
            if ($label === '') {
                continue;
            }

            $typeChoices[$label] = $label;
        }

        $builder
            ->add('typeChambre', ChoiceType::class, [
                'label' => 'Type de chambre',
                'mapped' => false,
                'required' => false,
                'choices' => $typeChoices,
                'placeholder' => 'Sélectionnez un type',
                'data' => $selectedType,
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'required' => true,
                'invalid_message' => 'Veuillez saisir une date de début valide.',
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'required' => true,
                'invalid_message' => 'Veuillez saisir une date de fin valide.',
            ])
            ->add('prixTotal', NumberType::class, [
                'label' => 'Prix total',
                'required' => true,
                'invalid_message' => 'Le prix total doit être un nombre valide.',
                'attr' => [
                    'min' => 0.01,
                    'step' => '0.01',
                ],
                'constraints' => [new Assert\NotBlank(['message' => 'Le prix total est obligatoire.'])],
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
                'placeholder' => 'Sélectionnez une chambre',
                'invalid_message' => 'Veuillez sélectionner une chambre valide.',
                'constraints' => [new Assert\NotBlank(['message' => 'La chambre est obligatoire.'])],
                'choice_attr' => function (Chambre $chambre) {
                    return [
                        'data-hotel-id' => (string) ($chambre->getHotel()?->getId() ?? ''),
                        'data-type' => (string) ($chambre->getType() ?? ''),
                        'data-disponibles' => (string) ($chambre->getNombreDeChambres() ?? 0),
                    ];
                },
            ])
        ;

        if (!$hotel instanceof Hotel) {
            $builder->add('hotel', EntityType::class, [
                'class' => Hotel::class,
                'choice_label' => 'nom',
                'label' => 'Hôtel',
                'required' => true,
                'placeholder' => 'Sélectionnez un hôtel',
                'invalid_message' => 'Veuillez sélectionner un hôtel valide.',
            ]);
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