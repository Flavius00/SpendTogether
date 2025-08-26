<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Service\ExpenseIndexContextResolver;
use App\Controller\Service\ExpenseParamsExtractor;
use App\Controller\Service\ReceiptStorage;
use App\Entity\Expense;
use App\Entity\User;
use App\Form\ExpenseType;
use App\Repository\CategoryRepository;
use App\Repository\ExpenseRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ExpenseVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/expenses')]
final class ExpenseController extends AbstractController
{
    #[Route('', name: 'app_expense_index', methods: ['GET'])]
    public function index(
        Request                     $request,
        ExpenseRepository           $expenses,
        CategoryRepository          $categories,
        UserRepository              $users,
        ExpenseIndexContextResolver $ctxResolver,
        ExpenseParamsExtractor      $params,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $ctx = $ctxResolver->resolveAndAuthorize($request, $users, $currentUser);
        $pagination = $params->extractPagination($request, defaultPerPage: 20, maxPerPage: 100);
        $sorting = $params->extractSorting($request, allowedSorts: ['date', 'name', 'amount', 'category'], defaultSort: 'date', defaultDir: 'DESC');
        $criteria = $params->extractCriteria($request);

        $userList = $ctx->viewingAllFamily
            ? ($ctx->familyUsers ? (method_exists($ctx->familyUsers, 'toArray') ? $ctx->familyUsers->toArray() : (array) $ctx->familyUsers) : [])
            : [$ctx->targetUser];

        $result = $expenses->searchByUsers($userList, $criteria, $sorting['sort'], $sorting['dir'], $pagination['page'], $pagination['perPage']);

        return $this->render('expense/index.html.twig', [
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
            'categories' => $categories->findBy([], ['name' => 'ASC']),
            'familyUsers' => $ctx->familyUsers,
        ]);
    }

    #[Route('/new', name: 'app_expense_new', methods: ['GET', 'POST'])]
    public function new(
        Request                 $request,
        EntityManagerInterface  $em,
        ReceiptStorage          $storage,
        UserRepository          $users,
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $targetUser = $this->resolveTargetUserFromQuery($request, $users, $currentUser, $isAdmin);
        $this->denyAccessUnlessGranted(ExpenseVoter::CREATE, $targetUser);

        $expense = new Expense();
        $expense->setDate(new \DateTime());
        $expense->setUserObject($isAdmin ? $targetUser : $currentUser);

        $form = $this->createForm(ExpenseType::class, $expense, [
            'is_admin' => $isAdmin,
            'target_user' => $targetUser,
            'family' => $isAdmin ? $currentUser->getFamily() : null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->validateSubscriptionOwnership($expense, $currentUser, $form);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $finalUser = $expense->getUserObject() ?? $currentUser;
            $this->denyAccessUnlessGranted(ExpenseVoter::CREATE, $finalUser);

            $uploadedFile = $form->get('receiptImageFile')->getData();
            $newReceipt = $this->processReceiptUpload(oldFile: null, uploadedFile: $uploadedFile, removeRequested: false, storage: $storage);
            $expense->setReceiptImage($newReceipt);

            $em->persist($expense);
            $em->flush();

            $this->addFlash('success', 'Expense created successfully.');

            return $this->redirectToRoute('app_expense_index', [
                'user' => $isAdmin ? $finalUser->getId() : null,
            ]);
        }

        return $this->render('expense/new.html.twig', [
            'form' => $form,
            'isAdmin' => $isAdmin,
            'targetUser' => $targetUser,
        ]);
    }

    #[Route('/{id}', name: 'app_expense_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Expense $expense): Response
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::VIEW, $expense);

