<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/api/categories')]
#[OA\Tag(name: "Admin - Categories")]
final class AdminApiCategoryController extends AbstractController
{
    #[Route('', name: 'admin_api_category_list', methods: ['GET'])]
    #[OA\Get(
        description: "Retrieves a list of all categories, including those that are soft-deleted. The response can be in JSON or XML format.",
        summary: "List all expense categories",
        security: [["Bearer" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of all categories",
                content: [
                    new OA\JsonContent(
                        type: "array",
                        items: new OA\Items(properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "is_deleted", type: "boolean"),
                        ], type: "object")
                    ),
                    new OA\XmlContent(
                        type: "array",
                        items: new OA\Items(properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "is_deleted", type: "boolean"),
                        ], type: "object"),
                        xml: new OA\Xml(name: 'response')
                    ),
                ]
            ),
            new OA\Response(response: 403, description: "Forbidden - Insufficient permissions"),
        ]
    )]
    public function listCategories(CategoryRepository $categoryRepository): array
    {
        $categories = $categoryRepository->findAll();

        $data = array_map(static fn (Category $c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'is_deleted' => $c->isDeleted(),
        ], $categories);

        return ['data' => $data];
    }

    #[Route('', name: 'admin_api_category_create', methods: ['POST'])]
    #[OA\Post(
        summary: "Create a new category or undelete an existing one",
        security: [["Bearer" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [new OA\Property(property: "name", type: "string", example: "Utilities")], type: "object")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Category created. The response can be in JSON or XML format.",
                content: [
                    new OA\JsonContent(
                        properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "is_deleted", type: "boolean"),
                        ], type: "object"
                    ),
                    new OA\XmlContent(
                        properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "is_deleted", type: "boolean"),
                        ], type: "object",
                        xml: new OA\Xml(name: 'response')
                    ),
                ]
            ),
            new OA\Response(
                response: 200,
                description: "Category undeleted and restored. The response can be in JSON or XML format.",
                content: [
                    new OA\JsonContent(
                        properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "is_deleted", type: "boolean"),
                        ], type: "object"
                    ),
                    new OA\XmlContent(
                        properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "name", type: "string"),
                            new OA\Property(property: "is_deleted", type: "boolean"),
                        ], type: "object",
                        xml: new OA\Xml(name: 'response')
                    ),
                ]
            ),
            new OA\Response(response: 400, description: "Bad Request - Name is missing"),
            new OA\Response(response: 403, description: "Forbidden - Insufficient permissions"),
            new OA\Response(response: 409, description: "Conflict - A category with this name already exists"),
        ]
    )]
    public function createOrUndeleteCategory(
        Request                 $request,
        CategoryRepository      $categoryRepository,
        EntityManagerInterface  $em,
    ): array {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;

        if (!$name) {
            return ['data' => ['error' => 'Category name is required'], 'status' => Response::HTTP_BAD_REQUEST];
        }

        $category = $categoryRepository->findOneBy(['name' => $name]);

        if ($category) {
            if ($category->isDeleted()) {
                $category->setIsDeleted(false);
                $em->flush();

                return ['data' => ['id' => $category->getId(), 'name' => $category->getName(), 'is_deleted' => $category->isDeleted()], 'status' => Response::HTTP_OK];
            }

            return ['data' => ['error' => 'A category with this name already exists'], 'status' => Response::HTTP_CONFLICT];
        }

        $category = new Category();
        $category->setName($name);
        $category->setIsDeleted(false);
        $em->persist($category);
        $em->flush();

        return ['data' => ['id' => $category->getId(), 'name' => $category->getName(), 'is_deleted' => $category->isDeleted()], 'status' => Response::HTTP_CREATED];
    }

    #[Route('/{id}', name: 'admin_api_category_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        summary: "Soft-delete an expense category",
        security: [["Bearer" => []]],
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))],
        responses: [
            new OA\Response(response: 204, description: "Category successfully soft-deleted"),
            new OA\Response(response: 403, description: "Forbidden - Insufficient permissions"),
            new OA\Response(response: 404, description: "Category not found"),
        ]
    )]
    public function softDeleteCategory(Category $category, EntityManagerInterface $em): array
    {
        // We use the Symfony ParamConverter to directly load the Category entity
        $category->setIsDeleted(true);
        $em->flush();

        return ['data' => null, 'status' => Response::HTTP_NO_CONTENT];
    }
}
