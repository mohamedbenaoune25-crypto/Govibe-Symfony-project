<?php

namespace App\Controller\Admin;

use App\Domain\Flight\Entity\Vol;
use App\Domain\Flight\Form\VolType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/vols')]
#[IsGranted('ROLE_ADMIN')]
class AdminVolController extends AbstractController
{
    #[Route('/', name: 'app_admin_vols_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $vols = $entityManager->getRepository(Vol::class)->findAll();

        $events = [];
        foreach ($vols as $v) {
            $start = $v->getDepartureTime() ? $v->getDepartureTime()->format(\DateTime::ATOM) : null;
            $end = $v->getArrivalTime() ? $v->getArrivalTime()->format(\DateTime::ATOM) : null;
            if ($start) {
                $events[] = [
                    'id' => $v->getFlightId(),
                    'title' => $v->getDepartureAirport() . ' ✈ ' . $v->getDestination(),
                    'start' => $start,
                    'end' => $end,
                    'backgroundColor' => 'rgba(0, 200, 150, 0.1)',
                    'borderColor' => '#00c896',
                    'textColor' => '#00a47a'
                ];
            }
        }

        return $this->render('admin/vols/index.html.twig', [
            'vols' => $vols,
            'calendarEvents' => json_encode($events),
        ]);
    }

    #[Route('/new', name: 'app_admin_vols_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        $vol = new Vol();
        $form = $this->createForm(VolType::class, $vol);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($vol);
            $entityManager->flush();

            $this->addFlash('success', 'Nouveau vol créé avec succès.');
            return $this->redirectToRoute('app_admin_vols_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/vols/new.html.twig', [
                'vol' => $vol,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('admin/vols/new.html.twig', [
            'vol' => $vol,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{flightId}/edit', name: 'app_admin_vols_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Vol $vol,
        EntityManagerInterface $entityManager
    ): Response
    {
        $form = $this->createForm(VolType::class, $vol);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Vol mis à jour avec succès.');
            return $this->redirectToRoute('app_admin_vols_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/vols/new.html.twig', [
                'vol' => $vol,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('admin/vols/new.html.twig', [ // We reuse the 'new' template as 'edit' usually has the same form layout
            'vol' => $vol,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{flightId}/delete', name: 'app_admin_vols_delete', methods: ['POST'])]
    public function delete(Request $request, Vol $vol, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $vol->getFlightId(), $request->request->get('_token'))) {
            $entityManager->remove($vol);
            $entityManager->flush();
            $this->addFlash('success', 'Vol supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_vols_index', [], Response::HTTP_SEE_OTHER);
    }
}
