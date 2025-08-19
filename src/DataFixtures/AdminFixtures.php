<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        $superAdmin = new User();
        $superAdmin->setEmail('superadmin@test.com');
        $superAdmin->setName('Super Admin');
        $superAdmin->setRoles(['ROLE_SUPER_ADMIN']);
        $superAdmin->setPassword($this->passwordHasher->hashPassword($superAdmin, 'password123'));
        $manager->persist($superAdmin);

        $regularUser = new User();
        $regularUser->setEmail('regular@test.com');
        $regularUser->setName('Regular User');
        $regularUser->setRoles(['ROLE_MEMBER']);
        $regularUser->setPassword($this->passwordHasher->hashPassword($regularUser, 'password123'));
        $manager->persist($regularUser);

        $category1 = new Category();
        $category1->setName('Groceries');
        $category1->setIsDeleted(false);
        $manager->persist($category1);

        $category2 = new Category();
        $category2->setName('Utilities');
        $category2->setIsDeleted(false);
        $manager->persist($category2);

        $deletedCategory = new Category();
        $deletedCategory->setName('Entertainment');
        $deletedCategory->setIsDeleted(true);
        $manager->persist($deletedCategory);

        $manager->flush();
    }
}
