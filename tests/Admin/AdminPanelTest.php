<?php

namespace App\Tests\Admin;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminPanelTest extends WebTestCase
{
    public function test(): void
    {
        $client = static::createClient();


        $userRepository = static::getContainer()->get(UserRepository::class);
        $admin = $userRepository->findOneBy(['email' => 'admin@example.com']);
        $client->loginUser($admin);

        $client->followRedirects();
        $client->request('GET', '/family/home');
    }
}
