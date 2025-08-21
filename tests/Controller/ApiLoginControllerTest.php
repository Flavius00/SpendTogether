<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Repository\AccessTokenRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiLoginControllerTest extends WebTestCase
{
    private AbstractDatabaseTool $databaseTool;
    private KernelBrowser $client;


    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $this->databaseTool->loadFixtures([UserFixtures::class]);
    }

    // ====================================================
    // == Tests for POST /api/login (Login)
    // ====================================================

    public function testLoginSuccessfully(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'SecurePassword123',
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);
        $this->assertArrayHasKey('token', $responseData);
        $this->assertNotEmpty($responseData['token']);
    }

    public function testLoginWithInvalidCredentialsFails(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginWithInvalidJsonBody(): void
    {
        $this->client->request(
          'POST',
          '/api/login',
          [], [],
          ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testLoginWithNoEmailOrPasswordFails(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'password' => 'SecurePassword123',
            ])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    // ====================================================
    // == Tests for POST /api/logout (Logout)
    // ====================================================

    public function testLogoutSuccessfully(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'SecurePassword123'
            ])
        );

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);
        $token = $responseData['token'];
        $this->assertNotEmpty($token);

        $accessTokenRepository = static::getContainer()->get(AccessTokenRepository::class);
        $tokenEntityBefore = $accessTokenRepository->findOneBy(['token' => $token]);
        $this->assertNotNull($tokenEntityBefore);

        $this->client->request(
            'POST',
            '/api/logout',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();

        $logoutResponseContent = $this->client->getResponse()->getContent();
        $logoutResponseData = json_decode($logoutResponseContent, true);
        $this->assertArrayHasKey('message', $logoutResponseData);
        $this->assertSame('Successfully logged out', $logoutResponseData['message']);

        $tokenEntityAfter = $accessTokenRepository->findOneBy(['token' => $token]);
        $this->assertNull($tokenEntityAfter);
    }

    public function testLogoutWithoutAuthorizationHeaderFails(): void
    {
        $this->client->request(
            'POST',
            '/api/logout',
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLogoutWithInvalidTokenFails(): void
    {
        $this->client->request(
            'POST',
            '/api/logout',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer invalid-fake-token']
        );

        $this->assertResponseStatusCodeSame(401);
    }


    protected function tearDown(): void
    {
        parent::tearDown();

        //$this->databaseTool->loadFixtures([]);
    }

}
