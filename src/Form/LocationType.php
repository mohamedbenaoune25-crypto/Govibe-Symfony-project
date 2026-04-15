<?php

namespace App\Form;

use App\Entity\Location;
use App\Entity\Voiture;
use App\Repository\VoitureRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;

class LocationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'] ?? null;
        $voitureRepository = $options['voiture_repository'] ?? null;

        $builder
            ->add('voiture', EntityType::class, [
                'class' => Voiture::class,
                'choice_label' => function(Voiture $voiture) {
                    return sprintf(
                        '%s %s (%s) - %s DT/jour',
                        $voiture->getMarque(),
                        $voiture->getModele(),
                        $voiture->getMatricule(),
                        number_format(floatval($voiture->getPrixJour()), 2)
                    );
                },
                'choice_attr' => function (Voiture $voiture): array {
                    $statut = (string) $voiture->getStatut();

                    return [
                        'data-statut' => $statut,
                        'data-is-disponible' => $statut === 'DISPONIBLE' ? '1' : '0',
                    ];
                },
                'query_builder' => function(EntityRepository $er) {
                    return $er->createQueryBuilder('v')
                        ->orderBy('v.marque', 'ASC')
                        ->addOrderBy('v.modele', 'ASC');
                },
                'placeholder' => 'Sélectionner une voiture',
                'label' => 'Voiture',
                'help' => 'Sélectionnez une voiture puis vérifiez son état avant de valider la location',
                'attr' => [
                    'class' => 'form-select',
                    'data-bs-toggle' => 'tooltip',
                    'title' => 'L\'état de la voiture sélectionnée est affiché sous le champ'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner une voiture']),
                ],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTime())->format('Y-m-d'),
                    'required' => true,
                ],
                'help' => 'La date de début de votre location',
                'constraints' => [
                    new NotBlank(['message' => 'La date de début est requise']),
                    new GreaterThan([
                        'value' => (new \DateTime())->modify('-1 day'),
                        'message' => 'La date de début ne peut pas être antérieure à aujourd\'hui',
                    ]),
                ],
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control',
                    'required' => true,
                ],
                'help' => 'La date de fin de votre location',
                'constraints' => [
                    new NotBlank(['message' => 'La date de fin est requise']),
                ],
            ])
            ->add('montantTotal', NumberType::class, [
                'label' => 'Montant total (DT)',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => 'readonly',
                    'placeholder' => 'Sera calculé automatiquement',
                    'min' => 0,
                    'step' => 0.01,
                ],
                'help' => 'Ce montant sera calculé automatiquement basé sur le prix journalier et le nombre de jours',
                'constraints' => [
                    new Type(['type' => 'float', 'message' => 'Le montant doit être un nombre']),
                    new Range([
                        'min' => 0,
                        'notInRangeMessage' => 'Le montant ne peut pas être négatif',
                    ]),
                ],
                'required' => false, // On le rend optionnel car il sera calculé
            ]);

        // Événement pour recalculer le montant et valider les dates
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event) use ($voitureRepository) {
            $data = $event->getData();
            $form = $event->getForm();

            if (!isset($data['dateDebut']) || !isset($data['dateFin']) || !isset($data['voiture'])) {
                return;
            }

            try {
                $dateDebut = new \DateTime($data['dateDebut']);
                $dateFin = new \DateTime($data['dateFin']);

                // Validation : la date de fin doit être après la date de début
                if ($dateFin <= $dateDebut) {
                    $form->get('dateFin')->addError(
                        new FormError('La date de fin doit être après la date de début')
                    );
                }

                // Calcul du nombre de jours
                $interval = $dateFin->diff($dateDebut);
                $nbJours = $interval->days + 1;

                // Récupérer le prix de la voiture depuis les données du formulaire
                if (isset($data['voiture']) && $voitureRepository instanceof VoitureRepository) {
                    $voitureId = $data['voiture'];
                    if (is_string($voitureId)) {
                        $voiture = $voitureRepository->find((int) $voitureId);
                        if ($voiture) {
                            if ($voiture->getStatut() !== 'DISPONIBLE') {
                                $form->get('voiture')->addError(
                                    new FormError('Cette voiture est ' . strtolower((string) $voiture->getStatut()) . '. Veuillez choisir une voiture disponible.')
                                );
                                $event->setData($data);
                                return;
                            }

                            $prixJour = floatval($voiture->getPrixJour());
                            $montantTotal = $prixJour * $nbJours;
                            $data['montantTotal'] = round($montantTotal, 2);
                        } else {
                            $form->get('voiture')->addError(
                                new FormError('Voiture invalide. Veuillez sélectionner une voiture disponible.')
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs en cas de dates non valides
            }

            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) {
            $location = $event->getData();
            $form = $event->getForm();

            if (!$location->getDateDebut() || !$location->getDateFin()) {
                return;
            }

            // Validation finale : la date de fin doit être après la date de début
            if ($location->getDateFin() <= $location->getDateDebut()) {
                $form->get('dateFin')->addError(
                    new FormError('La date de fin doit être après la date de début')
                );
            }

            if ($location->getVoiture() === null) {
                $form->get('voiture')->addError(
                    new FormError('Veuillez sélectionner une voiture.')
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Location::class,
            'user' => null,
            'voiture_repository' => null,
        ]);
    }

}
