<?php

namespace App\Form;

use App\Entity\Reclamation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sujet', TextType::class, [
                'label' => 'Sujet de votre réclamation',
                'attr' => ['placeholder' => 'Ex: Problème avec une réservation'],
                'constraints' => [new NotBlank()]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Détails de la réclamation',
                'attr' => ['placeholder' => 'Expliquez en détail votre problème...', 'rows' => 6],
                'constraints' => [new NotBlank()]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
        ]);
    }
}
