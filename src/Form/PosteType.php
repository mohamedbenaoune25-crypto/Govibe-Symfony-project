<?php

namespace App\Form;

use App\Entity\Forum;
use App\Entity\Personne;
use App\Entity\Poste;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PosteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contenu', null, [
                'label' => 'VOTRE MESSAGE',
                'attr' => ['placeholder' => 'Exprimez-vous...', 'maxlength' => 500]
            ])
            ->add('type', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class)
            ->add('imageFile', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'SÉLECTION MÉDIA',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'd-none'] // We will use a custom button
            ])

            ->add('localisation', null, [
                'label' => 'Localisation',
                'required' => false,
                'attr' => ['placeholder' => 'Ajouter un lieu (ex: Paris, France)']
            ])
            ->add('forum', EntityType::class, [
                'class' => Forum::class,
                'choice_label' => 'name',
                'label' => 'Forum',
                'required' => false
            ])
        ;


    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Poste::class,
        ]);
    }
}
