<?php

namespace App\Service;

use App\Entity\Location;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeService
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function generateQrCode(string $reference): string
    {
        // Créer le répertoire s'il n'existe pas
        $uploadDir = $this->projectDir . '/public/uploads/qrcodes';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Générer le QR Code
        $qrCode = QrCode::create($reference)
            ->setSize(300)
            ->setMargin(10);

        $writer = new PngWriter();
        $fileName = 'qrcode-' . $reference . '.png';
        $filePath = $uploadDir . '/' . $fileName;

        $result = $writer->write($qrCode);
        $result->saveToFile($filePath);

        return 'uploads/qrcodes/' . $fileName;
    }
}
