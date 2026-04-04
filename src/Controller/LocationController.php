<?php

namespace App\Controller;

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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/locations')]
#[IsGranted('ROLE_USER')]
class LocationController extends AbstractController
{
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

    #[Route('/new', name: 'app_location_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        VoitureRepository $voitureRepository,
        EntityManagerInterface $entityManager,
        ContratGeneratorService $contratGenerator
    ): Response {
        $location = new Location();
        $user = $this->getUser();
        $location->setUser($user);

        $form = $this->createForm(LocationType::class, $location, [
            'user' => $user,
            'voiture_repository' => $voitureRepository,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
            $voiture = $location->getVoiture();
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
