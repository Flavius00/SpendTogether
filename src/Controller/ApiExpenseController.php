<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Repository\CategoryRepository;
use App\Repository\ExpenseRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ExpenseVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ApiExpenseController extends AbstractController
{
    #[Route('/expense', name: 'api_expense_add', methods: ['POST'])]
    public function addExpense(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepository,
        UserRepository $userRepository,
        ?SubscriptionRepository $subscriptionRepository = null
    ): JsonResponse {
        $authUser = $this->getUser();
        if (!is_object($authUser)) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $name = $data['name'] ?? null;
        $amount = $data['amount'] ?? null;
        $categoryId = $data['category_id'] ?? null;

        if (!$name || $amount === null || !$categoryId) {
            return $this->json(['message' => 'name, amount and category_id are required'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_numeric($amount)) {
            return $this->json(['message' => 'amount must be numeric'], Response::HTTP_BAD_REQUEST);
        }
        $amountStr = number_format((float) $amount, 2, '.', '');

        // Determine the target user (default authenticated; admin can set user_id)
        $targetUser = $authUser;
        if (isset($data['user_id'])) {
            $targetUser = $userRepository->find((int) $data['user_id']);
            if (!$targetUser) {
                return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
            }
        }

        // Authorization via Voter (admin allowed, otherwise only for self)
        $this->denyAccessUnlessGranted(ExpenseVoter::CREATE, $targetUser);

        $category = $categoryRepository->find((int) $categoryId);
        if (!$category) {
            return $this->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $dateInput = $data['date'] ?? null;
        if ($dateInput) {
            try {
                $date = new \DateTime($dateInput);
            } catch (\Throwable) {
                return $this->json(['message' => 'Invalid date. Use ISO 8601 or YYYY-MM-DD'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            $date = new \DateTime();
        }

        $subscription = null;
        if (isset($data['subscription_id']) && $subscriptionRepository) {
            $subscription = $subscriptionRepository->find((int) $data['subscription_id']);
        }

        $expense = new Expense();
        $expense
            ->setName((string) $name)
            ->setAmount($amountStr)
            ->setDescription($data['description'] ?? null)
            ->setDate($date)
            ->setReceiptImage($data['receipt_image'] ?? null)
            ->setCategoryId($category)
            ->setUserObject($targetUser)
            ->setSubscription($subscription);

        $em->persist($expense);
        $em->flush();

        return $this->json([
            'id' => $expense->getId(),
            'user_id' => $targetUser->getId(),
            'name' => $expense->getName(),
            'amount' => $expense->getAmount(),
            'category_id' => $category->getId(),
            'description' => $expense->getDescription(),
            'date' => $expense->getDate()?->format(DATE_ATOM),
            'receipt_image' => $expense->getReceiptImage(),
            'subscription_id' => $subscription?->getId(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/user/{id}/expense', name: 'api_user_expense_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function listUserExpenses(
        int $id,
        ExpenseRepository $expenseRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $authUser = $this->getUser();
        if (!is_object($authUser)) {
            return $this->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $userRepository->find($id);
        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Authorization via Voter (admin or owner)
        $this->denyAccessUnlessGranted(ExpenseVoter::LIST, $user);

        $expenses = $expenseRepository->findBy(
            ['userObject' => $user],
            ['date' => 'DESC', 'id' => 'DESC']
        );

        $data = array_map(static function (Expense $e): array {
            return [
                'id' => $e->getId(),
                'name' => $e->getName(),
                'amount' => $e->getAmount(),
                'category_id' => $e->getCategoryId()?->getId(),
                'description' => $e->getDescription(),
                'date' => $e->getDate()?->format(DATE_ATOM),
                'receipt_image' => $e->getReceiptImage(),
                'subscription_id' => $e->getSubscription()?->getId(),
            ];
        }, $expenses);

        return $this->json($data, Response::HTTP_OK);
    }
}
