<?php

namespace App\Form;

use App\Entity\Voiture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;

class VoitureType extends AbstractType
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

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('matricule', TextType::class, [
                'label' => 'Matricule',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: TUN 123 TN',
                    'maxlength' => 20,
                    'pattern' => '^[A-Za-z0-9\-\s]{4,20}$',
                ],
                'help' => 'Format tunisien recommandé',
                'constraints' => [
                    new NotBlank(['message' => 'Le matricule est requis']),
                    new Length([
                        'min' => 4,
                        'max' => 20,
                        'minMessage' => 'Le matricule doit avoir au moins 4 caractères',
                        'maxMessage' => 'Le matricule ne doit pas dépasser 20 caractères',
                    ]),
                    new Regex([
                        'pattern' => '/^[A-Za-z0-9\-\s]{4,20}$/',
                        'message' => 'Le matricule contient des caractères invalides',
                    ]),
                ],
            ])
            ->add('marque', TextType::class, [
                'label' => 'Marque',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Toyota',
                    'maxlength' => 50,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La marque est requise']),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'La marque doit avoir au moins 2 caractères',
                        'maxMessage' => 'La marque ne doit pas dépasser 50 caractères',
                    ]),
                    new Regex([
                        'pattern' => '/^[\p{L}0-9\-\s]{2,50}$/u',
                        'message' => 'La marque contient des caractères invalides',
                    ]),
                ],
            ])
            ->add('modele', TextType::class, [
                'label' => 'Modèle',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Corolla',
                    'maxlength' => 50,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le modèle est requis']),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le modèle doit avoir au moins 2 caractères',
                        'maxMessage' => 'Le modèle ne doit pas dépasser 50 caractères',
                    ]),
                    new Regex([
                        'pattern' => '/^[\p{L}0-9\-\s]{2,50}$/u',
                        'message' => 'Le modèle contient des caractères invalides',
                    ]),
                ],
            ])
            ->add('annee', IntegerType::class, [
                'label' => 'Année',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 2000,
                    'max' => date('Y'),
                ],
                'help' => 'Année de fabrication',
                'constraints' => [
                    new NotBlank(['message' => 'L\'année est requise']),
                    new Range([
                        'min' => 2000,
                        'max' => (int) date('Y'),
                        'notInRangeMessage' => 'L\'année doit être entre {{ min }} et {{ max }}',
                    ]),
                ],
            ])
            ->add('typeCarburant', ChoiceType::class, [
                'label' => 'Type de Carburant',
                'choices' => [
                    'Essence' => 'Essence',
                    'Diesel' => 'Diesel',
                    'Électrique' => 'Électrique',
                    'Hybride' => 'Hybride',
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Sélectionner un carburant',
                'constraints' => [
                    new NotBlank(['message' => 'Le type de carburant est requis']),
                ],
            ])
            ->add('prixJour', NumberType::class, [
                'label' => 'Prix par Jour (DT)',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0.01,
                    'step' => 0.01,
                ],
                'help' => 'Prix journalier de location',
                'constraints' => [
                    new NotBlank(['message' => 'Le prix est requis']),
                    new Type(['type' => 'float', 'message' => 'Le prix doit être un nombre']),
                    new Positive(['message' => 'Le prix doit être positif']),
                ],
            ])
            ->add('adresseAgence', ChoiceType::class, [
                'label' => 'Agence',
                'choices' => array_combine(array_keys(self::AGENCIES), array_keys(self::AGENCIES)),
                'placeholder' => 'Sélectionner une agence',
                'attr' => [
                    'class' => 'form-select',
                ],
                'choice_attr' => function (?string $choice, string $label, string $value): array {
                    $agency = self::AGENCIES[$value] ?? null;

                    return $agency ? [
                        'data-latitude' => $agency['latitude'],
                        'data-longitude' => $agency['longitude'],
                    ] : [];
                },
                'help' => 'Choisissez une agence pour remplir automatiquement les coordonnées',
                'constraints' => [
                    new NotBlank(['message' => 'L\'adresse est requise']),
                ],
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'scale' => 8,
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 36.8065',
                    'readonly' => true,
                ],
                'help' => 'Pour la géolocalisation',
                'constraints' => [
                    new NotBlank(['message' => 'La latitude est requise']),
                    new Range([
                        'min' => -90,
                        'max' => 90,
                        'notInRangeMessage' => 'Latitude invalide',
                    ]),
                ],
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'scale' => 8,
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 10.1953',
                    'readonly' => true,
                ],
                'help' => 'Pour la géolocalisation',
                'constraints' => [
                    new NotBlank(['message' => 'La longitude est requise']),
                    new Range([
                        'min' => -180,
                        'max' => 180,
                        'notInRangeMessage' => 'Longitude invalide',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Caractéristiques spéciales, équipements, etc...',
                ],
                'help' => 'Informations supplémentaires optionnelles',
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'La description ne doit pas dépasser 1000 caractères',
                    ]),
                ],
            ])
            ->add('imageUrl', UrlType::class, [
                'label' => 'URL de l\'Image',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://...',
                ],
                'help' => 'Lien vers une image de la voiture',
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Disponible' => 'DISPONIBLE',
                    'En Maintenance' => 'EN_MAINTENANCE',
                    'Accidentée' => 'ACCIDENTE',
                ],
                'attr' => ['class' => 'form-select'],
                'help' => 'État actuel de la voiture',
                'constraints' => [
                    new NotBlank(['message' => 'Le statut est requis']),
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();

            if (!is_array($data)) {
                return;
            }

            $agencyName = $data['adresseAgence'] ?? null;
            if (!$agencyName || !isset(self::AGENCIES[$agencyName])) {
                return;
            }

            $data['latitude'] = self::AGENCIES[$agencyName]['latitude'];
            $data['longitude'] = self::AGENCIES[$agencyName]['longitude'];
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voiture::class,
        ]);
    }
}
