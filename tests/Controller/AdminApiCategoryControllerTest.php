<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AdminFixtures;
use App\Repository\CategoryRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminApiCategoryControllerTest extends WebTestCase
{
    private AbstractDatabaseTool $databaseTool;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $this->databaseTool->loadFixtures([AdminFixtures::class]);
    }

    private function getAuthToken(string $email, string $password): string
    {
        $this->client->request(
            'POST', '/api/login', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );
        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['token'];
    }

    // ===============================================
    // == Tests for GET /admin/api/categories (List)
    // ===============================================

    public function testListCategoriesAsSuperAdminSuccessfully(): void
    {
        $token = $this->getAuthToken('superadmin@test.com', 'password123');

        $this->client->request(
            'GET',
            '/admin/api/categories',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertCount(3, $responseData);
    }

    public function testListCategoriesAsRegularUserFails(): void
    {
        $token = $this->getAuthToken('regular@test.com', 'password123');

        $this->client->request(
            'GET',
            '/admin/api/categories',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(403); // Forbidden
    }

    // ==========================================================
    // == Tests for POST /admin/api/categories (Create/Undelete)
    // ==========================================================

    public function testCreateNewCategorySuccessfully(): void
    {
        $token = $this->getAuthToken('superadmin@test.com', 'password123');

        $this->client->request(
            'POST',
            '/admin/api/categories',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'New Category'])
        );

        $this->assertResponseStatusCodeSame(201); // Created
    }

    public function testCreateNewCategoryFails(): void
    {
        $token = $this->getAuthToken('superadmin@test.com', 'password123');

        $this->client->request(
            'POST',
            '/admin/api/categories',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUndeleteCategorySuccessfully(): void
    {
        $token = $this->getAuthToken('superadmin@test.com', 'password123');

        $this->client->request(
            'POST',
            '/admin/api/categories',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Entertainment'])
        );

        $this->assertResponseIsSuccessful(); // 200 OK
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['is_deleted']);
    }

    public function testUndeleteCategoryFails(): void
    {
        $token = $this->getAuthToken('superadmin@test.com', 'password123');

        $this->client->request(
            'POST',
            '/admin/api/categories',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Utilities'])
        );

        $this->assertResponseStatusCodeSame(409);
    }

    // ==========================================================
    // == Tests for DELETE /admin/api/categories/{id} (Soft Delete)
    // ==========================================================

    public function testSoftDeleteCategorySuccessfully(): void
    {
        $token = $this->getAuthToken('superadmin@test.com', 'password123');
        $categoryRepository = static::getContainer()->get(CategoryRepository::class);
        $categoryToDelete = $categoryRepository->findOneBy(['name' => 'Groceries']);
        $categoryId = $categoryToDelete->getId();
        $this->assertFalse($categoryToDelete->isDeleted());

        $this->client->request(
            'DELETE',
            '/admin/api/categories/' . $categoryToDelete->getId(),
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(204); // No Content

        $updatedCategory = $categoryRepository->find($categoryId);
        $this->assertTrue($updatedCategory->isDeleted());
    }
}
