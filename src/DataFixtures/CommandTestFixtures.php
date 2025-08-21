<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CommandTestFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $user1 = new User();
        $user1->setEmail('admin.to.be@test.com');
        $user1->setName('Future Admin');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password'));
        $manager->persist($user1);

        $user2 = new User();
        $user2->setEmail('member.to.be@test.com');
        $user2->setName('Future Member');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password'));
        $manager->persist($user2);

        $manager->flush();
    }
}
