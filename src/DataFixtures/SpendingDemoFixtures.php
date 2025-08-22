<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\User;
use App\Entity\Family;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SpendingDemoFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $family = $this->getOrCreateFamily($manager, 'Familia Demo', '3000.00');

        $plainPassword = 'password123';

        $users = [
            [
                'email' => 'user@gmail.com',
                'name'  => 'User Demo',
                'roles' => ['ROLE_ADMIN'], // family admin
            ],
            [
                'email' => 'sebi@gmail.com',
                'name'  => 'Sebi Demo',
                'roles' => ['ROLE_MEMBER'],
            ],
            [
                'email' => 'user2@gmail.com',
                'name'  => 'User2 Demo',
                'roles' => ['ROLE_MEMBER'],
            ],
        ];

        $userEntities = [];
        foreach ($users as $cfg) {
            $userEntities[$cfg['email']] = $this->getOrCreateUser(
                $manager,
                email: $cfg['email'],
                name: $cfg['name'],
                roles: $cfg['roles'],
                family: $family,
                plainPassword: $plainPassword
            );
        }

        $catFood          = $this->getOrCreateCategory($manager, 'Food');
        $catTransport     = $this->getOrCreateCategory($manager, 'Transport');
        $catUtilities     = $this->getOrCreateCategory($manager, 'Utilities');
        $catEntertainment = $this->getOrCreateCategory($manager, 'Entertainment');
        $categories       = [$catFood, $catTransport, $catUtilities, $catEntertainment];

        $julyDays = [1, 2, 3, 5, 7, 9, 10, 12, 13, 15, 16, 18, 19, 21, 22, 24, 25, 27, 28, 30];
        $augDays  = [1, 2, 3, 4, 5, 7, 8, 9, 11, 12, 14, 16, 18, 20, 22];

        $amountProfiles = [
            'user@gmail.com'  => [30, 120],
            'sebi@gmail.com'  => [20, 90],
            'user2@gmail.com' => [40, 150],
        ];

        foreach ($userEntities as $email => $user) {
            if (!$user instanceof User) {
                continue;
            }

            [$minA, $maxA] = $amountProfiles[$email] ?? [25, 100];

            foreach ($julyDays as $i => $day) {
                $date = \DateTime::createFromFormat('Y-m-d H:i:s', sprintf('2025-07-%02d 12:00:00', $day));
                $category = $this->pickCategory($categories, $i);
                $amount = $this->amountForIndex($minA, $maxA, $i);
                $name = $this->nameForCategory($category->getName(), $day);
                $this->addExpense($manager, $user, $category, $date, $amount, $name);
            }

            foreach ($augDays as $i => $day) {
                $date = \DateTime::createFromFormat('Y-m-d H:i:s', sprintf('2025-08-%02d 12:00:00', $day));
                $category = $this->pickCategory($categories, $i + 100);
                $amount = $this->amountForIndex($minA, $maxA, $i + 100);
                $name = $this->nameForCategory($category->getName(), $day);
                $this->addExpense($manager, $user, $category, $date, $amount, $name);
            }
        }

        $manager->flush();
    }

    private function getOrCreateFamily(ObjectManager $manager, string $name, string $monthlyBudget): Family
    {
        $repo = $manager->getRepository(Family::class);
        $family = $repo->findOneBy(['name' => $name]);
        if (!$family instanceof Family) {
            $family = new Family();
            $family->setName($name);
            $family->setMonthlyTargetBudget($monthlyBudget);
            $manager->persist($family);
        } else {
            if ($family->getMonthlyTargetBudget() !== $monthlyBudget) {
                $family->setMonthlyTargetBudget($monthlyBudget);
            }
        }
        return $family;
    }

    private function getOrCreateUser(
        ObjectManager $manager,
        string $email,
        string $name,
        array $roles,
        Family $family,
        string $plainPassword
    ): User {
        $repo = $manager->getRepository(User::class);
        $user = $repo->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $user = new User();
            $user->setEmail($email);
            $user->setName($name);
            $user->setRoles($roles);
            $user->setFamily($family);
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $manager->persist($user);
            return $user;
        }

        if ($user->getName() !== $name) {
            $user->setName($name);
        }
        $user->setRoles($roles);
        if ($user->getFamily() !== $family) {
            $user->setFamily($family);
        }
        // $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        return $user;
    }

    private function getOrCreateCategory(ObjectManager $manager, string $name): Category
    {
        $repo = $manager->getRepository(Category::class);
        $cat = $repo->findOneBy(['name' => $name]);
        if ($cat instanceof Category) {
            return $cat;
        }
        $cat = new Category();
        $cat->setName($name);
        if (method_exists($cat, 'setIsDeleted')) {
            $cat->setIsDeleted(false);
        }
        $manager->persist($cat);
        return $cat;
    }

    private function pickCategory(array $categories, int $index): Category
    {
        $idx = $index % max(1, count($categories));
        return $categories[$idx];
    }

    private function amountForIndex(float $min, float $max, int $index): float
    {
        $span = max(1.0, $max - $min);
        $k = (sin($index * 1.7) + 1.0) / 2.0; // 0..1
        $value = $min + $k * $span;
        return round($value, 2);
    }

    private function nameForCategory(string $categoryName, int $day): string
    {
        return match ($categoryName) {
            'Food' => 'Groceries D' . $day,
            'Transport' => 'Transport Ticket D' . $day,
            'Utilities' => 'Utilities D' . $day,
            'Entertainment' => 'Entertainment D' . $day,
            default => 'Expense D' . $day,
        };
    }

    private function addExpense(
        ObjectManager $manager,
        User $user,
        Category $category,
        \DateTime $date,
        float $amount,
        string $name
    ): void
    {
        $expense = new Expense();
        $expense->setName($name);
        $expense->setAmount((string) $amount);
        $expense->setCategory($category);

        if (method_exists($expense, 'setUserObject')) {
            $expense->setUserObject($user);
        } elseif (method_exists($expense, 'setUser')) {
            $expense->setUser($user);
        }

        $expense->setDate($date);
        $manager->persist($expense);
    }

    public static function getGroups(): array
    {
        return ['SpendingDemoFixtures'];
    }
}
