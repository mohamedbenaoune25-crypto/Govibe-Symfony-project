<?php

namespace App\Controller;

use App\Entity\Vol;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/vols')]
class VolController extends AbstractController
{
    #[Route('/', name: 'app_vols_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $repository = $entityManager->getRepository(Vol::class);
        $searchDestination = $request->query->get('destination');
        $searchOrigin = $request->query->get('origin');

        $queryBuilder = $repository->createQueryBuilder('v')
            ->orderBy('v.departureTime', 'ASC');

        if ($searchDestination) {
            $queryBuilder->andWhere('v.destination LIKE :destination')
                         ->setParameter('destination', '%' . $searchDestination . '%');
        }

        if ($searchOrigin) {
            $queryBuilder->andWhere('v.departureAirport LIKE :origin')
                         ->setParameter('origin', '%' . $searchOrigin . '%');
        }

        $vols = $queryBuilder->getQuery()->getResult();

        return $this->render('vol/index.html.twig', [
            'vols' => $vols,
            'searchDestination' => $searchDestination,
            'searchOrigin' => $searchOrigin,
        ]);
    }
}
