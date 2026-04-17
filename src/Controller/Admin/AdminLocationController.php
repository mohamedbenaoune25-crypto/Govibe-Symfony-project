<?php

namespace App\Controller\Admin;

use App\Entity\Location;
use App\Entity\Voiture;
use App\Repository\LocationRepository;
use App\Service\LocationExcelService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/locations')]
#[IsGranted('ROLE_ADMIN')]
class AdminLocationController extends AbstractController
{
    #[Route('/', name: 'app_admin_location_index', methods: ['GET'])]
    public function index(
        Request $request,
        LocationRepository $locationRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $searchRef = $request->query->get('reference', '');
        $searchUser = $request->query->get('user', '');
        $searchVoiture = $request->query->get('voiture', '');
        $statut = $request->query->get('statut', '');
        $dateFrom = $request->query->get('dateFrom', '');
        $dateTo = $request->query->get('dateTo', '');
        $sortBy = $request->query->get('sort', 'dateCreation');
        $sortOrder = $request->query->get('order', 'DESC');

        // Validation du tri
        $validSortFields = ['dateDebut', 'dateFin', 'montantTotal', 'nbJours', 'statut', 'dateCreation'];
        $validSortOrders = ['ASC', 'DESC'];

        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'dateCreation';
        }
        if (!in_array($sortOrder, $validSortOrders)) {
            $sortOrder = 'DESC';
        }

        $queryBuilder = $locationRepository->createQueryBuilder('l')
            ->leftJoin('l.user', 'u')
            ->leftJoin('l.voiture', 'v')
            ->orderBy("l.$sortBy", $sortOrder);

        if ($searchRef) {
            $queryBuilder->andWhere('l.reference LIKE :reference')
                ->setParameter('reference', '%' . $searchRef . '%');
        }

        if ($searchUser) {
            $queryBuilder->andWhere('(u.nom LIKE :user OR u.prenom LIKE :user OR u.email LIKE :user)')
                ->setParameter('user', '%' . $searchUser . '%');
        }

        if ($searchVoiture) {
            $queryBuilder->andWhere('(v.marque LIKE :voiture OR v.modele LIKE :voiture OR v.matricule LIKE :voiture)')
                ->setParameter('voiture', '%' . $searchVoiture . '%');
        }

