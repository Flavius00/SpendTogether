<?php

namespace App\Controller;

use App\Controller\Service\ThresholdService;
use App\Entity\Thresholds;
use App\Entity\User;
use App\Form\CreateThresholdFormType;
use App\Form\EditThresholdFormType;
use App\Repository\CategoryRepository;
use App\Repository\ThresholdsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/thresholds')]
final class ThresholdsController extends AbstractController
{
    #[Route('/add', name: 'app_threshold_add')]
    public function index(
        Request $request,
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        EntityManagerInterface $em,
        ThresholdsRepository $thresholdsRepository,
        ThresholdService $thresholdService,
    ): Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $threshold = new Thresholds();
        $form = $this->createForm(CreateThresholdFormType::class, $threshold);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $thresholds = $thresholdsRepository->findBy([
                'category' => $threshold->getCategory(),
                'family' => $user->getFamily(),
            ]);

            if (!empty($thresholds)) {
                $this->addFlash('warning', 'Threshold for this category already exists!');
                return $this->redirectToRoute('app_family_home');
            }

            if ($threshold->getAmount() <= 0) {
                $this->addFlash('error', 'Threshold amount must be greater than zero!');
                return $this->redirectToRoute('app_family_home');
            }

            if (!$thresholdService->validateThresholdAmount($threshold->getAmount(), $thresholds, $user->getFamily())) {
                $this->addFlash('error', 'Invalid threshold value, the total exceeds the monthly budget!');
                return $this->redirectToRoute('app_family_home');
            }

            $threshold->setFamily($user->getFamily());
            $em->persist($threshold);
            $em->flush();

            $this->addFlash('success', 'Threshold added successfully!');
            return $this->redirectToRoute('app_family_home');
        }

        return $this->render('thresholds/add-threshold.html.twig', [
            'createThresholdForm' => $form->createView(),
            'userEmail' => $user->getEmail(),
        ]);
    }

    #[Route('/edit/{thresholdId}', name: 'app_threshold_edit')]
    public function edit(
        int $thresholdId,
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        Request $request,
        ThresholdsRepository $thresholdsRepository,
        EntityManagerInterface $em,
    ): Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        if (!$authCheck->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'You do not have permission to edit thresholds.');
            return $this->redirectToRoute('app_family_home');
        }

        $threshold = $thresholdsRepository->find($thresholdId);
        $form = $this->createForm(EditThresholdFormType::class, $threshold);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($threshold);
            $em->flush();

            $this->addFlash('success', 'Threshold updated successfully!');
            return $this->redirectToRoute('app_family_home');
        }

        return $this->render('thresholds/edit-threshold.html.twig', [
            'editThresholdForm' => $form->createView(),
            'userEmail' => $user->getEmail(),
        ]);
    }

    #[Route('/delete/{thresholdId}', name: 'app_threshold_delete')]
    public function delete(
        int $thresholdId,
        AuthorizationCheckerInterface $authCheck,
        ThresholdsRepository $thresholdsRepository,
        EntityManagerInterface $em,
    ): Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        if (!$authCheck->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'You do not have permission to delete thresholds.');
            return $this->redirectToRoute('app_family_home');
        }

        $threshold = $thresholdsRepository->find($thresholdId);
        if ($threshold) {
            $em->remove($threshold);
            $em->flush();
            $this->addFlash('success', 'Threshold deleted successfully!');
        } else {
            $this->addFlash('error', 'Threshold not found.');
        }

        return $this->redirectToRoute('app_family_home');
    }
}
