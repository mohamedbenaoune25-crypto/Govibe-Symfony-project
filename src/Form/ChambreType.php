<?php

namespace App\Form;

use App\Entity\Chambre;
use App\Entity\Hotel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class ChambreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $hotel = $options['hotel'];

        $builder
            ->add('type', TextType::class, [
                'label' => 'Type de chambre',
                'required' => true,
                'invalid_message' => 'Veuillez saisir un type de chambre valide.',
                'trim' => true,
                'attr' => [
                    'maxlength' => 100,
                ],
            ])
            ->add('capacite', IntegerType::class, [
                'label' => 'Capacité',
                'required' => true,
                'invalid_message' => 'La capacité doit être un entier.',
                'attr' => [
                    'min' => 1,
                    'max' => 20,
                ],
            ])
            ->add('nombreDeChambres', IntegerType::class, [
                'label' => 'Nombre de chambres',
                'required' => true,
                'invalid_message' => 'Le nombre de chambres doit être un entier.',
                'attr' => [
                    'min' => 1,
                    'max' => 500,
                ],
            ])
            ->add('equipements', TextType::class, [
                'label' => 'Équipements',
                'required' => false,
                'invalid_message' => 'Veuillez saisir des équipements valides.',
                'trim' => true,
                'attr' => [
                    'maxlength' => 255,
                ],
            ])
            ->add('prixStandard', NumberType::class, [
                'label' => 'Prix standard',
                'required' => false,
                'invalid_message' => 'Le prix standard doit être un nombre valide.',
                'attr' => [
                    'min' => 0,
                    'max' => 100000,
                    'step' => '0.01',
                ],
            ])
            ->add('prixHauteSaison', NumberType::class, [
                'label' => 'Prix haute saison',
                'required' => false,
                'invalid_message' => 'Le prix haute saison doit être un nombre valide.',
                'attr' => [
                    'min' => 0,
                    'max' => 100000,
                    'step' => '0.01',
                ],
            ])
            ->add('prixBasseSaison', NumberType::class, [
                'label' => 'Prix basse saison',
                'required' => false,
                'invalid_message' => 'Le prix basse saison doit être un nombre valide.',
                'attr' => [
                    'min' => 0,
                    'max' => 100000,
                    'step' => '0.01',
                ],
            ])
        ;

        if (!$hotel instanceof Hotel) {
            $builder->add('hotel', EntityType::class, [
                'class' => Hotel::class,
                'choice_label' => 'nom',
                'label' => 'Hôtel',
                'required' => true,
                'placeholder' => 'Sélectionnez un hôtel',
                'invalid_message' => 'Veuillez sélectionner un hôtel valide.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Chambre::class,
            'hotel' => null,
        ]);

        $resolver->setAllowedTypes('hotel', ['null', Hotel::class]);
    }
}