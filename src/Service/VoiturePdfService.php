<?php

namespace App\Service;

use App\Entity\Voiture;

class VoiturePdfService
{
    public function generateVoiturePdf(Voiture $voiture): string
    {
        return $this->generateHtml($voiture);
    }

    private function generateHtml(Voiture $voiture): string
    {
        $dateCreation = $voiture->getDateCreation() ? $voiture->getDateCreation()->format('d/m/Y H:i') : 'N/A';
        $statutColor = match($voiture->getStatut()) {
            'DISPONIBLE' => '#50C878',
            'EN_MAINTENANCE' => '#FF9500',
            'ACCIDENTE' => '#E74C3C',
            default => '#95A5A6'
        };
        $statut = $this->getStatutLabel($voiture->getStatut());

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                    color: #333;
                }
                .container {
                    width: 100%;
                    padding: 30px;
                    box-sizing: border-box;
                }
                .header {
                    text-align: center;
                    border-bottom: 3px solid #50C878;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }
                .header h1 {
                    margin: 0;
                    color: #013220;
                    font-size: 28px;
                }
                .header p {
                    margin: 5px 0 0 0;
                    color: #2E8B57;
                    font-size: 14px;
                }
                .section {
                    margin-bottom: 30px;
                }
                .section h2 {
                    font-size: 16px;
                    color: #013220;
                    background-color: #E8F5F0;
                    padding: 10px 15px;
                    margin: 0 0 15px 0;
                    border-left: 4px solid #50C878;
                }
                .section-content {
                    padding: 0 15px;
                }
                .row {
                    display: flex;
                    margin-bottom: 15px;
                    gap: 30px;
                }
                .col {
                    flex: 1;
                }
                .label {
                    font-weight: bold;
                    color: #013220;
                    font-size: 12px;
                    display: block;
                    margin-bottom: 3px;
                }
                .value {
                    color: #555;
                    font-size: 14px;
                    padding: 8px;
                    background-color: #FAFAFA;
                    border-radius: 4px;
                }
                .badge {
                    display: inline-block;
                    padding: 6px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: bold;
                    color: white;
                    background-color: {$statutColor};
                }
                .grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                }
                .footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #DDD;
                    text-align: center;
                    color: #999;
                    font-size: 11px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Détails de la Voiture</h1>
                    <p>Système de Gestion des Locations - GoVibe</p>
                </div>

                <div class='section'>
                    <h2>Informations Générales</h2>
                    <div class='section-content'>
                        <div class='row'>
                            <div class='col'>
                                <label class='label'>Matricule</label>
                                <div class='value'>{$voiture->getMatricule()}</div>
                            </div>
                            <div class='col'>
                                <label class='label'>Statut</label>
                                <div class='value'><span class='badge'>{$statut}</span></div>
                            </div>
                        </div>
                        <div class='row'>
                            <div class='col'>
                                <label class='label'>Marque</label>
                                <div class='value'>{$voiture->getMarque()}</div>
                            </div>
                            <div class='col'>
                                <label class='label'>Modèle</label>
                                <div class='value'>{$voiture->getModele()}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class='section'>
                    <h2>Spécifications Technique</h2>
                    <div class='section-content'>
                        <div class='row'>
                            <div class='col'>
                                <label class='label'>Année</label>
                                <div class='value'>{$voiture->getAnnee()}</div>
                            </div>
                            <div class='col'>
                                <label class='label'>Type de Carburant</label>
                                <div class='value'>{$voiture->getTypeCarburant()}</div>
                            </div>
                        </div>
                        <div class='row'>
                            <div class='col'>
                                <label class='label'>Prix par Jour</label>
                                <div class='value'>{$voiture->getPrixJour()} DT</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class='section'>
                    <h2>Informations Agence</h2>
                    <div class='section-content'>
                        <div class='row'>
                            <div class='col'>
                                <label class='label'>Adresse Agence</label>
                                <div class='value'>{$voiture->getAdresseAgence()}</div>
                            </div>
                        </div>
                        " . ($voiture->getLatitude() && $voiture->getLongitude() ? "
                        <div class='row'>
                            <div class='col'>
                                <label class='label'>Coordonnées GPS</label>
                                <div class='value'>{$voiture->getLatitude()}, {$voiture->getLongitude()}</div>
                            </div>
                        </div>
                        " : "") . "
                    </div>
                </div>

                " . ($voiture->getDescription() ? "
                <div class='section'>
                    <h2>Description</h2>
                    <div class='section-content'>
                        <div class='value'>" . nl2br($voiture->getDescription()) . "</div>
                    </div>
                </div>
                " : "") . "

                <div class='section'>
                    <h2>Informations Système</h2>
                    <div class='section-content'>
                        <div class='row'>
                            <div class='col'>
                                <label class='label'>Date de Création</label>
                                <div class='value'>{$dateCreation}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class='footer'>
                    <p>Document généré automatiquement par le système GoVibe - Gestion des Locations</p>
                    <p>Imprimé le " . (new \DateTime())->format('d/m/Y à H:i') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getStatutLabel(string $statut): string
    {
        return match($statut) {
            'DISPONIBLE' => 'Disponible',
            'EN_MAINTENANCE' => 'En Maintenance',
            'ACCIDENTE' => 'Accidentée',
            default => $statut
        };
    }
}
