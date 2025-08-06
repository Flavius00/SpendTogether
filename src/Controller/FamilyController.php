<?php

namespace App\Controller;

use App\Entity\Family;
use App\Entity\User;
use App\Form\CreateFamilyFormType;
use App\Repository\FamilyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/family')]
final class FamilyController extends AbstractController
{
    #[Route('/', name: 'app_family_home')]
    public function index(
        AuthorizationCheckerInterface $authorizationChecker,
    ): Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        if (is_null($user->getFamily())) {
            return $this->redirectToRoute('app_family_create');
        }
        return $this->render('family/index.html.twig', [
            'userEmail' => $user->getEmail(),
        ]);
    }

    #[Route('/create', name: 'app_family_create')]
    public function createFamilyPage(
        Request $request,
        AuthorizationCheckerInterface $authorizationChecker,
        EntityManagerInterface $em,
    ) : Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        if (!is_null($user->getFamily())) {
            return $this->redirectToRoute('app_family_home');
        }


        $family = new Family();
        $form = $this->createForm(CreateFamilyFormType::class, $family);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($family);
            $em->flush();

            $user->setFamily($family);
            $user->setRole(['FAMILY_ADMIN']);
            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('app_family_home');
        }

        return $this->render('family/create.html.twig', [
            'createFamilyForm' => $form->createView(),
            'userEmail' => $user->getEmail(),
        ]);
    }

    #[Route('/join', name: 'app_family_join')]
    public function getFamily(
        AuthorizationCheckerInterface $authorizationChecker,
        FamilyRepository $familyRepository,
    ) : Response
    {
        if (!$authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $families = $familyRepository->findAll();
        $user = $this->getUser();


        return $this->render('family/join.html.twig', [
            'families' => $families,
            'userEmail' => $user->getEmail(),
        ]);
    }
}
