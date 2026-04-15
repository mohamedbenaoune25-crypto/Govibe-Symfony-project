<?php

namespace App\Form;

use App\Entity\Hotel;
use Symfony\Component\Validator\Constraints as Assert;
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
                'invalid_message' => 'Veuillez saisir un nom valide.',
                'trim' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom de l\'hôtel est obligatoire.']),
                    new Assert\Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => true,
                'invalid_message' => 'Veuillez saisir une adresse valide.',
                'trim' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'adresse est obligatoire.']),
                    new Assert\Length(['min' => 5, 'max' => 150]),
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => true,
                'invalid_message' => 'Veuillez saisir une ville valide.',
                'trim' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La ville est obligatoire.']),
                    new Assert\Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('nombreEtoiles', IntegerType::class, [
                'label' => 'Nombre d\'étoiles',
                'required' => false,
                'invalid_message' => 'Le nombre d\'étoiles doit être un entier.',
                'constraints' => [
                    new Assert\Range(['min' => 1, 'max' => 5]),
                ],
                'attr' => [
                    'min' => 1,
                    'max' => 5,
                ],
            ])
            ->add('budget', NumberType::class, [
                'label' => 'Budget',
                'required' => false,
                'invalid_message' => 'Le budget doit être un nombre valide.',
                'constraints' => [
                    new Assert\PositiveOrZero(),
                ],
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'invalid_message' => 'Veuillez saisir une description valide.',
                'trim' => true,
                'constraints' => [
                    new Assert\Length(['max' => 2000]),
                ],
            ])
            ->add('photoUrl', TextType::class, [
                'label' => 'URL de la photo',
                'required' => false,
                'invalid_message' => 'Veuillez saisir une URL valide.',
                'trim' => true,
                'constraints' => [
                    new Assert\Url(),
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