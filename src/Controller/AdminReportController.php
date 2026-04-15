<?php

namespace App\Controller;

use App\Service\AdminReportExportService;
use App\Service\AdminStatsService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_ADMIN')]
class AdminReportController extends AbstractController
{
    #[Route('/statistics/pdf', name: 'app_admin_reports_statistics_pdf', methods: ['GET'])]
    public function exportStatisticsPdf(AdminStatsService $adminStatsService): Response
    {
        $reportData = $adminStatsService->getDetailedReportData();

        $previousErrorHandler = set_error_handler(
            static function (int $severity, string $message): bool {
                if (str_contains($message, 'iconv():')
                    && str_contains($message, 'incomplete multibyte character')) {
                    return true;
                }

                return false;
            },
            E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE
        );

        try {
            $options = new Options();
            // Use a core PDF font to avoid font metadata parsing issues in some environments.
            $options->set('defaultFont', 'Helvetica');
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new Dompdf($options);
            $html = $this->renderView('admin/report/statistics_pdf.html.twig', [
                'reportData' => $reportData,
                'generatedAt' => new \DateTimeImmutable(),
            ]);
            $html = $this->forceValidUtf8($html);

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
        } finally {
            restore_error_handler();
        }

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rapport-statistiques-admin.pdf"',
        ]);
    }

    #[Route('/statistics/excel', name: 'app_admin_reports_statistics_excel', methods: ['GET'])]
    public function exportStatisticsExcel(AdminStatsService $adminStatsService, AdminReportExportService $reportExportService): Response
    {
        $reportData = $adminStatsService->getDetailedReportData();
        $excelXml = $reportExportService->buildExcelXml($reportData);

        return new Response($excelXml, Response::HTTP_OK, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rapport-statistiques-admin.xls"',
        ]);
    }

    private function forceValidUtf8(string $value): string
    {
        $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return '';
        }

        $decoded = json_decode($json, true);
        if (!is_string($decoded)) {
            return '';
        }

        return $decoded;
    }
}
