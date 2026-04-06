<?php

namespace App\Controller;

use App\Entity\Chambre;
use App\Entity\Hotel;
use App\Form\ChambreType;
use App\Repository\ChambreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/chambres')]
#[IsGranted('ROLE_ADMIN')]
class AdminChambreController extends AbstractController
{
    #[Route('/', name: 'app_admin_chambres_index', methods: ['GET'])]
    public function index(Request $request, ChambreRepository $chambreRepository): Response
    {
        $search = $request->query->get('search', '');
        $hotelId = $request->query->getInt('hotel', 0);
        $sortBy = $request->query->get('sortBy', 'type');
        $sortDir = $request->query->get('sortDir', 'ASC');

        if ($hotelId <= 0) {
            return $this->redirectToRoute('app_admin_hotels_index', [], Response::HTTP_SEE_OTHER);
        }

        $queryBuilder = $chambreRepository->createQueryBuilder('c')
            ->leftJoin('c.hotel', 'h')
            ->addSelect('h');

        if ($hotelId > 0) {
            $queryBuilder->andWhere('h.id = :hotelId')
                ->setParameter('hotelId', $hotelId);
        }

        if ($search) {
            $queryBuilder->andWhere('c.type LIKE :search OR h.nom LIKE :search OR c.equipements LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $validSortFields = ['type', 'capacite', 'prixStandard', 'prixHauteSaison', 'prixBasseSaison', 'createdAt'];
        if (!in_array($sortBy, $validSortFields, true)) {
            $sortBy = 'type';
        }

        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $chambres = $queryBuilder
            ->orderBy('c.' . $sortBy, $sortDir)
            ->getQuery()
            ->getResult();

        $hotel = $queryBuilder->getEntityManager()->getRepository('App\\Entity\\Hotel')->find($hotelId);

        $quickAddForm = null;
        if ($hotel instanceof Hotel) {
            $quickAddForm = $this->createForm(ChambreType::class, new Chambre(), [
                'hotel' => $hotel,
                'action' => $this->generateUrl('app_admin_chambres_new', ['hotel' => $hotel->getId()]),
                'method' => 'POST',
            ]);
        }

        return $this->render('admin/chambre/index.html.twig', [
            'chambres' => $chambres,
            'search' => $search,
            'hotel' => $hotel,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'quick_add_form' => $quickAddForm?->createView(),
        ]);
    }

    #[Route('/new', name: 'app_admin_chambres_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $hotelId = $request->query->getInt('hotel', 0);
        $selectedHotel = $hotelId > 0 ? $entityManager->getRepository(Hotel::class)->find($hotelId) : null;

        $chambre = new Chambre();
        $form = $this->createForm(ChambreType::class, $chambre, [
            'hotel' => $selectedHotel,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($selectedHotel instanceof Hotel) {
                $chambre->setHotel($selectedHotel);
            }

            $entityManager->persist($chambre);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'chambre' => [
                        'id' => $chambre->getId(),
                        'type' => $chambre->getType() ?: '-',
                        'hotel' => $chambre->getHotel()?->getNom() ?: '-',
                        'capacite' => $chambre->getCapacite() ?? '-',
                        'prixStandard' => $chambre->getPrixStandard() ?? '-',
                    ],
                ]);
            }

            if ($selectedHotel instanceof Hotel) {
                return $this->redirectToRoute('app_admin_chambres_index', ['hotel' => $selectedHotel->getId()], Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_admin_chambres_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le formulaire contient des erreurs.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('admin/chambre/new.html.twig', [
            'chambre' => $chambre,
            'form' => $form,
            'hotel' => $selectedHotel,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_chambres_show', methods: ['GET'])]
    public function show(Chambre $chambre): Response
    {
        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$chambre->getId(),
        ])
            ->setAction($this->generateUrl('app_admin_chambres_delete', ['id' => $chambre->getId()]))
            ->setMethod('POST')
            ->getForm();

        return $this->render('admin/chambre/show.html.twig', [
            'chambre' => $chambre,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_chambres_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Chambre $chambre, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ChambreType::class, $chambre);
        $form->handleRequest($request);

        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$chambre->getId(),
        ])
            ->setAction($this->generateUrl('app_admin_chambres_delete', ['id' => $chambre->getId()]))
            ->setMethod('POST')
            ->getForm();

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_chambres_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/chambre/edit.html.twig', [
            'chambre' => $chambre,
            'form' => $form,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_chambres_delete', methods: ['POST'])]
    public function delete(Request $request, Chambre $chambre, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$chambre->getId(), $request->request->get('_token'))) {
            $entityManager->remove($chambre);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_chambres_index', [], Response::HTTP_SEE_OTHER);
    }
}