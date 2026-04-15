<?php

namespace App\Controller;

use App\Entity\Chambre;
use App\Form\ChambreType;
use App\Repository\ChambreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chambre')]
class ChambreController extends AbstractController
{
    #[Route('/', name: 'app_chambre_index', methods: ['GET'])]
    public function index(ChambreRepository $chambreRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sortBy', 'type');
        $sortDir = $request->query->get('sortDir', 'ASC');
        $allowedSortBy = ['type', 'capacite', 'nombreDeChambres', 'prixStandard', 'prixHauteSaison', 'prixBasseSaison', 'createdAt'];

        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'type';
        }

        $sortDir = strtoupper((string) $sortDir) === 'DESC' ? 'DESC' : 'ASC';

        if ($search) {
            $chambres = $chambreRepository->searchChambres($search, $sortBy, $sortDir);
        } else {
            $chambres = $chambreRepository->findAllSorted($sortBy, $sortDir);
        }

        return $this->render('chambre/index.html.twig', [
            'chambres' => $chambres,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    #[Route('/new', name: 'app_chambre_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $chambre = new Chambre();
        $form = $this->createForm(ChambreType::class, $chambre);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->validateChambreInput($chambre, $form);

            if ($form->isValid()) {
                $entityManager->persist($chambre);
                $entityManager->flush();

                return $this->redirectToRoute('app_chambre_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('chambre/new.html.twig', [
            'chambre' => $chambre,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_chambre_show', methods: ['GET'])]
    public function show(Chambre $chambre): Response
    {
        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$chambre->getId(),
        ])
            ->setAction($this->generateUrl('app_chambre_delete', ['id' => $chambre->getId()]))
            ->setMethod('POST')
            ->getForm();

        return $this->render('chambre/show.html.twig', [
            'chambre' => $chambre,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_chambre_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Chambre $chambre, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ChambreType::class, $chambre);
        $form->handleRequest($request);

        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$chambre->getId(),
        ])
            ->setAction($this->generateUrl('app_chambre_delete', ['id' => $chambre->getId()]))
            ->setMethod('POST')
            ->getForm();

        if ($form->isSubmitted()) {
            $this->validateChambreInput($chambre, $form);

            if ($form->isValid()) {
                $entityManager->flush();

                return $this->redirectToRoute('app_chambre_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('chambre/edit.html.twig', [
            'chambre' => $chambre,
            'form' => $form,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_chambre_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Chambre $chambre, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$chambre->getId(), $request->request->get('_token'))) {
            $entityManager->remove($chambre);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_chambre_index', [], Response::HTTP_SEE_OTHER);
    }

    private function validateChambreInput(Chambre $chambre, FormInterface $form): void
    {
        if (trim((string) $chambre->getType()) === '') {
            $form->get('type')->addError(new FormError('Le type de chambre est obligatoire.'));
        }

        if ($chambre->getCapacite() === null || $chambre->getCapacite() <= 0) {
            $form->get('capacite')->addError(new FormError('La capacite doit etre un entier superieur a 0.'));
        }

        if ($chambre->getNombreDeChambres() === null || $chambre->getNombreDeChambres() <= 0) {
            $form->get('nombreDeChambres')->addError(new FormError('Le nombre de chambres doit etre un entier superieur a 0.'));
        }

        if ($chambre->getPrixStandard() !== null && $chambre->getPrixStandard() < 0) {
            $form->get('prixStandard')->addError(new FormError('Le prix standard doit etre positif ou nul.'));
        }

        if ($chambre->getPrixHauteSaison() !== null && $chambre->getPrixHauteSaison() < 0) {
            $form->get('prixHauteSaison')->addError(new FormError('Le prix haute saison doit etre positif ou nul.'));
        }

        if ($chambre->getPrixBasseSaison() !== null && $chambre->getPrixBasseSaison() < 0) {
            $form->get('prixBasseSaison')->addError(new FormError('Le prix basse saison doit etre positif ou nul.'));
        }

        if ($form->has('hotel') && $chambre->getHotel() === null) {
            $form->get('hotel')->addError(new FormError("L'hotel associe est obligatoire."));
        }
    }
}