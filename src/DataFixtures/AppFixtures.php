<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\Family;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $familyPopescu = new Family();
        $familyPopescu->setName('Familia Popescu');
        $familyPopescu->setMonthlyTargetBudget('1000');
        $manager->persist($familyPopescu);

        $familyAlt = new Family();
        $familyAlt->setName('Familia Alt');
        $familyAlt->setMonthlyTargetBudget('1000');
        $manager->persist($familyAlt);

        $admin = new User();
        $admin->setEmail('admin@popescu.com');
        $admin->setName('Admin Popescu');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password123'));
        $admin->setFamily($familyPopescu);
        $manager->persist($admin);

        $member = new User();
        $member->setEmail('membru@popescu.com');
        $member->setName('Membru Popescu');
        $member->setRoles(['ROLE_MEMBER']);
        $member->setPassword($this->passwordHasher->hashPassword($member, 'password123'));
        $member->setFamily($familyPopescu);
        $manager->persist($member);

        $otherUser = new User();
        $otherUser->setEmail('alt.user@alt.com');
        $otherUser->setName('Alt User');
        $otherUser->setRoles(['ROLE_ADMIN']);
        $otherUser->setPassword($this->passwordHasher->hashPassword($otherUser, 'password123'));
        $otherUser->setFamily($familyAlt);
        $manager->persist($otherUser);

        $categoryFood = new Category();
        $categoryFood->setName('Food');
        $categoryFood->setIsDeleted(false);
        $manager->persist($categoryFood);

        $categoryTransport = new Category();
        $categoryTransport->setName('Transport');
        $categoryTransport->setIsDeleted(false);
        $manager->persist($categoryTransport);

        $subscription = new Subscription();
        $subscription->setName('Netflix')
            ->setAmount('50.00')
            ->setCategory($categoryFood)
            ->setUserObject($member)
            ->setFrequency('monthly')
            ->setNextDueDate(new \DateTime('+30 days'))
            ->setIsActive(true);
        $manager->persist($subscription);

        $expense1 = new Expense();
        $expense1->setName('Cumparaturi saptamanale')
            ->setAmount('150.75')
            ->setCategory($categoryFood)
            ->setUserObject($member)
            ->setDate(new \DateTime('-5 days'));
        $manager->persist($expense1);

        $expense2 = new Expense();
        $expense2->setName('Bilet autobuz')
            ->setAmount('25.00')
            ->setCategory($categoryTransport)
            ->setUserObject($member)
            ->setDate(new \DateTime('-2 days'));
        $manager->persist($expense2);

        $expense3 = new Expense();
        $expense3->setName('Motorina')
            ->setAmount('250.00')
            ->setCategory($categoryTransport)
            ->setUserObject($admin)
            ->setDate(new \DateTime('-1 day'));
        $manager->persist($expense3);

        $expense4 = new Expense();
        $expense4->setName('Restaurant')
            ->setAmount('120.00')
            ->setCategory($categoryFood)
            ->setUserObject($otherUser)
            ->setDate(new \DateTime('-3 days'));
        $manager->persist($expense4);

        $manager->flush();
    }
}
