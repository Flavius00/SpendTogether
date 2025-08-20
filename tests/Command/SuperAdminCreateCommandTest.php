<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SuperAdminCreateCommandTest extends KernelTestCase
{
    public function testExecute(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('app:super-admin:create');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['supersecret']);

        $commandTester->execute([
            'email' => 'superadmin@command.com',
            'name' => 'Super Admin from Test',
        ]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('SUPER_ADMIN user created successfully!', $output);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneByEmail('superadmin@command.com');
        $this->assertNotNull($user);
        $this->assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }
}
