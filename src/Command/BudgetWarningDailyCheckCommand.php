<?php
// src/Command/BudgetWarningDailyCheckCommand.php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Family;
use App\Entity\User;
use App\Repository\FamilyRepository;
use App\Warnings\BudgetWarningService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:budget-warning:daily-check',
    description: 'Checks each family early every morning and enqueues budget warning emails if projection exceeds budget and/or category thresholds are breached'
)]
final class BudgetWarningDailyCheckCommand extends Command
{
    public function __construct(
        private readonly FamilyRepository $familyRepository,
        private readonly BudgetWarningService $budgetWarningService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $families = $this->familyRepository->findAll();
        $processedFamilies = 0;

        foreach ($families as $family) {
            if (!$family instanceof Family) {
                continue;
            }
            $admin = null;
            foreach ($family->getUsers() as $u) {
                if ($u instanceof User && in_array('ROLE_ADMIN', $u->getRoles(), true)) {
                    $admin = $u; break;
                }
            }
            if (!$admin instanceof User) {
                continue;
            }

            $warning = $this->budgetWarningService->computeFamilyBudgetWarning($admin);
            if ($warning && ($warning['exceeds'] ?? false)) {
                $this->budgetWarningService->enqueueBudgetWarningEmails($admin, $warning);
                $io->writeln(sprintf('Enqueued family budget warning for family #%s', (string) $family->getId()));
            }

            $breaches = $this->budgetWarningService->computeCategoryThresholdBreaches($admin);
            if (!empty($breaches)) {
                $this->budgetWarningService->enqueueCategoryThresholdEmails($admin, $breaches);
                $io->writeln(sprintf('Enqueued %d category threshold warning(s) for family #%s', count($breaches), (string) $family->getId()));
            }

            $processedFamilies++;
        }

        $io->success(sprintf('Done. Families processed: %d', $processedFamilies));
        return Command::SUCCESS;
    }
}
