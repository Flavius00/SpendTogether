<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ExpenseRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ExpenseVoter;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
#[OA\Tag(name: "Expenses")]
final class ApiExpenseController extends AbstractController
{
    #[Route('/expense', name: 'api_expense_add', methods: ['POST'])]
    #[OA\Post(
        description: "Creates a new expense record for a user. Regular users can only create expenses for themselves. Admins can create expenses for other users within their family by providing a 'user_id'.",
        summary: "Add a new expense",
        security: [["Bearer" => []]],
        requestBody: new OA\RequestBody(
            description: "Expense data",
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Groceries"),
                    new OA\Property(property: "amount", type: "number", format: "float", example: 55.45),
                    new OA\Property(property: "category_id", type: "integer", example: 1),
                    new OA\Property(property: "description", type: "string", example: "Weekly shopping at Lidl"),
                    new OA\Property(property: "date", type: "string", format: "date-time", example: "2025-08-11T12:00:00Z"),
                    new OA\Property(property: "user_id", description: "Admin only: specify user ID to create expense for another family member.", type: "integer", example: 2),
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Expense created successfully. The response can be in JSON or XML format.",
                content: [
                    new OA\JsonContent(
                        properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "user_id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "amount", type: "string"),
                            new OA\Property(property: "category_id", type: "integer"),
                            new OA\Property(property: "description", type: "string", nullable: true),
                            new OA\Property(property: "date", type: "string", format: "date-time"),
                        ],
                        type: "object"
                    ),
                    new OA\XmlContent(
                        properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "user_id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "amount", type: "string"),
                            new OA\Property(property: "category_id", type: "integer"),
                            new OA\Property(property: "description", type: "string", nullable: true),
                            new OA\Property(property: "date", type: "string", format: "date-time"),
                        ],
                        type: "object",
                        xml: new OA\Xml(name: 'response')
                    )
                ]
            ),
            new OA\Response(response: 400, description: "Bad Request - Missing required fields or invalid data"),
            new OA\Response(response: 401, description: "Unauthorized - Invalid or missing token"),
            new OA\Response(response: 403, description: "Forbidden - You are not allowed to create an expense for this user"),
            new OA\Response(response: 404, description: "Not Found - Category or User not found"),
            new OA\Response(response: 429, description: "Too Many Requests"),
        ]
    )]
    public function addExpense(
        Request $request,
        EntityManagerInterface $em,
        #[CurrentUser]
        User $user,
        CategoryRepository $categoryRepository,
        UserRepository $userRepository,
        ?SubscriptionRepository $subscriptionRepository = null,
    ): array {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return ['data' => ['message' => 'Invalid JSON body'], 'status' => Response::HTTP_BAD_REQUEST];
        }

        $name = $data['name'] ?? null;
        $amount = $data['amount'] ?? null;
        $categoryId = $data['category_id'] ?? null;

        if (!$name || $amount === null || !$categoryId) {
            return ['data' => ['message' => 'name, amount and category_id are required'], 'status' => Response::HTTP_BAD_REQUEST];
        }

        if (!is_numeric($amount)) {
            return ['data' => ['message' => 'amount must be numeric'], 'status' => Response::HTTP_BAD_REQUEST];
        }
        $amountStr = number_format((float) $amount, 2, '.', '');

        // Determine the target user (default authenticated; admin can set user_id)
        $targetUser = $user;
        if (isset($data['user_id'])) {
            $targetUser = $userRepository->find((int) $data['user_id']);
            if (!$targetUser) {
                return ['data' => ['message' => 'User not found'], 'status' => Response::HTTP_NOT_FOUND];
            }
        }

        // Authorization via Voter (admin allowed, otherwise only for self)
        $this->denyAccessUnlessGranted(ExpenseVoter::CREATE, $targetUser);

        $category = $categoryRepository->find((int) $categoryId);
        if (!$category) {
            return ['data' => ['message' => 'Category not found'], 'status' => Response::HTTP_NOT_FOUND];
        }

        $dateInput = $data['date'] ?? null;
        if ($dateInput) {
            try {
                $date = new \DateTime($dateInput);
            } catch (\Throwable) {
                return ['data' => ['message' => 'Invalid date. Use ISO 8601 or YYYY-MM-DD'], 'status' => Response::HTTP_BAD_REQUEST];
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

        return [
            'data' => [
                'id' => $expense->getId(),
                'user_id' => $targetUser->getId(),
                'name' => $expense->getName(),
                'amount' => $expense->getAmount(),
                'category_id' => $category->getId(),
                'description' => $expense->getDescription(),
                'date' => $expense->getDate()?->format(DATE_ATOM),
                'receipt_image' => $expense->getReceiptImage(),
                'subscription_id' => $subscription?->getId(),
            ],
            'status' => Response::HTTP_CREATED,
        ];
    }

    #[Route('/user/{id}/expense', name: 'api_user_expense_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        description: "Retrieves a list of expenses for a given user ID. Regular users can only view their own expenses. Admins can view expenses for any user within their family.",
        summary: "List expenses for a specific user",
        security: [["Bearer" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "The ID of the user whose expenses to retrieve.",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "A list of expenses",
                content: [
                    new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(properties: [
                        new OA\Property(property: "id", type: "integer"),
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "amount", type: "string"),
                        new OA\Property(property: "category_id", type: "integer"),
                        new OA\Property(property: "description", type: "string", nullable: true),
                        new OA\Property(property: "date", type: "string", format: "date-time"),
                    ], type: "object")
                    ),
                    new OA\XmlContent(
                        type: "array",
                        items: new OA\Items(properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "amount", type: "string"),
                            new OA\Property(property: "category_id", type: "integer"),
                            new OA\Property(property: "description", type: "string", nullable: true),
                            new OA\Property(property: "date", type: "string", format: "date-time"),
                        ], type: "object"),
                        xml: new OA\Xml(name: 'response')
                    )
                ]
            ),
            new OA\Response(response: 401, description: "Unauthorized - Invalid or missing token"),
            new OA\Response(response: 403, description: "Forbidden - You are not allowed to view these expenses"),
            new OA\Response(response: 404, description: "Not Found - User not found"),
            new OA\Response(response: 429, description: "Too Many Requests"),
        ]
    )]
    public function listUserExpenses(
        Request $request,
        int $id,
        ExpenseRepository $expenseRepository,
        UserRepository $userRepository,
    ): array {
        $user = $userRepository->find($id);
        if (!$user) {
            return ['data' => ['message' => 'User not found'], 'status' => Response::HTTP_NOT_FOUND];
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

        return [
            'data' => $data,
            'status' => Response::HTTP_OK,
        ];
    }
}
