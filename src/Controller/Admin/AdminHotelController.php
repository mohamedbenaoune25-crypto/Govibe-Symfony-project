<?php

namespace App\Controller\Admin;

use App\Entity\Chambre;
use App\Entity\Hotel;
use App\Entity\Reservation;
use App\Form\HotelType;
use App\Repository\HotelRepository;
use App\Service\AdminStatsService;
use App\Service\HotelPredictionService;
use App\Service\HotelDescriptionTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/hotels')]
#[IsGranted('ROLE_ADMIN')]
class AdminHotelController extends AbstractController
{
    #[Route('/', name: 'app_admin_hotels_index', methods: ['GET'])]
    public function index(Request $request, HotelRepository $hotelRepository, AdminStatsService $adminStatsService): Response
    {
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sortBy', 'nom');
        $sortDir = $request->query->get('sortDir', 'asc');

        $hotels = $search
            ? $hotelRepository->searchHotels($search, $sortBy, $sortDir)
            : $hotelRepository->findAllSorted($sortBy, $sortDir);

        $stats = $adminStatsService->getHotelReservationStats();

        return $this->render('admin/hotel/index.html.twig', [
            'hotels' => $hotels,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'stats' => $stats,
        ]);
    }

    #[Route('/new', name: 'app_admin_hotels_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        HotelDescriptionTranslationService $hotelDescriptionTranslationService
    ): Response
    {
        $hotel = new Hotel();
        $form = $this->createForm(HotelType::class, $hotel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($hotel);
            $entityManager->flush();
            $hotelDescriptionTranslationService->translateAndStore($hotel);

            return $this->redirectToRoute('app_admin_hotels_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/hotel/new.html.twig', [
            'hotel' => $hotel,
            'form' => $form,
        ]);
    }

    #[Route('/prediction', name: 'app_admin_hotels_prediction', methods: ['GET'])]
    public function prediction(Request $request, HotelPredictionService $hotelPredictionService): Response
    {
        return $this->render('admin/hotel/prediction.html.twig', [
            'prediction' => $hotelPredictionService->buildPredictionDashboard($request->getLocale()),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_hotels_show', methods: ['GET'])]
    public function show(Hotel $hotel): Response
    {
        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$hotel->getId(),
        ])
            ->setAction($this->generateUrl('app_admin_hotels_delete', ['id' => $hotel->getId()]))
            ->setMethod('POST')
            ->getForm();

        return $this->render('admin/hotel/show.html.twig', [
            'hotel' => $hotel,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_hotels_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Hotel $hotel,
        EntityManagerInterface $entityManager,
        HotelDescriptionTranslationService $hotelDescriptionTranslationService
    ): Response
    {
        $form = $this->createForm(HotelType::class, $hotel);
        $form->handleRequest($request);

        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$hotel->getId(),
        ])
            ->setAction($this->generateUrl('app_admin_hotels_delete', ['id' => $hotel->getId()]))
            ->setMethod('POST')
            ->getForm();

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $hotelDescriptionTranslationService->translateAndStore($hotel);

            return $this->redirectToRoute('app_admin_hotels_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/hotel/edit.html.twig', [
            'hotel' => $hotel,
            'form' => $form,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_hotels_delete', methods: ['POST'])]
    public function delete(Request $request, Hotel $hotel, EntityManagerInterface $entityManager): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$submittedToken) {
            $formPayload = $request->request->all('form');
            if (is_array($formPayload)) {
                $submittedToken = $formPayload['_token'] ?? null;
            }
        }

        if ($this->isCsrfTokenValid('delete'.$hotel->getId(), $submittedToken)) {
            $connection = $entityManager->getConnection();
            try {
                $connection->beginTransaction();

                // Delete dependent reservations first, then dependent rooms, then the hotel.
                $entityManager->createQuery('DELETE FROM '.Reservation::class.' r WHERE r.hotel = :hotel')
                    ->setParameter('hotel', $hotel)
                    ->execute();

                $entityManager->createQuery('DELETE FROM '.Chambre::class.' c WHERE c.hotel = :hotel')
                    ->setParameter('hotel', $hotel)
                    ->execute();

                $entityManager->remove($hotel);
                $entityManager->flush();

                $connection->commit();
                $this->addFlash('success', 'Hôtel supprimé avec succès.');
            } catch (Throwable $exception) {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                $this->addFlash('error', 'Impossible de supprimer cet hôtel pour le moment.');
            }
        } else {
            $this->addFlash('error', 'Requête de suppression invalide.');
        }

        return $this->redirectToRoute('app_admin_hotels_index', [], Response::HTTP_SEE_OTHER);
    }
}