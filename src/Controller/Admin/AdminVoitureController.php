<?php

namespace App\Controller\Admin;

use App\Entity\Voiture;
use App\Form\VoitureType;
use App\Repository\VoitureRepository;
use App\Service\VoiturePdfService;
use App\Service\VoitureExcelService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/voitures')]
#[IsGranted('ROLE_ADMIN')]
class AdminVoitureController extends AbstractController
{
    private const AGENCIES = [
        'HOPPA CAR' => ['latitude' => '36.84641000', 'longitude' => '10.21542000'],
        'Bakir Rent A Car' => ['latitude' => '36.84881000', 'longitude' => '10.21613000'],
        'Tunisia Rent Car' => ['latitude' => '36.84651000', 'longitude' => '10.21594000'],
        'ONE RENT CAR' => ['latitude' => '36.84631000', 'longitude' => '10.21575000'],
        'GEARS RENT A CAR' => ['latitude' => '36.84601000', 'longitude' => '10.19886000'],
        'Dreams Rent A Car' => ['latitude' => '36.84651000', 'longitude' => '10.21527000'],
        'Regency Rent A Car' => ['latitude' => '36.84601000', 'longitude' => '10.18368000'],
        'AVANTGARDE RENT A CAR' => ['latitude' => '36.85681000', 'longitude' => '10.20659000'],
        'AVIS Car Rental' => ['latitude' => '36.84681000', 'longitude' => '10.21551000'],
        'Camelcar' => ['latitude' => '36.84731000', 'longitude' => '10.21720000'],
    ];

    #[Route('/', name: 'admin_voiture_index', methods: ['GET'])]
    public function index(
        Request $request,
        VoitureRepository $voitureRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $searchMarque = $request->query->get('marque', '');
        $searchModele = $request->query->get('modele', '');
        $searchMatricule = $request->query->get('matricule', '');
        $searchCarburant = $request->query->get('carburant', '');
        $searchStatut = $request->query->get('statut', '');
        $priceMin = $request->query->get('priceMin', '');
        $priceMax = $request->query->get('priceMax', '');
        $sortBy = $request->query->get('sort', 'dateCreation');
        $sortOrder = $request->query->get('order', 'DESC');

        $validSortFields = ['marque', 'modele', 'matricule', 'prixJour', 'statut', 'annee', 'dateCreation'];
        $validSortOrders = ['ASC', 'DESC'];

        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'dateCreation';
        }
        if (!in_array($sortOrder, $validSortOrders)) {
            $sortOrder = 'DESC';
        }

        $queryBuilder = $voitureRepository->createQueryBuilder('v')
            ->orderBy("v.$sortBy", $sortOrder);

        if ($searchMarque) {
            $queryBuilder->andWhere('v.marque LIKE :marque')
                ->setParameter('marque', '%' . $searchMarque . '%');
        }

        if ($searchModele) {
            $queryBuilder->andWhere('v.modele LIKE :modele')
                ->setParameter('modele', '%' . $searchModele . '%');
        }

        if ($searchMatricule) {
            $queryBuilder->andWhere('v.matricule LIKE :matricule')
                ->setParameter('matricule', '%' . $searchMatricule . '%');
        }

        if ($searchCarburant) {
            $queryBuilder->andWhere('v.typeCarburant = :carburant')
                ->setParameter('carburant', $searchCarburant);
        }

        if ($searchStatut) {
            $queryBuilder->andWhere('v.statut = :statut')
                ->setParameter('statut', $searchStatut);
        }

        if ($priceMin) {
            $queryBuilder->andWhere('v.prixJour >= :priceMin')
                ->setParameter('priceMin', floatval($priceMin));
        }

        if ($priceMax) {
            $queryBuilder->andWhere('v.prixJour <= :priceMax')
                ->setParameter('priceMax', floatval($priceMax));
        }
        $total = count($queryBuilder->getQuery()->getResult());
        $voitures = $queryBuilder->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($total / $limit);

        // Statistiques
        $stats = $this->calculateStats($voitureRepository);

