<?php

namespace App\Form;

use App\Entity\Hotel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class HotelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'hôtel',
                'required' => true,
                'invalid_message' => 'Format invalide.',
                'trim' => true,
                'attr' => [
                    'maxlength' => 100,
                ],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => true,
                'invalid_message' => 'Format invalide.',
                'trim' => true,
                'attr' => [
                    'maxlength' => 150,
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => true,
                'invalid_message' => 'Format invalide.',
                'trim' => true,
                'attr' => [
                    'maxlength' => 100,
                ],
            ])
            ->add('nombreEtoiles', IntegerType::class, [
                'label' => 'Nombre d\'étoiles',
                'required' => false,
                'invalid_message' => 'Nombre entier attendu.',
                'attr' => [
                    'min' => 1,
                    'max' => 5,
                ],
            ])
            ->add('budget', NumberType::class, [
                'label' => 'Budget',
                'required' => false,
                'invalid_message' => 'Nombre valide attendu.',
                'attr' => [
                    'min' => 0,
                    'max' => 1000000,
                    'step' => '0.01',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'invalid_message' => 'Format invalide.',
                'trim' => true,
                'attr' => [
                    'maxlength' => 2000,
                ],
            ])
            ->add('photoUrl', TextType::class, [
                'label' => 'URL de la photo',
                'required' => false,
                'invalid_message' => 'URL invalide.',
                'trim' => true,
                'attr' => [
                    'maxlength' => 255,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Hotel::class,
        ]);
    }
}