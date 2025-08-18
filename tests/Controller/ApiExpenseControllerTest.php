<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Repository\CategoryRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiExpenseControllerTest extends WebTestCase
{
    private AbstractDatabaseTool $databaseTool;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $this->databaseTool->loadFixtures([AppFixtures::class]);
    }

    private function getAuthToken(string $email, string $password): string
    {
        $this->client->request(
            'POST',
            '/api/login',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['token'];
    }

    // ===============================================
    // == Tests for POST /api/expense (Add Expense)
    // ===============================================

    public function testAddExpenseAsMemberForSelfSuccessfully(): void
    {
        $token = $this->getAuthToken('membru@popescu.com', 'password123');
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['name' => 'Food']);

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Pizza Night',
                'amount' => 25.50,
                'category_id' => $category->getId()
            ])
        );

        $this->assertResponseStatusCodeSame(201); // Created
        $this->assertJson($this->client->getResponse()->getContent());
    }

    public function testAddExpenseAsMemberForSelfWithoutJsonBodyFails(): void
    {
        $token = $this->getAuthToken('membru@popescu.com', 'password123');

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAddExpenseAsMemberForAnotherUserFails(): void
    {
        $token = $this->getAuthToken('membru@popescu.com', 'password123');
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['name' => 'Food']);
        $otherUser = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'alt.user@alt.com']);

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Invalid Expense',
                'amount' => 10,
                'category_id' => $category->getId(),
                'user_id' => $otherUser->getId()
            ])
        );

        $this->assertResponseStatusCodeSame(403); // Forbidden
    }

    public function testAddExpenseAsAdminForFamilyMemberSuccessfully(): void
    {
        $token = $this->getAuthToken('admin@popescu.com', 'password123');
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['name' => 'Food']);
        $familyMember = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'membru@popescu.com']);

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Admin added expense',
                'amount' => 50,
                'category_id' => $category->getId(),
                'user_id' => $familyMember->getId()
            ])
        );

        $this->assertResponseStatusCodeSame(201);
    }

    public function testAddExpenseAsAdminForOtherFamilyFails(): void
    {
        $token = $this->getAuthToken('admin@popescu.com', 'password123');
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['name' => 'Food']);
        $otherUser = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'alt.user@alt.com']);

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Invalid Expense',
                'amount' => 10,
                'category_id' => $category->getId(),
                'user_id' => $otherUser->getId()
            ])
        );

        $this->assertResponseStatusCodeSame(403); // 403 Forbidden
    }

    private function getValidExpenseData(): array
    {
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['name' => 'Food']);
        return [
            'name' => 'Test Expense',
            'amount' => 100,
            'category_id' => $category->getId()
        ];
    }

    public static function provideMissingFieldsData(): \Generator
    {
        yield 'missing name' => [['amount' => 50, 'category_id' => 1]];
        yield 'missing amount' => [['name' => 'Test', 'category_id' => 1]];
        yield 'missing category_id' => [['name' => 'Test', 'amount' => 50]];
    }

    #[DataProvider('provideMissingFieldsData')]
    public function testAddExpenseWithMissingRequiredFieldsFails(array $invalidData): void
    {
        $token = $this->getAuthToken('membru@popescu.com', 'password123');

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($invalidData)
        );

        $this->assertResponseStatusCodeSame(400); // Bad Request
    }

    public function testAddExpenseWithNonNumericAmountFails(): void
    {
        $token = $this->getAuthToken('membru@popescu.com', 'password123');
        $data = $this->getValidExpenseData();
        $data['amount'] = 'not-a-number';

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(400); // Bad Request
    }

    public function testAddExpenseAsAdminWithNonExistentUserFails(): void
    {
        $token = $this->getAuthToken('admin@popescu.com', 'password123');
        $data = $this->getValidExpenseData();
        $data['user_id'] = 99999;

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(404); // Not Found
    }

    public function testAddExpenseWithNonExistentCategoryFails(): void
    {
        $token = $this->getAuthToken('membru@popescu.com', 'password123');
        $data = $this->getValidExpenseData();
        $data['category_id'] = 99999;

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(404); // Not Found
    }

    public function testAddExpenseWithInvalidDateFormatFails(): void
    {
        $token = $this->getAuthToken('membru@popescu.com', 'password123');
        $data = $this->getValidExpenseData();
        $data['date'] = 'this-is-not-a-valid-date';

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(400); // Bad Request
    }

    public function testAddExpenseWithSubscriptionSuccessfully(): void
    {
        $token = $this->getAuthToken('membru@popescu.com', 'password123');

        $subscriptionRepository = static::getContainer()->get(SubscriptionRepository::class);
        $subscription = $subscriptionRepository->findOneBy(['name' => 'Netflix']);

        $data = $this->getValidExpenseData();
        $data['subscription_id'] = $subscription->getId();

        $this->client->request(
            'POST',
            '/api/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $this->assertResponseStatusCodeSame(201); // Created

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);
        $this->assertSame($subscription->getId(), $responseData['subscription_id']);
    }

    // ====================================================
    // == Tests for GET /api/user/{id}/expense (List)
    // ====================================================

    public function testListExpensesAsOwnerSuccessfully(): void
    {
        $token = $this->getAuthToken('membru@popescu.com', 'password123');
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'membru@popescu.com']);

        $this->client->request(
            'GET',
            '/api/user/' . $user->getId() . '/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testListExpensesAsOtherUserFails(): void
    {
        $token = $this->getAuthToken('alt.user@alt.com', 'password123');
        $userToView = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'membru@popescu.com']);

        $this->client->request(
            'GET',
            '/api/user/' . $userToView->getId() . '/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(403); // Forbidden
    }

    public function testListExpensesForNonExistentUserFails(): void
    {
        $token = $this->getAuthToken('admin@popescu.com', 'password123');
        $nonExistentUserId = 99999;

        $this->client->request(
            'GET',
            '/api/user/' . $nonExistentUserId . '/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(404);

        $responseContent = $this->client->getResponse()->getContent();
        $this->assertJson($responseContent);
        $responseData = json_decode($responseContent, true);
        $this->assertSame('User not found', $responseData['message']);
    }

    public function testListExpensesFailsWhenUnauthenticated(): void
    {
        $this->client->request(
            'GET',
            '/api/user/1/expense'
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListExpensesAsAdminForFamilyMemberSuccessfully(): void
    {
        $token = $this->getAuthToken('admin@popescu.com', 'password123');
        $userToView = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'membru@popescu.com']);

        $this->client->request(
            'GET',
            '/api/user/' . $userToView->getId() . '/expense',
            [], [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
    }
}
