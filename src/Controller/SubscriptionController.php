<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Form\SubscriptionType;
use App\Repository\CategoryRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Security\Voter\SubscriptionVoter;
use App\Service\SubscriptionIndexContextResolver;
use App\Service\SubscriptionParamsExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/subscriptions')]
final class SubscriptionController extends AbstractController
{
    #[Route('', name: 'app_subscription_index', methods: ['GET'])]
    public function index(
        Request $request,
        SubscriptionRepository $subscriptions,
        UserRepository $users,
        CategoryRepository $categoriesRepo,
        SubscriptionIndexContextResolver $ctxResolver,
        SubscriptionParamsExtractor $params,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $ctx = $ctxResolver->resolveAndAuthorize($request, $users, $currentUser);

        $pagination = $params->extractPagination($request, defaultPerPage: 20, maxPerPage: 100);
        $sorting = $params->extractSorting($request, allowedSorts: ['next_due', 'name', 'amount', 'category', 'user'], defaultSort: 'next_due', defaultDir: 'ASC');
        $criteria = $params->extractCriteria($request);

        $userList = $ctx->viewingAllFamily
            ? ($ctx->familyUsers ? (method_exists($ctx->familyUsers, 'toArray') ? $ctx->familyUsers->toArray() : (array) $ctx->familyUsers) : [])
            : [$ctx->targetUser];

        $result = $subscriptions->searchByUsers($userList, $criteria, $sorting['sort'], $sorting['dir'], $pagination['page'], $pagination['perPage']);

        return $this->render('subscription/index.html.twig', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'perPage' => $result['perPage'],
            'sort' => $sorting['sort'],
            'dir' => $sorting['dir'],
            'criteria' => $criteria,
            'targetUser' => $ctx->targetUser,
            'isAdmin' => $ctx->isAdmin,
            'viewingAllFamily' => $ctx->viewingAllFamily,
            'familyUsers' => $ctx->familyUsers,
            'categories' => $categoriesRepo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_subscription_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $this->denyAccessUnlessGranted(SubscriptionVoter::CREATE, $currentUser);

        $subscription = new Subscription();
        $subscription->setIsActive(true);
        $subscription->setNextDueDate(new \DateTime('today'));

        $this->ensureOwnerForNonAdmin($subscription, $currentUser, $isAdmin);

        $form = $this->createForm(SubscriptionType::class, $subscription, [
            'is_admin' => $isAdmin,
            'family' => $currentUser->getFamily(),
            'current_user' => $currentUser,
        ]);
        $form->handleRequest($request);

        $this->ensureOwnerForNonAdmin($subscription, $currentUser, $isAdmin);

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
    public function show(Subscription $subscription): Response
    {
        $this->denyAccessUnlessGranted(SubscriptionVoter::VIEW, $subscription);

        return $this->render('subscription/show.html.twig', [
            'subscription' => $subscription,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_subscription_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Subscription $subscription,
        EntityManagerInterface $em,
    ): Response {
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

        $this->keepOwnerForNonAdmin($subscription, $owner, $isAdmin);

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
        Request $request,
        Subscription $subscription,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(SubscriptionVoter::DELETE, $subscription);

        if ($this->isCsrfTokenValid('delete-subscription-' . $subscription->getId(), $request->request->get('_token'))) {
            // unlink expenses to avoid FK violation
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

    /**
     * Helpers private.
     */

    private function ensureOwnerForNonAdmin(Subscription $subscription, User $currentUser, bool $isAdmin): void
    {
        if (!$isAdmin) {
            $subscription->setUserObject($currentUser);
        }
    }

    private function keepOwnerForNonAdmin(Subscription $subscription, ?User $owner, bool $isAdmin): void
    {
        if (!$isAdmin) {
            $subscription->setUserObject($owner);
        }
    }
}
