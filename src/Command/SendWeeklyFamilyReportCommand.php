<?php

declare(strict_types=1);

namespace App\Command;

use App\Diagrams\Calculators\ProjectedSpendingCalculator;
use App\Entity\Expense;
use App\Entity\Family;
use App\Entity\User;
use App\Repository\FamilyRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;

#[AsCommand(
    name: 'app:send-weekly-family-report',
    description: 'Sends weekly (Monday) email to family admins with projection and monthly category breakdown'
)]
final class SendWeeklyFamilyReportCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly BodyRendererInterface $bodyRenderer,
        private readonly FamilyRepository $familyRepository,
        private readonly ProjectedSpendingCalculator $projectedCalculator,
        private readonly Address $fromAddress = new Address('reports@example.test', 'Weekly Reports') // replace via DI if needed
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // Optional: limit to a specific family id
            ->addOption('family-id', null, InputOption::VALUE_REQUIRED, 'Send only for this family id')
            // Optional: do not actually send emails, just print what would be sent
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not send, just simulate')
            // Optional: skip if not Monday (if cron is not set precisely)
            ->addOption('only-monday', null, InputOption::VALUE_NONE, 'Exit if today is not Monday');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Safety: optionally exit if not Monday (cron can control anyway)
        if ($input->getOption('only-monday')) {
            $dow = (int) (new \DateTime())->format('N'); // 1 = Monday
            if ($dow !== 1) {
                $io->writeln('<comment>Not Monday, skipping.</comment>');
                return Command::SUCCESS;
            }
        }

        $familyId = $input->getOption('family-id');
        $dryRun = (bool) $input->getOption('dry-run');

        $selectedMonth = (new \DateTime('first day of this month'))->format('Y-m');

        // Load target families
        $families = [];
        if ($familyId !== null) {
            $family = $this->familyRepository->find((int) $familyId);
            if (!$family) {
                $io->error(sprintf('Family id %s not found.', $familyId));
                return Command::FAILURE;
            }
            $families = [$family];
        } else {
            $families = $this->familyRepository->findAll();
        }

        $sentCount = 0;
        foreach ($families as $family) {
            if (!$family instanceof Family) {
                continue;
            }

            // Admins within this family
            $admins = $this->collectFamilyAdmins($family);
            if (count($admins) === 0) {
                $io->writeln(sprintf('<comment>No admins for family #%s, skipping.</comment>', (string)$family->getId()));
                continue;
            }

            // Resolve month context (reuses ProjectedSpendingCalculator)
            [$monthStart, $monthEnd, $daysInMonth] = $this->projectedCalculator->resolveMonthContext($selectedMonth);
            $monthStart = $monthStart instanceof \DateTime ? $monthStart : new \DateTime('first day of this month');
            $monthEnd = $monthEnd instanceof \DateTime ? $monthEnd : (new \DateTime('last day of this month'))->setTime(23, 59, 59);

            // Accumulate daily totals for current and previous month (family)
            $dailyCurrent = array_fill(1, (int) $daysInMonth, 0.0);
            $this->accumulateFamilyMonthExpenses($family, $dailyCurrent, $monthStart, $monthEnd);

            $prevStart = (clone $monthStart)->modify('first day of previous month')->setTime(0, 0, 0);
            $prevEnd   = (clone $prevStart)->modify('last day of this month')->setTime(23, 59, 59);
            $prevDays  = (int) $prevStart->format('t');
            $dailyPrev = array_fill(1, $prevDays, 0.0);
            $this->accumulateFamilyMonthExpenses($family, $dailyPrev, $prevStart, $prevEnd);

            // Family budget (if any)
            $budget = null;
            $familyBudget = $family->getMonthlyTargetBudget();
            if ($familyBudget !== null && (float) $familyBudget > 0) {
                $budget = (float) $familyBudget;
            }

            // Projection (reuse the calculator used in your dashboard)
            $proj = $this->projectedCalculator->calculate($selectedMonth, $dailyCurrent, $dailyPrev, $budget);

            // Category breakdown for current month (family-level)
            $categoryTotals = $this->computeFamilyCategoryTotals($family, $monthStart, $monthEnd);
            $categoryBreakdown = $this->toPercentages($categoryTotals);

            // Prepare and send one email per admin
            foreach ($admins as $admin) {
                $to = $admin->getEmail();
                if (!$to) {
                    continue;
                }

                // Build the templated email
                $email = (new TemplatedEmail())
                    ->from($this->fromAddress)
                    ->to($to)
                    ->subject(sprintf(
                        'Weekly family spending report — %s — %s',
                        (string) ($family->getName() ?? ('Family #'.(string) $family->getId())),
                        (new \DateTime())->format('M Y')
                    ))
                    ->htmlTemplate('email/weekly_family_report.html.twig')
                    ->context([
                        'family'            => $family,
                        'month_label'       => (new \DateTime($selectedMonth.'-01'))->format('F Y'),
                        'projected_total'   => $proj->projectedTotal,
                        'current_to_date'   => $proj->currentToDate,
                        'prev_to_date'      => $proj->prevToDate,
                        'growth_pct'        => $this->formatGrowthPct($proj->currentToDate, $proj->prevToDate),
                        'budget'            => $budget,
                        'budget_hit'        => $proj->budgetHit?->format('M j'),
                        'category_breakdown'=> $categoryBreakdown, // array of [name => ['amount' => float, 'percent' => float]]
                    ]);

                // Render body (also generates text alternative)
                $this->bodyRenderer->render($email);

                if ($dryRun) {
                    $io->writeln(sprintf('[DRY-RUN] Would send weekly report to %s (family #%s)', $to, (string)$family->getId()));
                } else {
                    $this->mailer->send($email);
                    $sentCount++;
                    $io->writeln(sprintf('Sent weekly report to %s (family #%s)', $to, (string)$family->getId()));
                }
            }
        }

        $io->success(sprintf('Done. Emails sent: %d', $sentCount));

        return Command::SUCCESS;
    }

    /**
     * @return User[]
     */
    private function collectFamilyAdmins(Family $family): array
    {
        $admins = [];
        foreach ($family->getUsers() as $u) {
            if (!$u instanceof User) {
                continue;
            }
            if (in_array('ROLE_ADMIN', $u->getRoles(), true)) {
                $admins[] = $u;
            }
        }
        return $admins;
    }

    /**
     * @param array<int,float> $daily
     */
    private function accumulateFamilyMonthExpenses(Family $family, array &$daily, \DateTime $start, \DateTime $end): void
    {
        foreach ($family->getUsers() as $u) {
            if (!$u instanceof User) {
                continue;
            }
            foreach ($u->getExpenses() as $expense) {
                if (!$expense instanceof Expense) {
                    continue;
                }
                $d = $expense->getDate();
                if (!$d || $d < $start || $d > $end) {
                    continue;
                }
                $dayIdx = (int) $d->format('j');
                if (isset($daily[$dayIdx])) {
                    $daily[$dayIdx] += (float) $expense->getAmount();
                }
            }
        }
    }

    /**
     * Category totals for current month (family-level).
     *
     * @return array<string,float> [categoryName => total]
     */
    private function computeFamilyCategoryTotals(Family $family, \DateTime $start, \DateTime $end): array
    {
        $totals = [];
        foreach ($family->getUsers() as $u) {
            if (!$u instanceof User) {
                continue;
            }
            foreach ($u->getExpenses() as $e) {
                if (!$e instanceof Expense) {
                    continue;
                }
                $d = $e->getDate();
                if (!$d || $d < $start || $d > $end) {
                    continue;
                }
                $category = $e->getCategory()?->getName() ?? 'Uncategorized';
                $totals[$category] = ($totals[$category] ?? 0.0) + (float) $e->getAmount();
            }
        }
        arsort($totals);
        return $totals;
    }

    /**
     * @param array<string,float> $totals
     *
     * @return array<string,array{amount:float,percent:float}>
     */
    private function toPercentages(array $totals): array
    {
        $sum = array_sum($totals);
        if ($sum <= 0) {
            return [];
        }
        $out = [];
        foreach ($totals as $name => $amount) {
            $out[$name] = [
                'amount' => (float) $amount,
                'percent' => (float) (($amount / $sum) * 100.0),
            ];
        }
        return $out;
    }

    private function formatGrowthPct(float $currentToDate, float $prevToDate): ?string
    {
        if ($prevToDate <= 0.0) {
            return null;
        }
        $pct = ($currentToDate / $prevToDate - 1.0) * 100.0;
        $sign = $pct >= 0 ? '+' : '';
        return $sign . number_format($pct, 1) . '%';
    }
}
