<?php

namespace App\Controller\Web;

use App\Domain\Flight\Entity\Vol;
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

        $queryBuilder = $repository->createQueryBuilder('v');

        if ($searchDestination) {
            $queryBuilder->andWhere('v.destination LIKE :destination')
                         ->setParameter('destination', '%' . $searchDestination . '%');
        }

        if ($searchOrigin) {
            $queryBuilder->andWhere('v.departureAirport LIKE :origin')
                         ->setParameter('origin', '%' . $searchOrigin . '%');
        }

        $sort = $request->query->get('sort', 'time');
        $dir = strtoupper($request->query->get('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        if ($sort === 'price') {
            $queryBuilder->orderBy('v.prix', $dir);
        } else {
            $queryBuilder->orderBy('v.departureTime', $dir);
        }

        $vols = $queryBuilder->getQuery()->getResult();

        return $this->render('vol/index.html.twig', [
            'vols' => $vols,
            'searchDestination' => $searchDestination,
            'searchOrigin' => $searchOrigin,
            'currentSort' => $sort,
            'currentDir' => $dir,
        ]);
    }
}
