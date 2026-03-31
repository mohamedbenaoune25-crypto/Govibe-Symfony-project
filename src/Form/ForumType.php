<?php

namespace App\Form;

use App\Entity\Forum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du Forum',
                'attr' => ['placeholder' => 'Ex: Voyage en Asie', 'class' => 'form-control rounded-pill']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['placeholder' => 'De quoi parle ce forum ?', 'class' => 'form-control rounded-4', 'rows' => 4]
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image de Couverture',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control rounded-pill']
            ])
            ->add('isPrivate', CheckboxType::class, [
                'label' => 'Rendre ce forum privé',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Forum::class,
        ]);
    }
}
