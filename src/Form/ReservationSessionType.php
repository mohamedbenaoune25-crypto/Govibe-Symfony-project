<?php

namespace App\Form;

use App\Entity\ActiviteSession;
use App\Entity\ReservationSession;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Length;

class ReservationSessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('session', EntityType::class, [
                'label'        => false,
                'class'        => ActiviteSession::class,
                'choice_label' => function (ActiviteSession $session): string {
                    return sprintf(
                        '%s — %s à %s (%d places restantes)',
                        $session->getActivite()?->getName() ?? 'N/A',
                        $session->getDate()?->format('d/m/Y') ?? '?',
                        $session->getHeure()?->format('H:i') ?? '?',
                        $session->getNbrPlacesRestant() ?? 0
                    );
                },
                'placeholder' => '-- Choisir une session --',
                'constraints' => [
                    new NotBlank(['message' => 'La session est obligatoire.']),
                ],
            ])
            ->add('nbPlaces', IntegerType::class, [
                'label' => false,
                'attr'  => ['min' => 1],
                'constraints' => [
                    new NotBlank(['message' => 'Le nombre de places est obligatoire.']),
                    new Positive(['message' => 'Le nombre de places doit être positif.']),
                ],
            ])
            ->add('userRef', TextType::class, [
                'label' => false,
                'attr'  => ['placeholder' => 'Ex : USER001'],
                'constraints' => [
                    new NotBlank(['message' => 'La référence utilisateur est obligatoire.']),
                    new Length(['max' => 50]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationSession::class,
        ]);
    }
}
