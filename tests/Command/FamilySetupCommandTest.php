<?php

namespace App\Tests\Command;

use App\DataFixtures\CommandTestFixtures;
use App\Repository\FamilyRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class FamilySetupCommandTest extends KernelTestCase
{
    private AbstractDatabaseTool $databaseTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
    }

    public function testExecute(): void
    {
        $this->databaseTool->loadFixtures([CommandTestFixtures::class]);

        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('app:family:setup');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['1500']);

        $commandTester->execute([
            'admin-name' => 'Future Admin',
            'family-name' => 'Test Family',
            'member-names' => ['Future Member'],
        ]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Family \'Test Family\' created successfully', $output);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $familyRepository = static::getContainer()->get(FamilyRepository::class);

        $admin = $userRepository->findOneByEmail('admin.to.be@test.com');
        $member = $userRepository->findOneByEmail('member.to.be@test.com');
        $family = $familyRepository->findOneByName('Test Family');

        $this->assertNotNull($family);
        $this->assertSame($family, $admin->getFamily());
        $this->assertSame($family, $member->getFamily());
        $this->assertContains('ROLE_ADMIN', $admin->getRoles());
        $this->assertContains('ROLE_MEMBER', $member->getRoles());
    }
}