        return $this->render('expense/show.html.twig', [
            'expense' => $expense,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_expense_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request                 $request,
        Expense                 $expense,
        EntityManagerInterface  $em,
        ReceiptStorage          $storage,
    ): Response {
        $this->denyAccessUnlessGranted(ExpenseVoter::EDIT, $expense);

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $oldFile = $expense->getReceiptImage();
        $originalOwner = $expense->getUserObject();

        $form = $this->createForm(ExpenseType::class, $expense, [
            'is_admin' => $isAdmin,
            'target_user' => $originalOwner,
            'family' => $isAdmin ? $currentUser->getFamily() : null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->validateSubscriptionOwnership($expense, $originalOwner ?? $currentUser, $form);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $finalUser = $expense->getUserObject() ?? $originalOwner ?? $currentUser;

            $removeRequested = (bool) ($form->has('removeReceipt') ? $form->get('removeReceipt')->getData() : false);
            $uploadedFile = $form->get('receiptImageFile')->getData();

            $newReceipt = $this->processReceiptUpload(oldFile: $oldFile, uploadedFile: $uploadedFile, removeRequested: $removeRequested, storage: $storage);
            $expense->setReceiptImage($newReceipt);

            $em->flush();

            $this->addFlash('success', 'Expense updated successfully.');

            return $this->redirectToRoute('app_expense_index', [
                'user' => $isAdmin ? $finalUser->getId() : null,
            ]);
        }

        return $this->render('expense/edit.html.twig', [
            'form' => $form,
            'expense' => $expense,
            'isAdmin' => $isAdmin,
            'targetUser' => $originalOwner,
        ]);
    }

    #[Route('/{id}', name: 'app_expense_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request                 $request,
        Expense                 $expense,
        EntityManagerInterface  $em,
        ReceiptStorage          $storage,
    ): Response {
        $this->denyAccessUnlessGranted(ExpenseVoter::DELETE, $expense);

        if ($this->isCsrfTokenValid('delete-expense-' . $expense->getId(), $request->request->get('_token'))) {
            $owner = $expense->getUserObject();
            $storage->remove($expense->getReceiptImage());

            $em->remove($expense);
            $em->flush();

            $this->addFlash('success', 'Expense deleted successfully.');

            return $this->redirectToRoute('app_expense_index', [
                'user' => $this->isGranted('ROLE_ADMIN') && $owner ? $owner->getId() : null,
            ]);
        }

        $this->addFlash('error', 'Invalid CSRF token.');

        return $this->redirectToRoute('app_expense_show', ['id' => $expense->getId()]);
    }

    #[Route('/{id}/receipt', name: 'app_expense_receipt', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadReceipt(Expense $expense, ReceiptStorage $storage): Response
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::VIEW, $expense);

        $filename = $expense->getReceiptImage();
        if (!$filename) {
            throw $this->createNotFoundException('No receipt for this expense.');
        }

        $filePath = $storage->path($filename);
        if (!is_file($filePath)) {
            throw $this->createNotFoundException('Receipt file not found.');
        }

        return $this->file($filePath);
    }

    /**
     * Helpers private.
     */

    private function resolveTargetUserFromQuery(Request $request, UserRepository $users, User $currentUser, bool $isAdmin): User
    {
        $targetUser = $currentUser;
        if ($isAdmin && ($uid = $request->query->get('user'))) {
            $candidate = $users->find((int) $uid);
            if ($candidate instanceof User) {
                $targetUser = $candidate;
            }
        }

        return $targetUser;
    }

    private function validateSubscriptionOwnership(Expense $expense, User $fallbackUser, FormInterface $form): void
    {
        $selectedSubscription = $expense->getSubscription();
        $selectedUser = $expense->getUserObject() ?: $fallbackUser;

        if ($selectedSubscription && $selectedSubscription->getUserObject()?->getId() !== $selectedUser->getId()) {
            $form->get('subscription')->addError(new FormError('Selected subscription does not belong to the chosen user.'));
        }
    }

    private function processReceiptUpload(?string $oldFile, mixed $uploadedFile, bool $removeRequested, ReceiptStorage $storage): ?string
    {
        if ($removeRequested && !$uploadedFile && $oldFile) {
            $storage->remove($oldFile);
            $oldFile = null;
        }

        if ($uploadedFile) {
            return $storage->store($uploadedFile, $oldFile);
        }

        return $oldFile;
    }
}
