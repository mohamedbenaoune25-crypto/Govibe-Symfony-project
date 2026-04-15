<?php

namespace App\Controller;

use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/holidays')]
#[IsGranted('ROLE_ADMIN')]
class AdminHolidayController extends AbstractController
{
    public function __construct(
        private HolidayService $holidayService,
        private string $projectDir,
    ) {
    }

    #[Route('/', name: 'app_admin_holidays', methods: ['GET'])]
    public function index(): Response
    {
        $holidays = $this->holidayService->getHolidays();
        $supplementPercentage = $this->holidayService->getSupplementPercentage();

        return $this->render('admin/holiday/index.html.twig', [
            'holidays' => $holidays,
            'supplementPercentage' => $supplementPercentage,
        ]);
    }

    #[Route('/supplement', name: 'app_admin_holidays_supplement', methods: ['POST'])]
    public function updateSupplement(Request $request): Response
    {
        $percentage = (float) ($request->request->get('percentage', 0));

        if ($percentage < 0 || $percentage > 100) {
            $this->addFlash('error', 'Le pourcentage doit etre entre 0 et 100.');
            return $this->redirectToRoute('app_admin_holidays', [], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('update_supplement', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Requete invalide.');
            return $this->redirectToRoute('app_admin_holidays', [], Response::HTTP_SEE_OTHER);
        }

        $this->holidayService->setSupplementPercentage($percentage);
        $this->holidayService->saveHolidays($this->projectDir);

        $this->addFlash('success', sprintf('Supplement mis a jour: %s%%', $percentage));
        return $this->redirectToRoute('app_admin_holidays', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/add', name: 'app_admin_holidays_add', methods: ['POST'])]
    public function addHoliday(Request $request): Response
    {
        $date = (string) $request->request->get('date', '');
        $name = (string) $request->request->get('name', '');

        if (!$this->isCsrfTokenValid('add_holiday', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Requete invalide.');
            return $this->redirectToRoute('app_admin_holidays', [], Response::HTTP_SEE_OTHER);
        }

        if (empty($date) || empty($name)) {
            $this->addFlash('error', 'La date et le nom sont obligatoires.');
            return $this->redirectToRoute('app_admin_holidays', [], Response::HTTP_SEE_OTHER);
        }

        try {
            $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($dateObj === false) {
                throw new \Exception('Format de date invalide.');
            }

            $this->holidayService->addHoliday($date, $name);
            $this->holidayService->saveHolidays($this->projectDir);

            $this->addFlash('success', sprintf('Jour ferie ajoute: %s', $name));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'ajout: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_holidays', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/remove/{date}', name: 'app_admin_holidays_remove', methods: ['POST'])]
    public function removeHoliday(string $date, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('remove_holiday_' . $date, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Requete invalide.');
            return $this->redirectToRoute('app_admin_holidays', [], Response::HTTP_SEE_OTHER);
        }

        try {
            // Valider le format de date
            $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($dateObj === false) {
                throw new \Exception('Format de date invalide.');
            }

            $this->holidayService->removeHoliday($date);
            $this->holidayService->saveHolidays($this->projectDir);

            $this->addFlash('success', sprintf('Jour ferie supprime: %s', $date));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_holidays', [], Response::HTTP_SEE_OTHER);
    }
}
