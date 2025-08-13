<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsVerified(true);
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        // Create regular users
        $users = [
            ['email' => 'test@example.com', 'password' => 'test123'],
            ['email' => 'demo@example.com', 'password' => 'demo123'],
        ];

        foreach ($users as $userData) {
            $user = new User();
            $user->setEmail($userData['email']);
            $user->setRoles(['ROLE_USER']);
            $user->setIsVerified(true);
            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);
            $manager->persist($user);
        }

        // Create unverified user for testing email confirmation
        $unverifiedUser = new User();
        $unverifiedUser->setEmail('unverified@example.com');
        $unverifiedUser->setRoles(['ROLE_USER']);
        $unverifiedUser->setIsVerified(false);
        $hashedPassword = $this->passwordHasher->hashPassword($unverifiedUser, 'unverified123');
        $unverifiedUser->setPassword($hashedPassword);
        $manager->persist($unverifiedUser);

        $manager->flush();
    }
}
