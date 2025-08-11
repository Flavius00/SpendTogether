<?php

namespace App\Controller;

use App\Entity\Family;
use App\Entity\User;
use App\Form\CreateFamilyFormType;
use App\Repository\FamilyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/family')]
final class FamilyController extends AbstractController
{
    #[Route('/home', name: 'app_family_home')]
    public function index(
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
    ): Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('app_login');
        }

        if (is_null($user->getFamily())) {
            return $this->redirectToRoute('app_family_create');
        }

        return $this->render('family/home.html.twig', [
            'userEmail' => $user->getEmail(),
            'family' => $user->getFamily(),
        ]);
    }

    #[Route('/create', name: 'app_family_create')]
    public function createFamilyPage(
        #[CurrentUser]
        User $user,
        Request $request,
        AuthorizationCheckerInterface $authCheck,
        EntityManagerInterface $em,
        Security $login,
    ) : Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('app_login');
        }

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
            $user->setRoles(['ROLE_ADMIN']);
            $em->persist($user);
            $em->flush();

            $login->login($user);

            return $this->redirectToRoute('app_family_home');
        }

        return $this->render('family/create.html.twig', [
            'createFamilyForm' => $form->createView(),
            'userEmail' => $user->getEmail(),
        ]);
    }

    #[Route('/join', name: 'app_family_join')]
    public function getFamily(
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        FamilyRepository $familyRepository,
    ) : Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('app_login');
        }

        $families = $familyRepository->findAll();

        return $this->render('family/join.html.twig', [
            'families' => $families,
            'userEmail' => $user->getEmail(),
        ]);
    }

    #[Route('/show-free-users', name: 'app_family_show_free_users')]
    public function getFreeUsers(
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        UserRepository $userRepository,
        Security $security,
    ) : Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('app_login');
        }

        if (!$security->isGranted('ROLE_ADMIN')) {
            $this->addFlash("error", "Restricted acccess. You are not allowed to add users to the family.");
            return $this->redirectToRoute('app_family_home');
        }

        $users = $userRepository->findBy(['family' => null]);
        return $this->render('family/search-free-user.html.twig', [
            'users' => $users,
            'userEmail' => $user->getEmail(),
            'family' => $user->getFamily(),
        ]);
    }
}