        if ($statut) {
            $queryBuilder->andWhere('l.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($dateFrom) {
            $queryBuilder->andWhere('l.dateDebut >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($dateFrom));
        }

        if ($dateTo) {
            $queryBuilder->andWhere('l.dateFin <= :dateTo')
                ->setParameter('dateTo', new \DateTime($dateTo));
        }

        $total = count($queryBuilder->getQuery()->getResult());
        $locations = $queryBuilder->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($total / $limit);

        // Calculer les statistiques
        $stats = $this->calculateStats($locationRepository);

        return $this->render('admin/location/index.html.twig', [
            'locations' => $locations,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'searchRef' => $searchRef,
            'searchUser' => $searchUser,
            'searchVoiture' => $searchVoiture,
            'statut' => $statut,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'statutes' => ['EN_ATTENTE', 'CONFIRMEE', 'ANNULEE', 'TERMINEE'],
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_location_show', methods: ['GET'])]
    public function show(Location $location): Response
    {
        // Calculer des infos supplémentaires
        $stats = [
            'nbJours' => $location->getNbJours(),
            'prixJournier' => round(floatval($location->getMontantTotal()) / $location->getNbJours(), 2),
            'prixTotal' => floatval($location->getMontantTotal()),
        ];

        return $this->render('admin/location/show.html.twig', [
            'location' => $location,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/confirm', name: 'app_admin_location_confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        Location $location,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('confirm' . $location->getIdLocation(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_admin_location_show', ['id' => $location->getIdLocation()]);
        }

        if ($location->getStatut() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Seules les locations en attente peuvent être confirmées.');
            return $this->redirectToRoute('app_admin_location_show', ['id' => $location->getIdLocation()]);
        }

        // Vérifier que la voiture n'est pas déjà en location à cette période
        if (!$this->isVoitureAvailable($location, $entityManager)) {
            $this->addFlash('error', 'Cette voiture n\'est pas disponible à cette période.');
            return $this->redirectToRoute('app_admin_location_show', ['id' => $location->getIdLocation()]);
        }

        $location->setStatut('CONFIRMEE');
        $entityManager->flush();

        $this->addFlash('success', 'Location confirmée avec succès!');
        return $this->redirectToRoute('app_admin_location_show', ['id' => $location->getIdLocation()]);
    }

    #[Route('/{id}/cancel', name: 'app_admin_location_cancel', methods: ['POST'])]
    public function cancel(
        Request $request,
        Location $location,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('cancel' . $location->getIdLocation(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_admin_location_show', ['id' => $location->getIdLocation()]);
        }

        if ($location->getStatut() === 'ANNULEE') {
            $this->addFlash('error', 'Cette location est déjà annulée.');
            return $this->redirectToRoute('app_admin_location_show', ['id' => $location->getIdLocation()]);
        }

        if ($location->getStatut() === 'TERMINEE') {
            $this->addFlash('error', 'Une location terminée ne peut pas être annulée.');
            return $this->redirectToRoute('app_admin_location_show', ['id' => $location->getIdLocation()]);
        }

        $location->setStatut('ANNULEE');
        $entityManager->flush();

        $this->addFlash('success', 'Location annulée avec succès!');
        return $this->redirectToRoute('app_admin_location_index');
    }

    #[Route('/export/excel', name: 'app_admin_location_export_excel', methods: ['GET'])]
    public function exportExcel(
        LocationRepository $locationRepository,
        LocationExcelService $excelService
    ): Response {
        $locations = $locationRepository->findAll();
        return $excelService->generateAdminStatsExcel($locations);
    }

    /**
     * Vérifier si une voiture est disponible pour la période donnée
     */
    private function isVoitureAvailable(Location $location, EntityManagerInterface $entityManager): bool
    {
        $voiture = $location->getVoiture();
        $dateDebut = $location->getDateDebut();
        $dateFin = $location->getDateFin();

        $locations = $entityManager->getRepository(Location::class)->createQueryBuilder('l')
            ->where('l.voiture = :voiture')
            ->andWhere('l.idLocation != :currentId') // Exclure la location actuelle en cas de modification
            ->andWhere('l.statut IN (:statuts)')
            ->andWhere('(:dateDebut <= l.dateFin AND :dateFin >= l.dateDebut)')
            ->setParameter('voiture', $voiture)
            ->setParameter('currentId', $location->getIdLocation())
            ->setParameter('statuts', ['CONFIRMEE', 'EN_ATTENTE'])
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getResult();

        return count($locations) === 0;
    }

    /**
     * Calculer les statistiques générales
     */
    private function calculateStats(LocationRepository $locationRepository): array
    {
        $allLocations = $locationRepository->findAll();
        
        $byStatut = [];
        $totalRevenue = 0;
        $nbVoituresActives = [];

        foreach ($allLocations as $location) {
            // Compter par statut
            $statut = $location->getStatut();
            if (!isset($byStatut[$statut])) {
                $byStatut[$statut] = 0;
            }
            $byStatut[$statut]++;

            // Revenue total
            $totalRevenue += floatval($location->getMontantTotal());

            // Voitures actives
            if (in_array($statut, ['CONFIRMEE', 'EN_ATTENTE'])) {
                $voitureId = $location->getVoiture()->getIdVoiture();
                $nbVoituresActives[$voitureId] = true;
            }
        }

        return [
            'totalLocations' => count($allLocations),
            'byStatut' => $byStatut,
            'totalRevenue' => $totalRevenue,
            'nbVoituresActives' => count($nbVoituresActives),
        ];
    }
}
