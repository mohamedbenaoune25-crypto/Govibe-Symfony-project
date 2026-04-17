<?php

namespace App\Controller\Web;

use App\Entity\Location;
use App\Entity\Voiture;
use App\Form\LocationType;
use App\Repository\LocationRepository;
use App\Repository\VoitureRepository;
use App\Service\LocationPdfService;
use App\Service\LocationExcelService;
use App\Service\ContratGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/locations')]
#[IsGranted('ROLE_USER')]
class LocationController extends AbstractController
{
    private const CITY_COORDS = [
        'tunis' => ['lat' => 36.8065, 'lng' => 10.1815],
        'sousse' => ['lat' => 35.8256, 'lng' => 10.6369],
        'sfax' => ['lat' => 34.7406, 'lng' => 10.7603],
        'nabeul' => ['lat' => 36.4561, 'lng' => 10.7376],
        'monastir' => ['lat' => 35.7643, 'lng' => 10.8113],
        'mahdia' => ['lat' => 35.5047, 'lng' => 11.0622],
        'bizerte' => ['lat' => 37.2744, 'lng' => 9.8739],
        'ariana' => ['lat' => 36.8665, 'lng' => 10.1647],
        'ben arous' => ['lat' => 36.7531, 'lng' => 10.2189],
    ];

    #[Route('/', name: 'app_location_index', methods: ['GET'])]
    public function index(
        Request $request,
        LocationRepository $locationRepository
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $searchRef = $request->query->get('reference', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'dateDebut');
        $sortOrder = $request->query->get('order', 'DESC');

        // Validation du tri
        $validSortFields = ['dateDebut', 'dateFin', 'montantTotal', 'nbJours', 'statut', 'dateCreation'];
        $validSortOrders = ['ASC', 'DESC'];

        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'dateDebut';
        }
        if (!in_array($sortOrder, $validSortOrders)) {
            $sortOrder = 'DESC';
        }

        $user = $this->getUser();
        $queryBuilder = $locationRepository->createQueryBuilder('l')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy("l.$sortBy", $sortOrder);

        if ($searchRef) {
            $queryBuilder->andWhere('l.reference LIKE :reference')
                ->setParameter('reference', '%' . $searchRef . '%');
        }

        if ($statut) {
            $queryBuilder->andWhere('l.statut = :statut')
                ->setParameter('statut', $statut);
        }

        $total = count($queryBuilder->getQuery()->getResult());
        $locations = $queryBuilder->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($total / $limit);

