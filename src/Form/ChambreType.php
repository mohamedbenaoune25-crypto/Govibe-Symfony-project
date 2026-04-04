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
        $builder
            ->add('type', TextType::class, [
                'label' => 'Type de chambre',
                'required' => true,
            ])
            ->add('capacite', IntegerType::class, [
                'label' => 'Capacité',
                'required' => false,
            ])
            ->add('equipements', TextType::class, [
                'label' => 'Équipements',
                'required' => false,
            ])
            ->add('prixStandard', NumberType::class, [
                'label' => 'Prix standard',
                'required' => false,
            ])
            ->add('prixHauteSaison', NumberType::class, [
                'label' => 'Prix haute saison',
                'required' => false,
            ])
            ->add('prixBasseSaison', NumberType::class, [
                'label' => 'Prix basse saison',
                'required' => false,
            ])
            ->add('hotel', EntityType::class, [
                'class' => Hotel::class,
                'choice_label' => 'nom',
                'label' => 'Hôtel',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Chambre::class,
        ]);
    }
}