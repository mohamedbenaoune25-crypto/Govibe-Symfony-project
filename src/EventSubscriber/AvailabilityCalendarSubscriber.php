<?php

namespace App\EventSubscriber;

use App\Entity\Location;
use App\Repository\LocationRepository;
use CalendarBundle\CalendarEvents;
use CalendarBundle\Entity\Event;
use CalendarBundle\Event\CalendarEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AvailabilityCalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LocationRepository $locationRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CalendarEvents::SET_DATA => 'onCalendarSetData',
        ];
    }

    public function onCalendarSetData(CalendarEvent $calendar): void
    {
        $filters = $calendar->getFilters();
        $carId = isset($filters['carId']) ? (int) $filters['carId'] : 0;

        if ($carId <= 0) {
            return;
        }

        $bookedLocations = $this->locationRepository->createQueryBuilder('l')
            ->where('IDENTITY(l.voiture) = :carId')
            ->andWhere('l.statut IN (:activeStatuses)')
            ->andWhere('l.dateDebut <= :end')
            ->andWhere('l.dateFin >= :start')
            ->setParameter('carId', $carId)
            ->setParameter('activeStatuses', ['EN_ATTENTE', 'CONFIRMEE'])
            ->setParameter('start', $calendar->getStart())
            ->setParameter('end', $calendar->getEnd())
            ->orderBy('l.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($bookedLocations as $location) {
            if (!$location instanceof Location) {
                continue;
            }

            // FullCalendar treats end date as exclusive for all-day events.
            $endDate = \DateTimeImmutable::createFromInterface($location->getDateFin());
            $endDateExclusive = $endDate->modify('+1 day');
            $event = new Event(
                'Indisponible',
                $location->getDateDebut(),
                $endDateExclusive
            );
            $event->setAllDay(true);
            $event->setOptions([
                'display' => 'background',
                'backgroundColor' => '#f87171',
                'borderColor' => '#ef4444',
            ]);

            $calendar->addEvent($event);
        }
    }
}
