<?php
namespace App\Form;

use App\Entity\Activite;
use App\Entity\ActiviteSession;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActiviteSessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de la session',
                'attr' => [
                    'class' => 'form-control rounded-pill',
                    'min' => date('Y-m-d')
                ]
            ])
            ->add('heure', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Heure de début',
                'attr' => ['class' => 'form-control rounded-pill']
            ])
            ->add('capacite', IntegerType::class, [
                'label' => 'Capacité maximale',
                'attr' => ['min' => 1, 'class' => 'form-control rounded-pill', 'placeholder' => 'Ex: 20']
            ])
            ->add('nbrPlacesRestant', IntegerType::class, [
                'label' => 'Nombre de places restantes',
                'attr' => ['min' => 0, 'class' => 'form-control rounded-pill']
            ])
            ->add('activite', EntityType::class, [
                'class' => Activite::class,
                'choice_label' => 'name',
                'label' => 'Activité associée',
                'attr' => ['class' => 'form-select rounded-pill']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActiviteSession::class,
        ]);
    }
}
