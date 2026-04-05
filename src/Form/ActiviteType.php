<?php

namespace App\Form;

use App\Entity\Activite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Length(['max' => 150, 'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('type', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'Sport'       => 'Sport',
                    'Culture'     => 'Culture',
                    'Nature'      => 'Nature',
                    'Gastronomie' => 'Gastronomie',
                    'Bien-être'   => 'Bien-être',
                    'Aventure'    => 'Aventure',
                    'Autre'       => 'Autre',
                ],
                'placeholder' => '-- Choisir un type --',
                'constraints' => [
                    new NotBlank(['message' => 'Le type est obligatoire.']),
                ],
            ])
            ->add('localisation', TextType::class, [
                'label' => false,
                'constraints' => [
                    new NotBlank(['message' => 'La localisation est obligatoire.']),
                    new Length(['max' => 150]),
                ],
            ])
            ->add('prix', MoneyType::class, [
                'label' => false,
                'currency' => false,
                'attr' => ['placeholder' => '0.00'],
                'constraints' => [
                    new NotBlank(['message' => 'Le prix est obligatoire.']),
                    new Positive(['message' => 'Le prix doit être positif.']),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'Confirmé'   => 'Confirmed',
                    'En attente' => 'Pending',
                    'Annulé'     => 'Cancelled',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le statut est obligatoire.']),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activite::class,
        ]);
    }
}
