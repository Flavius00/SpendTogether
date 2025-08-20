<?php

namespace App\Controller;

use App\Controller\Service\FamilyService;
use App\Entity\Family;
use App\Entity\User;
use App\Form\AddUserToFamilyFormType;
use App\Form\CreateFamilyFormType;
use App\Form\FamilyEditFormType;
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
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        if (is_null($user->getFamily())) {
            return $this->redirectToRoute('app_family_create');
        }

        return $this->render('family/home.html.twig', [
            'userEmail' => $user->getEmail(),
            'family' => $user->getFamily(),
            'userRole' => $user->getRoles()[0],
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
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
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
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $families = $familyRepository->findAll();

        return $this->render('family/join.html.twig', [
            'families' => $families,
            'userEmail' => $user->getEmail(),
        ]);
    }

    #[Route('/join/{familyId}', name: 'app_family_join_family')]
    public function joinFamily(
        int $familyId,
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        FamilyRepository $familyRepository,
        EntityManagerInterface $em,
        Security $security,
    ) : Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $family = $familyRepository->find($familyId);

        if (!$family) {
            $this->addFlash('error', 'Family not found.');
            return $this->redirectToRoute('app_family_join');
        }

        $user->setFamily($family);
        $user->setRoles(['ROLE_MEMBER']);

        $em->persist($user);
        $em->flush();

        $security->login($user);

        $this->addFlash('success', 'Successfully joined the family!');
        return $this->redirectToRoute('app_family_home');
    }

    #[Route('/add-user-to-family', name: 'app_family_add_user')]
    public function addUserToFamily(
        Request $request,
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        UserRepository $userRepository,
        EntityManagerInterface $em,
    ) : Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(AddUserToFamilyFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->getData();
            $userToAdd = $userRepository->findOneBy(['email' => $email]);

            if (!$userToAdd) {
                $this->addFlash("error", "User with this email does not exist.");
            } elseif (!is_null($userToAdd->getFamily())){
                $this->addFlash("error", "User already belongs to a family.");
            } else {
                $userToAdd->setFamily($user->getFamily());
                $userToAdd->setRoles(['ROLE_MEMBER']);

                $em->persist($userToAdd);
                $em->flush();

                $this->addFlash("success", "User added to family successfully.");
            }

            return $this->redirectToRoute('app_family_add_user');
        }

        return $this->render('family/add-user.html.twig', [
            'userEmail' => $user->getEmail(),
            'family' => $user->getFamily(),
            'addUserForm' => $form->createView(),
        ]);
    }

    #[Route('/leave', name: 'app_family_leave')]
    public function leaveFamily(
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        EntityManagerInterface $em,
        Security $security,
        FamilyService $familyService,
    ): Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        if (is_null($user->getFamily())) {
            $this->addFlash('error', 'You are not part of any family.');
            return $this->redirectToRoute('app_family_create');
        }

        if ($familyService->verifyLeavePosibility($user) === false) {
            $this->addFlash('error', 'Can\' leave family if you are the only admin.');
            $this->addFlash('error', "Give the admin role to someone else before leaving the family.");
            return $this->redirectToRoute('app_family_home');
        }

        $user->setFamily(null);
        $user->setRoles(['ROLE_USER']);

        $em->persist($user);
        $em->flush();

        $security->login($user);

        $this->addFlash('success', 'You have left the family successfully.');
        return $this->redirectToRoute('app_family_home');
    }

    #[Route('/kick/{userId}', name: 'app_family_kick_user')]
    public function kickUserFromFamily(
        int $userId,
        #[CurrentUser]
        User $currentUser,
        AuthorizationCheckerInterface $authCheck,
        EntityManagerInterface $em,
        UserRepository $userRepository,
    ) : Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->find($userId);

        if ($currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot kick this user.');
            return $this->redirectToRoute('app_family_home');
        }

        if ($currentUser->getRoles()[0] !== 'ROLE_ADMIN') {
            $this->addFlash('error', 'Only admins can kick users from the family.');
            return $this->redirectToRoute('app_family_home');
        }

        $user->setRoles(['ROLE_USER']);
        $user->setFamily(null);
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'User has been kicked from the family successfully.');
        return $this->redirectToRoute('app_family_home'); // Placeholder for kick user logic
    }

    #[Route('/role-change/{userId}', name: 'app_family_role_change')]
    public function roleChange(
        int $userId,
        Request $request,
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        EntityManagerInterface $em,
        UserRepository $userRepository,
    ): Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        if($user->getRoles()[0] !== 'ROLE_ADMIN') {
            $this->addFlash('error', 'Only admins can change user roles.');
            return $this->redirectToRoute('app_family_home');
        }

        $newRole = $request->request->get('role');
        $changedUser = $userRepository->find($userId);

        if (!$changedUser) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_family_home');
        }

        $changedUser->setRoles([$newRole]);
        $em->persist($changedUser);
        $em->flush();

        $this->addFlash('success', 'User role has been changed successfully.');
        return $this->redirectToRoute('app_family_home');
    }

    #[Route('/edit', name: 'app_family_edit')]
    public function editFamily(
        #[CurrentUser]
        User $user,
        Request $request,
        EntityManagerInterface $em,
        AuthorizationCheckerInterface $authCheck,
    ) : Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getRoles()[0] !== 'ROLE_ADMIN') {
            $this->addFlash('error', 'Only admins can update family.');
            return $this->redirectToRoute('app_family_home');
        }

        $family = $user->getFamily();
        $form = $this->createForm(FamilyEditFormType::class, $family);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($family);
            $em->flush();

            $this->addFlash('success', 'Family details updated successfully.');
            return $this->redirectToRoute('app_family_home');
        }

        return $this->render('family/edit-family.html.twig',[
            'userEmail' => $user->getEmail(),
            'family' => $family,
            'editFamilyForm' => $form->createView(),
        ]); //Placeholder for edit family logic
    }

    #[Route('/delete', name: 'app_family_delete')]
    public function deleteFamily(
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        EntityManagerInterface $em,
        Security $security,
    ) : Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $family = $user->getFamily();

        $numberOfUsers = count($family->getUsers());

        if ($numberOfUsers !== 1) {
            $this->addFlash('error', 'You cannot delete family.');
            return $this->redirectToRoute('app_family_home');
        }

        $user->setRoles(['ROLE_USER']);
        $user->setFamily(null);

        $em->persist($user);

        foreach ($family->getThresholds() as $threshold) {
            $threshold->setFamily(null);
            $em->remove($threshold);
        }

        $em->remove($family);
        $em->flush();

        $security->login($user);

        $this->addFlash('success', 'Family has been deleted.');
        return $this->redirectToRoute('app_family_create');
    }
}
