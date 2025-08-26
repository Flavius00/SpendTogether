<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\Subscription;
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
        // Family + users
        $family = $this->getOrCreateFamily($manager, 'Familia Demo', '3000.00');

        $plainPassword = 'password123';

        $usersConfig = [
            [
                'email' => 'user@gmail.com',
                'name'  => 'User Demo',
                'roles' => ['ROLE_ADMIN'],
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
        foreach ($usersConfig as $cfg) {
            $userEntities[$cfg['email']] = $this->getOrCreateUser(
                $manager,
                email: $cfg['email'],
                name: $cfg['name'],
                roles: $cfg['roles'],
                family: $family,
                plainPassword: $plainPassword
            );
        }

        // Categories
        $catFood          = $this->getOrCreateCategory($manager, 'Food');
        $catTransport     = $this->getOrCreateCategory($manager, 'Transport');
        $catUtilities     = $this->getOrCreateCategory($manager, 'Utilities');
        $catEntertainment = $this->getOrCreateCategory($manager, 'Entertainment');
        $categories       = [$catFood, $catTransport, $catUtilities, $catEntertainment];

        // Amount profiles (min, max) per user for one-time expenses
        $amountProfiles = [
            'user@gmail.com'  => [30, 120],
            'sebi@gmail.com'  => [20, 90],
            'user2@gmail.com' => [40, 150],
        ];

        // Date range for historical data
        $from = new \DateTime('2024-08-26 00:00:00');
        $to   = new \DateTime('today 23:59:59');

        foreach ($userEntities as $email => $user) {
            if (!$user instanceof User) {
                continue;
            }
            [$minA, $maxA] = $amountProfiles[$email] ?? [25, 100];

            // Variație: definim subscripții diferite per user, cu ferestre de activitate și "skip" periodic
            $subsDefs = $this->subscriptionDefinitionsForUser($user, $catUtilities, $catEntertainment, $catTransport);

            // Crează/actualizează subscripțiile și asamblează descriptorii
            $subscriptions = $this->getOrCreateUserSubscriptions(
                $manager,
                $user,
                $subsDefs,
                baseStart: new \DateTime('2024-08-01 00:00:00')
            );

            // Generează istoricul de cheltuieli din subscripții în [from .. to] cu variații lunare
            $subDaysByMonth = $this->generateSubscriptionExpensesHistory(
                $manager,
                $user,
                $subscriptions,
                $from,
                $to
            );

            // Generează one-time expenses cu țintă dinamică de zile/lună (>=20 când este posibil)
            $this->generateOneTimeExpensesByMonth(
                $manager,
                $user,
                $categories,
                $from,
                $to,
                $minA,
                $maxA,
                $subDaysByMonth
            );
        }

        $manager->flush();
    }

    private function subscriptionDefinitionsForUser(User $user, Category $catUtilities, Category $catEntertainment, Category $catTransport): array
    {
        $u = $user->getEmail();

        if ($u === 'user@gmail.com') {
            return [
                // name, amount, category, day, start, end?, skip?
                ['name' => 'Internet',      'amount' => '50.00',  'category' => $catUtilities,     'day' => 3,  'start' => new \DateTime('2024-08-01')],
                ['name' => 'Mobile Plan',   'amount' => '25.00',  'category' => $catUtilities,     'day' => 7,  'start' => new \DateTime('2024-09-01')],
                ['name' => 'Streaming',     'amount' => '42.00',  'category' => $catEntertainment, 'day' => 12, 'start' => new \DateTime('2024-08-01'), 'skip' => ['mod' => 6, 'phase' => 0]], // sare fiecare a 6-a lună
                ['name' => 'Gym',           'amount' => '30.00',  'category' => $catEntertainment, 'day' => 18, 'start' => new \DateTime('2024-10-01'), 'end' => new \DateTime('2025-06-30')], // anulat vara
                ['name' => 'Parking',       'amount' => '60.00',  'category' => $catTransport,     'day' => 24, 'start' => new \DateTime('2024-08-01')],
                ['name' => 'Cloud Storage', 'amount' => '9.99',   'category' => $catUtilities,     'day' => 5,  'start' => new \DateTime('2024-11-01')],
                ['name' => 'Music',         'amount' => '12.99',  'category' => $catEntertainment, 'day' => 15, 'start' => new \DateTime('2025-02-01')],
            ];
        }

        if ($u === 'sebi@gmail.com') {
            return [
                ['name' => 'Internet',    'amount' => '45.00',  'category' => $catUtilities,     'day' => 4,  'start' => new \DateTime('2024-08-01')],
                ['name' => 'Streaming',   'amount' => '35.00',  'category' => $catEntertainment, 'day' => 14, 'start' => new \DateTime('2024-12-01')],
                ['name' => 'Gym',         'amount' => '25.00',  'category' => $catEntertainment, 'day' => 20, 'start' => new \DateTime('2025-01-01')],
                ['name' => 'Parking',     'amount' => '50.00',  'category' => $catTransport,     'day' => 26, 'start' => new \DateTime('2024-08-01'), 'skip' => ['mod' => 4, 'phase' => 1]],
            ];
        }

        // user2@gmail.com
        return [
            ['name' => 'Mobile Plan',   'amount' => '20.00',  'category' => $catUtilities,     'day' => 8,  'start' => new \DateTime('2024-08-01')],
            ['name' => 'Streaming',     'amount' => '45.00',  'category' => $catEntertainment, 'day' => 13, 'start' => new \DateTime('2024-08-01')],
            ['name' => 'Cloud Storage', 'amount' => '9.99',   'category' => $catUtilities,     'day' => 6,  'start' => new \DateTime('2025-03-01'), 'end' => new \DateTime('2025-12-31')],
        ];
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

    /**
     * @param array<array{
     *     name:string, amount:string, category:Category, day:int,
     *     start:\DateTime, end?:\DateTime, skip?:array{mod:int,phase:int}
     * }> $defs
     * @return array<int,array{sub:Subscription,day:int,start:\DateTime,end:?\DateTime,skip?:array{mod:int,phase:int}}>
     */
    private function getOrCreateUserSubscriptions(
        ObjectManager $manager,
        User $user,
        array $defs,
        \DateTime $baseStart
    ): array {
        $subs = [];
        foreach ($defs as $def) {
            // Defensive: accept și listă numerică
            if (array_is_list($def)) {
                [$n, $a, $c, $d] = $def;
                $def = ['name' => $n, 'amount' => $a, 'category' => $c, 'day' => $d, 'start' => $baseStart];
            }

            $sub = $this->getOrCreateSubscription(
                $manager,
                $user,
                $def['name'],
                $def['amount'],
                $def['category'],
                $baseStart,
                'monthly',
                $def['day']
            );

            $subs[] = [
                'sub'   => $sub,
                'day'   => (int) $def['day'],
                'start' => $def['start'] ?? $baseStart,
                'end'   => $def['end']   ?? null,
                'skip'  => $def['skip']  ?? null,
            ];
        }
        return $subs;
    }

    private function getOrCreateSubscription(
        ObjectManager $manager,
        User $user,
        string $name,
        string $amount,
        Category $category,
        \DateTime $createdAt,
        string $frequency,
        int $anchorDay
    ): Subscription {
        $repo = $manager->getRepository(Subscription::class);
        $existing = $repo->findOneBy([
            'userObject' => $user,
            'name' => $name,
        ]);

        if ($existing instanceof Subscription) {
            // Keep data fresh
            $existing->setAmount($amount);
            $existing->setCategory($category);
            $existing->setFrequency($frequency);
            $existing->setNextDueDate($this->nextDueAfter(new \DateTime(), $anchorDay));
            $existing->setIsActive(true);
            return $existing;
        }

        $sub = new Subscription();
        $sub->setUserObject($user);
        $sub->setName($name);
        $sub->setAmount($amount);
        $sub->setCategory($category);
        $sub->setFrequency($frequency);
        $sub->setIsActive(true);
        $sub->setNextDueDate($this->nextDueAfter(new \DateTime(), $anchorDay));
        $manager->persist($sub);

        return $sub;
    }

    /**
     * Generează cheltuieli din subscripții în [from .. to], cu ferestre active și opțional skip periodic.
     * @param array<int,array{sub:Subscription,day:int,start:\DateTime,end:?\DateTime,skip?:array{mod:int,phase:int}}> $subscriptions
     * @return array<string,array<int,true>> e.g. ['2025-07' => [3=>true, 7=>true, ...]]
     */
    private function generateSubscriptionExpensesHistory(
        ObjectManager $manager,
        User $user,
        array $subscriptions,
        \DateTime $from,
        \DateTime $to
    ): array {
        $map = []; // month => {day => true}

        $monthCursor = (clone $from)->modify('first day of this month')->setTime(0, 0, 0);
        $endMonth    = (clone $to)->modify('first day of this month')->setTime(0, 0, 0);

        while ($monthCursor <= $endMonth) {
            $ym = $monthCursor->format('Y-m');
            $daysInMonth = (int) $monthCursor->format('t');
            $monthStartDay = ($monthCursor->format('Y-m') === $from->format('Y-m')) ? (int) $from->format('j') : 1;
            $monthEndDay   = ($monthCursor->format('Y-m') === $to->format('Y-m')) ? (int) $to->format('j') : $daysInMonth;

            foreach ($subscriptions as $desc) {
                /** @var Subscription $sub */
                $sub   = $desc['sub'];
                $day   = (int) ($desc['day'] ?? 10);
                $start = $desc['start'] ?? (clone $from);
                $end   = $desc['end'] ?? null;
                $skip  = $desc['skip'] ?? null;

                // Ferestre active
                if ($this->isBeforeMonth($monthCursor, $start)) {
                    continue;
                }
                if ($end !== null && $this->isAfterMonth($monthCursor, $end)) {
                    continue;
                }

                // Skip pattern (ex: mod=6, phase=0 => sare când diff%6==0)
                if (is_array($skip) && isset($skip['mod'], $skip['phase'])) {
                    $diff = $this->monthDiff($start, $monthCursor);
                    if ($diff >= 0 && ($diff % (int)$skip['mod']) === (int)$skip['phase']) {
                        continue;
                    }
                }

                $chargeDay = min($day, $daysInMonth);
                if ($chargeDay < $monthStartDay || $chargeDay > $monthEndDay) {
                    continue;
                }
                $chargeDate = \DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%s-%02d 10:00:00', $ym, $chargeDay));
                if ($chargeDate > $to) {
                    continue; // nu creăm cheltuieli din viitor
                }

                // Create expense for this subscription (nume compatibil cu validatorul)
                $this->addExpense(
                    manager: $manager,
                    user: $user,
                    category: $sub->getCategory(),
                    date: $chargeDate,
                    amount: (float) $sub->getAmount(),
                    name: 'Subscription ' . $sub->getName(),
                    subscription: $sub
                );

                $map[$ym][$chargeDay] = true;
            }

            $monthCursor->modify('+1 month');
        }

        return $map;
    }

    private function isBeforeMonth(\DateTime $a, \DateTime $b): bool
    {
        // a < month(b)
        $aKey = (int)$a->format('Y') * 12 + (int)$a->format('n');
        $bKey = (int)$b->format('Y') * 12 + (int)$b->format('n');
        return $aKey < $bKey;
    }

    private function isAfterMonth(\DateTime $a, \DateTime $b): bool
    {
        // a > month(b)
        $aKey = (int)$a->format('Y') * 12 + (int)$a->format('n');
        $bKey = (int)$b->format('Y') * 12 + (int)$b->format('n');
        return $aKey > $bKey;
    }

    private function monthDiff(\DateTime $start, \DateTime $end): int
    {
        $s = (int)$start->format('Y') * 12 + (int)$start->format('n');
        $e = (int)$end->format('Y') * 12 + (int)$end->format('n');
        return $e - $s;
    }

    private function nextDueAfter(\DateTime $ref, int $anchorDay): \DateTime
    {
        $currentDaysInMonth = (int) $ref->format('t');
        $candidateDay = min($anchorDay, $currentDaysInMonth);
        $candidate = \DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%s-%02d 10:00:00', $ref->format('Y-m'), $candidateDay));
        if ($candidate < $ref) {
            $next = (clone $ref)->modify('first day of next month');
            $nextDays = (int) $next->format('t');
            $nextDay = min($anchorDay, $nextDays);
            return \DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%s-%02d 10:00:00', $next->format('Y-m'), $nextDay));
        }
        return $candidate;
    }

    /**
     * Generate one-time expenses per month with dynamic target days (>=20 when possible).
     *
     * @param array<string,array<int,true>> $subDaysByMonth
     */
    private function generateOneTimeExpensesByMonth(
        ObjectManager $manager,
        User $user,
        array $categories,
        \DateTime $from,
        \DateTime $to,
        float $minA,
        float $maxA,
        array $subDaysByMonth
    ): void {
        $monthCursor = (clone $from)->modify('first day of this month')->setTime(0, 0, 0);
        $endMonth    = (clone $to)->modify('first day of this month')->setTime(0, 0, 0);

        while ($monthCursor <= $endMonth) {
            $ym = $monthCursor->format('Y-m');
            $daysInMonth = (int) $monthCursor->format('t');

            $monthStartDay = ($monthCursor->format('Y-m') === $from->format('Y-m')) ? (int) $from->format('j') : 1;
            $monthEndDay   = ($monthCursor->format('Y-m') === $to->format('Y-m')) ? (int) $to->format('j') : $daysInMonth;

            $availableDays = range($monthStartDay, $monthEndDay);
            $possibleDays  = count($availableDays);

            // Already used by subscriptions
            $subDays = array_keys($subDaysByMonth[$ym] ?? []);
            $usedDays = array_intersect($availableDays, $subDays);

            // Seasonal + jitter per user to vary target days (keep at least 20 when possible)
            $seasonalAdj = $this->seasonalAdjustment((int)$monthCursor->format('n'));
            $seed = crc32($user->getEmail().'|'.$ym.'|onetimes');
            $jitter = (int)($seed % 7) - 3; // -3..+3

            $targetRaw = 20 + $seasonalAdj + $jitter;
            $targetRaw = max(10, $targetRaw); // protecție minimală
            $targetDays = min($possibleDays, max(20, min(28, $targetRaw))); // între 20..28 când se poate

            $missingDaysCount = max(0, $targetDays - count($usedDays));

            // Candidate days = available days excluding subscription days
            $candidateDays = array_values(array_diff($availableDays, $usedDays));

            // Deterministic selection per user+month
            $selectedDays = $this->pickUniqueDays($candidateDays, $missingDaysCount, $seed);

            // Create one-time expenses for selected days (1 per day)
            $i = 0;
            foreach ($selectedDays as $day) {
                $date = \DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%s-%02d 12:00:00', $ym, $day));
                $category = $this->pickCategory($categories, (int) ($seed + $i));
                $amount = $this->amountForIndex($minA, $maxA, (int) ($seed % 1000) + $i);
                $name = $this->nameForCategory($category->getName(), $day);
                $this->addExpense($manager, $user, $category, $date, $amount, $name);
                $i++;
            }

            $monthCursor->modify('+1 month');
        }
    }

    private function seasonalAdjustment(int $month): int
    {
        return match ($month) {
            12 => 6,          // Decembrie: mai mult consum
            6,7,8 => 3,       // vară
            11,1 => 2,        // prag de sărbători / început de an
            2 => -2,          // Februarie: mai scurtă
            default => 0,
        };
    }

    /**
     * Deterministic unique day picker with a seed.
     * @param int[] $days
     * @return int[]
     */
    private function pickUniqueDays(array $days, int $count, int $seed): array
    {
        if ($count <= 0 || empty($days)) {
            return [];
        }
        // Deterministic shuffle
        $pairs = [];
        foreach ($days as $d) {
            $h = crc32($seed.'|'.$d);
            $pairs[] = [$h, $d];
        }
        usort($pairs, static fn($a, $b) => $a[0] <=> $b[0]);
        $selected = array_slice(array_column($pairs, 1), 0, $count);
        sort($selected);
        return $selected;
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
        string $name,
        ?Subscription $subscription = null
    ): void {
        $expense = new Expense();
        $expense->setName($name);
        $expense->setAmount(number_format($amount, 2, '.', ''));
        $expense->setCategory($category);

        if (method_exists($expense, 'setUserObject')) {
            $expense->setUserObject($user);
        } elseif (method_exists($expense, 'setUser')) {
            $expense->setUser($user);
        }

        if ($subscription) {
            $expense->setSubscription($subscription);
        }

        $expense->setDate($date);
        $manager->persist($expense);
    }

    public static function getGroups(): array
    {
        return ['SpendingDemoFixtures'];
    }
}
