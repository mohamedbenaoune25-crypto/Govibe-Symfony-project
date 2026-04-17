<?php

namespace App\Controller\Api;

use App\Repository\PersonneRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ApiUserController extends AbstractController
{
    #[Route('/api/users/search', name: 'api_users_search', methods: ['GET'])]
    public function search(Request $request, PersonneRepository $personneRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 1) {
            return new JsonResponse([]);
        }

        $users = $personneRepository->createQueryBuilder('u')
            ->where('u.prenom LIKE :q OR u.nom LIKE :q OR u.email LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'name' => $user->getPrenom() . ' ' . $user->getNom(),
                'tag' => strtolower($user->getPrenom() . $user->getNom()),
                'photo' => $user->getPhotoUrl()
            ];
        }

        return new JsonResponse($data);
    }
}