        return $this->render('location/index.html.twig', [
            'locations' => $locations,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'searchRef' => $searchRef,
            'statut' => $statut,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'statutes' => ['EN_ATTENTE', 'CONFIRMEE', 'ANNULEE', 'TERMINEE']
        ]);
    }

    #[Route('/ai-cars', name: 'app_location_ai_cars', methods: ['GET'])]
    public function aiCars(Request $request, VoitureRepository $voitureRepository): Response
    {
        $locationQuery = trim((string) $request->query->get('location', ''));
        $maxPrice = (float) $request->query->get('maxPrice', 0);

        $qb = $voitureRepository->createQueryBuilder('v')
            ->where('v.statut = :status')
            ->setParameter('status', 'DISPONIBLE');

        if ($maxPrice > 0) {
            $qb->andWhere('v.prixJour <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }

        /** @var Voiture[] $cars */
        $cars = $qb->orderBy('v.prixJour', 'ASC')->getQuery()->getResult();

        $results = [];
        foreach ($cars as $car) {
            $score = $this->computeCarAiScore($car, $locationQuery, $maxPrice);

            if ($locationQuery !== '' && $score < 10) {
                continue;
            }

            $results[] = [
                'id' => $car->getIdVoiture(),
                'modele' => trim(((string) $car->getMarque()) . ' ' . ((string) $car->getModele())),
                'prix' => round((float) $car->getPrixJour(), 2),
                'agence' => (string) $car->getAdresseAgence(),
                'localisation' => $this->resolveCarLocation($car),
                'score' => round($score, 2),
            ];
        }

        usort($results, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        return $this->json([
            'locationQuery' => $locationQuery,
            'maxPrice' => $maxPrice,
            'count' => count($results),
            'cars' => array_slice($results, 0, 12),
        ]);
    }

    #[Route('/new', name: 'app_location_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        LocationRepository $locationRepository,
        VoitureRepository $voitureRepository,
        EntityManagerInterface $entityManager,
        ContratGeneratorService $contratGenerator
    ): Response {
        $location = new Location();
        $user = $this->getUser();
        $location->setUser($user);

        $recommendedCarId = $request->query->getInt('recommendedCar', 0);
        if ($recommendedCarId > 0) {
            $recommendedCar = $voitureRepository->find($recommendedCarId);
            if ($recommendedCar instanceof Voiture && $recommendedCar->getStatut() === 'DISPONIBLE') {
                $location->setVoiture($recommendedCar);
            }
        }

        $form = $this->createForm(LocationType::class, $location, [
            'user' => $user,
            'voiture_repository' => $voitureRepository,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $voiture = $location->getVoiture();
            if (!$voiture || $voiture->getStatut() !== 'DISPONIBLE') {
                $form->get('voiture')->addError(
                    new FormError('Cette voiture n\'est pas disponible pour une nouvelle location.')
                );

                return $this->render('location/new.html.twig', [
                    'form' => $form,
                ]);
            }

            $dateDebut = \DateTime::createFromInterface($location->getDateDebut());
            $dateFin = \DateTime::createFromInterface($location->getDateFin());
            if (!$locationRepository->isVoitureAvailable($voiture, $dateDebut, $dateFin)) {
                $form->get('voiture')->addError(
                    new FormError('Cette voiture est déjà louée pendant la période sélectionnée.')
                );

                return $this->render('location/new.html.twig', [
                    'form' => $form,
                ]);
            }

            // Génération de la référence
            $reference = 'LOC-' . date('YmdHis') . '-' . substr(uniqid(), -4);
            $location->setReference($reference);

            // Calcul du nombre de jours
            $dateDebut = $location->getDateDebut();
            $dateFin = $location->getDateFin();
            $interval = $dateFin->diff($dateDebut);
            $nbJours = $interval->days + 1; // +1 pour inclure le jour de fin
            $location->setNbJours($nbJours);

            // Calcul du montant total
            $prixJour = floatval($voiture->getPrixJour());
            $montantTotal = $prixJour * $nbJours;
            $location->setMontantTotal((string)$montantTotal);

            // Génération du contrat PDF
            $contratPath = $contratGenerator->generateContrat($location);
            $location->setContratPdf($contratPath);

            $entityManager->persist($location);
            $entityManager->flush();

            $this->addFlash('success', 'Location créée avec succès!');
            return $this->redirectToRoute('app_location_show', ['id' => $location->getIdLocation()]);
        }

        return $this->render('location/new.html.twig', [
            'form' => $form,
        ]);
    }

    private function computeCarAiScore(Voiture $car, string $locationQuery, float $maxPrice): float
    {
        $score = 20.0;
        $query = mb_strtolower(trim($locationQuery));
        $agency = mb_strtolower((string) $car->getAdresseAgence());
        $model = mb_strtolower(trim(((string) $car->getMarque()) . ' ' . ((string) $car->getModele())));

        if ($query !== '') {
            if (str_contains($agency, $query) || str_contains($model, $query)) {
                $score += 60.0;
            } else {
                $simAgency = 0.0;
                similar_text($query, $agency, $simAgency);
                $simModel = 0.0;
                similar_text($query, $model, $simModel);
                $score += max($simAgency, $simModel) * 0.25;
            }

            $cityMatchScore = $this->computeCityDistanceScore($car, $query);
            $score += $cityMatchScore;
        }

        $price = (float) $car->getPrixJour();
        if ($maxPrice > 0) {
            $affordability = max(0.0, min(1.0, 1 - ($price / $maxPrice)));
            $score += $affordability * 20.0;
        }

        return $score;
    }

    private function computeCityDistanceScore(Voiture $car, string $query): float
    {
        foreach (self::CITY_COORDS as $city => $coords) {
            if (!str_contains($query, $city)) {
                continue;
            }

            $lat = $car->getLatitude();
            $lng = $car->getLongitude();
            if ($lat === null || $lng === null) {
                return 0.0;
            }

            $distanceKm = $this->haversineDistanceKm((float) $lat, (float) $lng, $coords['lat'], $coords['lng']);

            return max(0.0, 40.0 - ($distanceKm * 0.6));
        }

        return 0.0;
    }

    private function resolveCarLocation(Voiture $car): string
    {
        $address = (string) $car->getAdresseAgence();
        $normalizedAddress = mb_strtolower($address);

        foreach (array_keys(self::CITY_COORDS) as $city) {
            if (str_contains($normalizedAddress, $city)) {
                return ucfirst($city);
            }
        }

        return $address;
    }

    private function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    #[Route('/check-availability', name: 'app_location_check_availability', methods: ['GET'])]
    public function checkAvailability(
        Request $request,
        LocationRepository $locationRepository,
        VoitureRepository $voitureRepository
    ): Response {
        $voitureId = $request->query->getInt('voitureId', 0);
        $dateDebutValue = (string) $request->query->get('dateDebut', '');
        $dateFinValue = (string) $request->query->get('dateFin', '');

        if ($voitureId <= 0 || $dateDebutValue === '' || $dateFinValue === '') {
            return $this->json([
                'available' => false,
                'reason' => 'missing-data',
                'message' => 'Veuillez sélectionner la voiture et les dates.',
            ]);
        }

        $voiture = $voitureRepository->find($voitureId);
        if (!$voiture) {
            return $this->json([
                'available' => false,
                'reason' => 'invalid-voiture',
                'message' => 'Voiture introuvable.',
            ]);
        }

        if ($voiture->getStatut() !== 'DISPONIBLE') {
            return $this->json([
                'available' => false,
                'reason' => 'unavailable-status',
                'message' => 'Cette voiture est actuellement ' . strtolower((string) $voiture->getStatut()) . '.',
            ]);
        }

        $dateDebut = \DateTime::createFromFormat('Y-m-d', $dateDebutValue) ?: null;
        $dateFin = \DateTime::createFromFormat('Y-m-d', $dateFinValue) ?: null;
        if (!$dateDebut || !$dateFin) {
            return $this->json([
                'available' => false,
                'reason' => 'invalid-dates',
                'message' => 'Dates invalides.',
            ]);
        }

        if ($dateFin <= $dateDebut) {
            return $this->json([
                'available' => false,
                'reason' => 'invalid-range',
                'message' => 'La date de fin doit être après la date de début.',
            ]);
        }

        $isAvailable = $locationRepository->isVoitureAvailable($voiture, $dateDebut, $dateFin);

        return $this->json([
            'available' => $isAvailable,
            'reason' => $isAvailable ? 'ok' : 'overlap',
            'message' => $isAvailable
                ? 'Ce créneau est disponible.'
                : 'Cette voiture est déjà allouée sur le créneau sélectionné.',
        ]);
    }

    #[Route('/{id}', name: 'app_location_show', methods: ['GET'])]
    public function show(Location $location): Response
    {
        // Vérifier que l'utilisateur est propriétaire de la location
        if ($location->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette location.');
        }

        // Calculer les statistiques
        $stats = [
            'nbJours' => $location->getNbJours(),
            'prixJournier' => round(floatval($location->getMontantTotal()) / $location->getNbJours(), 2),
            'prixTotal' => floatval($location->getMontantTotal()),
        ];

        return $this->render('location/show.html.twig', [
            'location' => $location,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_location_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Location $location,
        EntityManagerInterface $entityManager,
        VoitureRepository $voitureRepository
    ): Response {
        // Vérifier les droits
        if ($location->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette location.');
        }

        // Empêcher l'édition si la location est confirmée ou annulée
        if (in_array($location->getStatut(), ['CONFIRMEE', 'ANNULEE', 'TERMINEE'])) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier une location ' . strtolower($location->getStatut()));
            return $this->redirectToRoute('app_location_show', ['id' => $location->getIdLocation()]);
        }

        $form = $this->createForm(LocationType::class, $location, [
            'user' => $this->getUser(),
            'voiture_repository' => $voitureRepository,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalculer les données
            $dateDebut = $location->getDateDebut();
            $dateFin = $location->getDateFin();
            $interval = $dateFin->diff($dateDebut);
            $nbJours = $interval->days + 1;
            $location->setNbJours($nbJours);

            $voiture = $location->getVoiture();
            $prixJour = floatval($voiture->getPrixJour());
            $montantTotal = $prixJour * $nbJours;
            $location->setMontantTotal((string)$montantTotal);

            $entityManager->flush();

            $this->addFlash('success', 'Location modifiée avec succès!');
            return $this->redirectToRoute('app_location_show', ['id' => $location->getIdLocation()]);
        }

        return $this->render('location/edit.html.twig', [
            'location' => $location,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_location_cancel', methods: ['POST'])]
    public function cancel(
        Request $request,
        Location $location,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier les droits
        if ($location->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette location.');
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('cancel' . $location->getIdLocation(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_location_show', ['id' => $location->getIdLocation()]);
        }

        // Empêcher l'annulation si déjà confirmée, annulée ou terminée
        if (in_array($location->getStatut(), ['CONFIRMEE', 'ANNULEE', 'TERMINEE'])) {
            $this->addFlash('error', 'Vous ne pouvez pas annuler une location ' . strtolower($location->getStatut()));
            return $this->redirectToRoute('app_location_show', ['id' => $location->getIdLocation()]);
        }

        $location->setStatut('ANNULEE');
        $entityManager->flush();

        $this->addFlash('success', 'Location annulée avec succès!');
        return $this->redirectToRoute('app_location_index');
    }

    #[Route('/{id}/delete', name: 'app_location_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Location $location,
        EntityManagerInterface $entityManager
    ): Response {
        if ($location->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette location.');
        }

        if (!$this->isCsrfTokenValid('delete' . $location->getIdLocation(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_location_show', ['id' => $location->getIdLocation()]);
        }

        $entityManager->remove($location);
        $entityManager->flush();

        $this->addFlash('success', 'Location supprimée définitivement avec succès!');
        return $this->redirectToRoute('app_location_index');
    }

    #[Route('/{id}/pdf', name: 'app_location_pdf', methods: ['GET'])]
    public function downloadPdf(
        Location $location,
        LocationPdfService $pdfService
    ): Response {
        // Vérifier les droits
        if ($location->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce document.');
        }

        return $pdfService->generatePdf($location);
    }

    #[Route('/{id}/excel', name: 'app_location_excel', methods: ['GET'])]
    public function downloadExcel(
        Location $location,
        LocationExcelService $excelService
    ): Response {
        // Vérifier les droits
        if ($location->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce document.');
        }

        return $excelService->generateExcel($location);
    }

    #[Route('/{id}/contrat', name: 'app_location_contrat', methods: ['GET'])]
    public function downloadContrat(Location $location): Response
    {
        // Vérifier les droits
        if ($location->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce document.');
        }

        if (!$location->getContratPdf()) {
            throw $this->createNotFoundException('Aucun contrat disponible pour cette location.');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $location->getContratPdf();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier du contrat n\'existe pas.');
        }

        return $this->file($filePath);
    }


    #[Route('/stats/export', name: 'app_location_stats_export', methods: ['GET'])]
    public function exportStats(
        LocationRepository $locationRepository,
        LocationExcelService $excelService
    ): Response {
        $user = $this->getUser();
        $locations = $locationRepository->findBy(['user' => $user]);

        return $excelService->generateStatsExcel($locations);
    }
}