        return $this->render('admin/voiture/index.html.twig', [
            'voitures' => $voitures,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'searchMarque' => $searchMarque,
            'searchModele' => $searchModele,
            'searchMatricule' => $searchMatricule,
            'searchCarburant' => $searchCarburant,
            'searchStatut' => $searchStatut,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'carburants' => ['Essence', 'Diesel', 'Électrique', 'Hybride'],
            'statutes' => ['DISPONIBLE', 'EN_MAINTENANCE', 'ACCIDENTE'],
            'stats' => $stats,
        ]);
    }

    #[Route('/new', name: 'admin_voiture_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $voiture = new Voiture();

        $form = $this->createForm(VoitureType::class, $voiture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyAgencyCoordinates($voiture, $form->get('adresseAgence')->getData());
            $entityManager->persist($voiture);
            $entityManager->flush();

            $this->addFlash('success', 'Voiture ajoutée avec succès!');
            return $this->redirectToRoute('admin_voiture_index');
        }

        return $this->render('admin/voiture/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_voiture_show', methods: ['GET'])]
    public function show(Voiture $voiture): Response
    {
        return $this->render('admin/voiture/show.html.twig', [
            'voiture' => $voiture,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_voiture_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Voiture $voiture,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(VoitureType::class, $voiture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyAgencyCoordinates($voiture, $form->get('adresseAgence')->getData());
            $entityManager->flush();

            $this->addFlash('success', 'Voiture modifiée avec succès!');
            return $this->redirectToRoute('admin_voiture_show', ['id' => $voiture->getIdVoiture()]);
        }

        return $this->render('admin/voiture/edit.html.twig', [
            'voiture' => $voiture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_voiture_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Voiture $voiture,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('delete' . $voiture->getIdVoiture(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_voiture_show', ['id' => $voiture->getIdVoiture()]);
        }

        $entityManager->remove($voiture);
        $entityManager->flush();

        $this->addFlash('success', 'Voiture supprimée avec succès!');
        return $this->redirectToRoute('admin_voiture_index');
    }

    #[Route('/{id}/pdf', name: 'admin_voiture_download_pdf', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function downloadPdf(
        Voiture $voiture,
        VoiturePdfService $pdfService
    ): Response {
        $content = $pdfService->generateVoiturePdf($voiture);
        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="voiture_' . $voiture->getMatricule() . '_' . date('Y-m-d') . '.html"',
        ]);
    }

    #[Route('/export/pdf', name: 'admin_voiture_export_pdf', methods: ['GET'])]
    public function exportPdf(
        VoitureRepository $voitureRepository
    ): Response {
        $voitures = $voitureRepository->findAll();
        $html = $this->generateInventoryHtml($voitures);
        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="inventaire_voitures_' . date('Y-m-d') . '.html"',
        ]);
    }

    #[Route('/export/excel', name: 'admin_voiture_export_excel', methods: ['GET'])]
    public function exportExcel(
        VoitureExcelService $excelService
    ): Response {
        $content = $excelService->exportAllVoitures();
        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="inventaire_voitures_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Générer l'HTML pour l'inventaire PDF
     */
    private function generateInventoryHtml(array $voitures): string
    {
        $htmlRows = '';
        $totalValue = 0;
        
        foreach ($voitures as $voiture) {
            $totalValue += $voiture->getPrixJour();
            $statutColor = match($voiture->getStatut()) {
                'DISPONIBLE' => '#50C878',
                'EN_MAINTENANCE' => '#FF9500',
                'ACCIDENTE' => '#E74C3C',
                default => '#95A5A6'
            };
            $statut = match($voiture->getStatut()) {
                'DISPONIBLE' => 'Disponible',
                'EN_MAINTENANCE' => 'En Maintenance',
                'ACCIDENTE' => 'Accidentée',
                default => $voiture->getStatut()
            };
            
            $htmlRows .= "
                <tr>
                    <td>{$voiture->getMatricule()}</td>
                    <td>{$voiture->getMarque()} {$voiture->getModele()}</td>
                    <td>{$voiture->getAnnee()}</td>
                    <td>{$voiture->getTypeCarburant()}</td>
                    <td>" . number_format($voiture->getPrixJour(), 2) . " DT</td>
                    <td><span style='background-color: {$statutColor}; color: white; padding: 4px 8px; border-radius: 3px;'>{$statut}</span></td>
                </tr>
            ";
        }

        $avgPrice = count($voitures) > 0 ? $totalValue / count($voitures) : 0;

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 20px; }
                .header { text-align: center; border-bottom: 3px solid #50C878; padding-bottom: 20px; margin-bottom: 20px; }
                .header h1 { margin: 0; color: #013220; font-size: 28px; }
                .header p { margin: 5px 0 0 0; color: #2E8B57; font-size: 14px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background-color: #50C878; color: white; padding: 12px; text-align: left; font-weight: bold; }
                td { padding: 10px 12px; border-bottom: 1px solid #DDD; }
                tr:nth-child(even) { background-color: #F5F5F5; }
                .stats { background-color: #E8F5F0; padding: 15px; border-radius: 5px; }
                .stat-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #DDD; text-align: center; color: #999; font-size: 11px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Inventaire des Voitures</h1>
                <p>Système de Gestion des Locations - GoVibe</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Marque / Modèle</th>
                        <th>Année</th>
                        <th>Carburant</th>
                        <th>Prix/Jour</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    {$htmlRows}
                </tbody>
            </table>

            <div class='stats'>
                <h3 style='color: #013220; margin-top: 0;'>Statistiques</h3>
                <div class='stat-row'>
                    <strong>Total Voitures:</strong>
                    <span>" . count($voitures) . "</span>
                </div>
                <div class='stat-row'>
                    <strong>Valeur Parc (DT):</strong>
                    <span>" . number_format($totalValue, 2) . "</span>
                </div>
                <div class='stat-row'>
                    <strong>Prix Moyen (DT/jour):</strong>
                    <span>" . number_format($avgPrice, 2) . "</span>
                </div>
            </div>

            <div class='footer'>
                <p>Document généré automatiquement le " . (new \DateTime())->format('d/m/Y à H:i') . "</p>
            </div>
        </body>
        </html>
        ";
    }

    private function applyAgencyCoordinates(Voiture $voiture, ?string $agencyName): void
    {
        if (!$agencyName || !isset(self::AGENCIES[$agencyName])) {
            return;
        }

        $voiture->setAdresseAgence($agencyName);
        $voiture->setLatitude(self::AGENCIES[$agencyName]['latitude']);
        $voiture->setLongitude(self::AGENCIES[$agencyName]['longitude']);
    }

    /**
     * Calculer les statistiques
     */
    private function calculateStats(VoitureRepository $voitureRepository): array
    {
        $allVoitures = $voitureRepository->findAll();
        
        $byStatut = [];
        $byCarburant = [];
        $totalValue = 0;
        $avgPrice = 0;
        $minPrice = PHP_INT_MAX;
        $maxPrice = 0;

        foreach ($allVoitures as $voiture) {
            // Par statut
            $statut = $voiture->getStatut();
            if (!isset($byStatut[$statut])) {
                $byStatut[$statut] = 0;
            }
            $byStatut[$statut]++;

            // Par carburant
            $carburant = $voiture->getTypeCarburant();
            if (!isset($byCarburant[$carburant])) {
                $byCarburant[$carburant] = 0;
            }
            $byCarburant[$carburant]++;

            // Valeurs
            $price = floatval($voiture->getPrixJour());
            $totalValue += $price;
            $minPrice = min($minPrice, $price);
            $maxPrice = max($maxPrice, $price);
        }

        $count = count($allVoitures);
        $avgPrice = $count > 0 ? $totalValue / $count : 0;

        return [
            'totalVoitures' => $count,
            'byStatut' => $byStatut,
            'byCarburant' => $byCarburant,
            'totalValue' => $totalValue,
            'avgPrice' => $avgPrice,
            'minPrice' => $minPrice === PHP_INT_MAX ? 0 : $minPrice,
            'maxPrice' => $maxPrice,
        ];
    }
}
