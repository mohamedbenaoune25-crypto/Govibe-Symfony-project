<?php

namespace App\Form;

use App\Entity\Chambre;
use App\Entity\Hotel;
use App\Entity\Reservation;
use App\Repository\ChambreRepository;
use App\Service\HolidayService;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationType extends AbstractType
{
    public function __construct(
        private readonly ChambreRepository $chambreRepository,
        private readonly HolidayService $holidayService
    ) {
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
            ->andWhere('(COALESCE(c.prixStandard, 0) > 0 OR COALESCE(c.prixHauteSaison, 0) > 0 OR COALESCE(c.prixBasseSaison, 0) > 0)')
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
            ->add('hotel', EntityType::class, [
                'class' => Hotel::class,
                'choice_label' => 'nom',
                'label' => 'Hôtel',
                'required' => false,
                'placeholder' => false,
                'data' => $hotel instanceof Hotel ? $hotel : null,
                'attr' => [
                    'class' => 'd-none',
                ],
                'row_attr' => [
                    'class' => 'd-none',
                ],
            ])
            ->add('chambre', EntityType::class, [
                'class' => Chambre::class,
                'query_builder' => $hotel instanceof Hotel
                    ? function (EntityRepository $repository) use ($hotel) {
                        return $repository->createQueryBuilder('c')
                            ->andWhere('c.hotel = :hotel')
                            ->andWhere('(COALESCE(c.prixStandard, 0) > 0 OR COALESCE(c.prixHauteSaison, 0) > 0 OR COALESCE(c.prixBasseSaison, 0) > 0)')
                            ->setParameter('hotel', $hotel)
                            ->orderBy('c.type', 'ASC');
                    }
                    : null,
                'choice_label' => static function (Chambre $chambre): string {
                    return ($chambre->getType() ?? '-') . ' - ' . ($chambre->getHotel()?->getNom() ?? '-');
                },
                'label' => 'Chambre',
                'required' => true,
                'placeholder' => 'Sélectionnez une chambre',
                'invalid_message' => 'Veuillez sélectionner une chambre valide.',
                'choice_attr' => static function (Chambre $chambre): array {
                    return [
                        'data-hotel-id' => (string) ($chambre->getHotel()?->getId() ?? ''),
                        'data-type' => (string) ($chambre->getType() ?? ''),
                        'data-disponibles' => (string) ($chambre->getNombreDeChambres() ?? 0),
                        'data-prix-standard' => (string) ($chambre->getPrixStandard() ?? 0),
                        'data-prix-haute' => (string) ($chambre->getPrixHauteSaison() ?? 0),
                        'data-prix-basse' => (string) ($chambre->getPrixBasseSaison() ?? 0),
                    ];
                },
            ])
        ;

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $reservation = $event->getData();
            if (!$reservation instanceof Reservation) {
                return;
            }

            $chambre = $reservation->getChambre();
            $dateDebut = $reservation->getDateDebut();
            $dateFin = $reservation->getDateFin();

            if (!$chambre instanceof Chambre) {
                $reservation->setPrixTotal(null);
                return;
            }

            if ($reservation->getHotel() === null && $chambre->getHotel() !== null) {
                $reservation->setHotel($chambre->getHotel());
            }

            if ($reservation->getHotel() !== null && $chambre->getHotel() !== null && $reservation->getHotel()?->getId() !== $chambre->getHotel()?->getId()) {
                $reservation->setHotel($chambre->getHotel());
            }

            if (!$dateDebut instanceof \DateTimeInterface || !$dateFin instanceof \DateTimeInterface || $dateFin <= $dateDebut) {
                $reservation->setPrixTotal(null);
                return;
            }

            $unitPrice = $this->resolveRoomUnitPrice($chambre);
            if ($unitPrice <= 0) {
                $reservation->setPrixTotal(null);
                return;
            }

            $nights = (int) $dateDebut->diff($dateFin)->days;
            if ($nights <= 0) {
                $reservation->setPrixTotal(null);
                return;
            }

            $basePrice = round($unitPrice * $nights, 2);
            $holidaySupplement = $this->holidayService->calculateHolidaySupplement(
                $unitPrice,
                \DateTimeImmutable::createFromInterface($dateDebut),
                \DateTimeImmutable::createFromInterface($dateFin)
            );

            $reservation->setPrixTotal(round($basePrice + $holidaySupplement, 2));
        });

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'hotel' => null,
        ]);

        $resolver->setAllowedTypes('hotel', ['null', Hotel::class]);
    }

    private function resolveRoomUnitPrice(Chambre $chambre): float
    {
        $standard = (float) ($chambre->getPrixStandard() ?? 0);
        if ($standard > 0) {
            return $standard;
        }

        $highSeason = (float) ($chambre->getPrixHauteSaison() ?? 0);
        if ($highSeason > 0) {
            return $highSeason;
        }

        $lowSeason = (float) ($chambre->getPrixBasseSaison() ?? 0);
        return $lowSeason > 0 ? $lowSeason : 0.0;
    }
}