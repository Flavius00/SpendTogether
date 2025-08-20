<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\User;
use App\Form\ExpenseType;
use App\Repository\CategoryRepository;
use App\Repository\ExpenseRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ExpenseVoter;
use App\Service\ReceiptStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation as Http;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/expenses')]
final class ExpenseController extends AbstractController
{
    #[Route('', name: 'app_expense_index', methods: ['GET'])]
    public function index(
        Http\Request $request,
        ExpenseRepository $expenses,
        CategoryRepository $categories,
        UserRepository $users
    ): Http\Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $familyUsers = [];
        if ($isAdmin && $currentUser->getFamily()) {
            $familyUsers = $currentUser->getFamily()->getUsers();
        }

        $userParam = $request->query->get('user');
        $viewingAllFamily = $isAdmin && $userParam === '__all__';

        $targetUser = $currentUser;
        if ($isAdmin && !$viewingAllFamily && $userParam) {
            $candidate = $users->find((int) $userParam);
            if ($candidate instanceof User) {
                $targetUser = $candidate;
            }
        }

        if ($viewingAllFamily) {
            if (!$isAdmin || !$currentUser->getFamily()) {
                throw $this->createAccessDeniedException();
            }
        } else {
            $this->denyAccessUnlessGranted(ExpenseVoter::LIST, $targetUser);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPageRaw = $request->query->get('perPage', 20);
        $perPage = min(100, max(5, (int) ($perPageRaw === '' ? 20 : $perPageRaw)));

        $sort = (string) $request->query->get('sort', 'date');
        $dir = strtoupper((string) $request->query->get('dir', 'DESC'));
        $dir = $dir === 'ASC' ? 'ASC' : 'DESC';

        $hasReceiptRaw = $request->query->get('has_receipt');
        $hasReceipt = null;
        if ($hasReceiptRaw !== null && $hasReceiptRaw !== '') {
            if ($hasReceiptRaw === '1' || $hasReceiptRaw === 'true') {
                $hasReceipt = true;
            } elseif ($hasReceiptRaw === '0' || $hasReceiptRaw === 'false') {
                $hasReceipt = false;
            }
        }

        $categoryRaw = $request->query->get('category');
        $category = ($categoryRaw === null || $categoryRaw === '') ? null : ($request->query->getInt('category') ?: null);

        $minAmountRaw = $request->query->get('min_amount');
        $minAmount = ($minAmountRaw === null || $minAmountRaw === '') ? null : (float) $minAmountRaw;

        $maxAmountRaw = $request->query->get('max_amount');
        $maxAmount = ($maxAmountRaw === null || $maxAmountRaw === '') ? null : (float) $maxAmountRaw;

        $q = trim((string) $request->query->get('q', '')) ?: null;

        $dateFrom = $request->query->get('date_from') ?: null;
        $dateTo = $request->query->get('date_to') ?: null;

        $criteria = [
            'q' => $q,
            'category' => $category,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'has_receipt' => $hasReceipt,
        ];

        if ($viewingAllFamily) {
            $userList = $familyUsers ? $familyUsers->toArray() : [];
            $result = $expenses->searchForUsers($userList, $criteria, $sort, $dir, $page, $perPage);
        } else {
            $result = $expenses->searchForUser($targetUser, $criteria, $sort, $dir, $page, $perPage);
        }

        return $this->render('expense/index.html.twig', [
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
            'categories' => $categories->findBy([], ['name' => 'ASC']),
            'familyUsers' => $familyUsers,
        ]);
    }


    #[Route('/new', name: 'app_expense_new', methods: ['GET', 'POST'])]
    public function new(
        Http\Request $request,
        EntityManagerInterface $em,
        ReceiptStorage $storage,
        UserRepository $users
    ): Http\Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $targetUser = $currentUser;
        if ($isAdmin && ($uid = $request->query->get('user'))) {
            $candidate = $users->find((int) $uid);
            if ($candidate instanceof User) {
                $targetUser = $candidate;
            }
        }

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
            // Validation: subscription belongs to the selected user
            $selectedSubscription = $expense->getSubscription();
            $selectedUser = $expense->getUserObject() ?: $currentUser;
            if ($selectedSubscription && $selectedSubscription->getUserObject()?->getId() !== $selectedUser->getId()) {
                $form->get('subscription')->addError(new \Symfony\Component\Form\FormError('Selected subscription does not belong to the chosen user.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $finalUser = $expense->getUserObject() ?? $currentUser;
            $this->denyAccessUnlessGranted(ExpenseVoter::CREATE, $finalUser);

            $uploadedFile = $form->get('receiptImageFile')->getData();
            if ($uploadedFile) {
                $filename = $storage->store($uploadedFile);
                $expense->setReceiptImage($filename);
            }

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
    public function show(Expense $expense): Http\Response
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::VIEW, $expense);

        return $this->render('expense/show.html.twig', [
            'expense' => $expense,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_expense_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Http\Request $request,
        Expense $expense,
        EntityManagerInterface $em,
        ReceiptStorage $storage
    ): Http\Response {
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
            $selectedSubscription = $expense->getSubscription();
            $selectedUser = $expense->getUserObject() ?: $originalOwner ?: $currentUser;
            if ($selectedSubscription && $selectedSubscription->getUserObject()?->getId() !== $selectedUser->getId()) {
                $form->get('subscription')->addError(new \Symfony\Component\Form\FormError('Selected subscription does not belong to the chosen user.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $finalUser = $expense->getUserObject() ?? $originalOwner ?? $currentUser;

            // remove existing receipt if requested (and no new file uploaded)
            $removeRequested = (bool)($form->has('removeReceipt') ? $form->get('removeReceipt')->getData() : false);
            $uploadedFile = $form->get('receiptImageFile')->getData();

            if ($removeRequested && !$uploadedFile && $oldFile) {
                $storage->remove($oldFile);
                $expense->setReceiptImage(null);
            }


            /*if ($finalUser !== $originalOwner) {
                $this->denyAccessUnlessGranted(ExpenseVoter::EDIT, $expense);
            }

            $uploadedFile = $form->get('receiptImageFile')->getData();*/

            if ($uploadedFile) {
                $newFilename = $storage->store($uploadedFile, $oldFile);
                $expense->setReceiptImage($newFilename);
            }

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
        Http\Request $request,
        Expense $expense,
        EntityManagerInterface $em,
        ReceiptStorage $storage
    ): Http\Response {
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
    public function downloadReceipt(Expense $expense, ReceiptStorage $storage): Http\Response
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
}
