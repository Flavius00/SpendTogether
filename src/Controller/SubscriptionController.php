<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Form\SubscriptionType;
use App\Repository\CategoryRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Security\Voter\SubscriptionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation as Http;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/subscriptions')]
final class SubscriptionController extends AbstractController
{
    #[Route('', name: 'app_subscription_index', methods: ['GET'])]
    public function index(
        Http\Request $request,
        SubscriptionRepository $subscriptions,
        UserRepository $users,
        CategoryRepository $categoriesRepo
    ): Http\Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $familyUsers = $currentUser->getFamily() ? $currentUser->getFamily()->getUsers() : [];

        $userParam = $request->query->get('user');
        $viewingAllFamily = $isAdmin && $userParam === '__all__'; // only admin can "All family"

        $targetUser = $currentUser;
        if (!$viewingAllFamily && $userParam) {
            $candidate = $users->find((int) $userParam);
            if ($candidate instanceof User) {
                $targetUser = $candidate;
            }
        }

        if ($viewingAllFamily) {
            // admin + part of a family
            if (!$currentUser->getFamily()) {
                throw $this->createAccessDeniedException();
            }
        } else {
            $this->denyAccessUnlessGranted(SubscriptionVoter::LIST, $targetUser);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPageRaw = $request->query->get('perPage', 20);
        $perPage = min(100, max(5, (int) ($perPageRaw === '' ? 20 : $perPageRaw)));

        $sort = (string) $request->query->get('sort', 'next_due');
        $dir = strtoupper((string) $request->query->get('dir', 'ASC'));
        $dir = $dir === 'ASC' ? 'ASC' : 'DESC';

        $q = trim((string) $request->query->get('q', '')) ?: null;

        $categoryRaw = $request->query->get('category');
        $category = ($categoryRaw === null || $categoryRaw === '') ? null : (int) $categoryRaw;

        $frequencyRaw = $request->query->get('frequency');
        $frequency = ($frequencyRaw === null || $frequencyRaw === '') ? null : (string) $frequencyRaw;

        $activeRaw = $request->query->get('active');
        $active = null;
        if ($activeRaw !== null && $activeRaw !== '') {
            $active = $activeRaw === '1';
        }

        $nextFrom = $request->query->get('next_from') ?: null;
        $nextTo = $request->query->get('next_to') ?: null;

        $criteria = [
            'q' => $q,
            'category' => $category,
            'frequency' => $frequency,
            'active' => $active,
            'next_from' => $nextFrom,
            'next_to' => $nextTo,
        ];

        if ($viewingAllFamily) {
            $userList = $familyUsers ? $familyUsers->toArray() : [];
            $result = $subscriptions->searchForUsers($userList, $criteria, $sort, $dir, $page, $perPage);
        } else {
            $result = $subscriptions->searchForUser($targetUser, $criteria, $sort, $dir, $page, $perPage);
        }

        return $this->render('subscription/index.html.twig', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'perPage' => $result['perPage'],
            'sort' => $sort,
            'dir' => $dir,
            'criteria' => $criteria,
            'targetUser' => $targetUser,
            'isAdmin' => $isAdmin,
            'viewingAllFamily' => $viewingAllFamily,
            'familyUsers' => $familyUsers,
            'categories' => $categoriesRepo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_subscription_new', methods: ['GET', 'POST'])]
    public function new(
        Http\Request $request,
        EntityManagerInterface $em
    ): Http\Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // CREATE for self (user) or family member (admin)
        $this->denyAccessUnlessGranted(SubscriptionVoter::CREATE, $currentUser);

        $subscription = new Subscription();
        $subscription->setIsActive(true);
        if (!$isAdmin) {
            $subscription->setUserObject($currentUser);
        }

        $form = $this->createForm(SubscriptionType::class, $subscription, [
            'is_admin' => $isAdmin,
            'family' => $currentUser->getFamily(),
            'current_user' => $currentUser,
        ]);
        $form->handleRequest($request);

        if (!$isAdmin) {
            $subscription->setUserObject($currentUser);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($subscription);
            $em->flush();

            $this->addFlash('success', 'Subscription created successfully.');
            return $this->redirectToRoute('app_subscription_index');
        }

        return $this->render('subscription/new.html.twig', [
            'form' => $form,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/{id}', name: 'app_subscription_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Subscription $subscription): Http\Response
    {
        $this->denyAccessUnlessGranted(SubscriptionVoter::VIEW, $subscription);

        return $this->render('subscription/show.html.twig', [
            'subscription' => $subscription,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_subscription_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Http\Request $request,
        Subscription $subscription,
        EntityManagerInterface $em
    ): Http\Response {
        $this->denyAccessUnlessGranted(SubscriptionVoter::EDIT, $subscription);

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $owner = $subscription->getUserObject();

        $form = $this->createForm(SubscriptionType::class, $subscription, [
            'is_admin' => $isAdmin,
            'family' => $currentUser->getFamily(),
            'current_user' => $currentUser,
        ]);
        $form->handleRequest($request);

        if (!$isAdmin) {
            $subscription->setUserObject($owner);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Subscription updated successfully.');
            return $this->redirectToRoute('app_subscription_index');
        }

        return $this->render('subscription/edit.html.twig', [
            'form' => $form,
            'subscription' => $subscription,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/{id}', name: 'app_subscription_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Http\Request $request,
        Subscription $subscription,
        EntityManagerInterface $em
    ): Http\Response {
        $this->denyAccessUnlessGranted(SubscriptionVoter::DELETE, $subscription);

        if ($this->isCsrfTokenValid('delete-subscription-' . $subscription->getId(), $request->request->get('_token'))) {
            // we uncouple expenses before deletion to avoid FK violation
            foreach ($subscription->getExpenses() as $expense) {
                $expense->setSubscription(null);
            }
            $em->remove($subscription);
            $em->flush();

            $this->addFlash('success', 'Subscription deleted successfully.');
            return $this->redirectToRoute('app_subscription_index');
        }

        $this->addFlash('error', 'Invalid CSRF token.');
        return $this->redirectToRoute('app_subscription_show', ['id' => $subscription->getId()]);
    }
}
