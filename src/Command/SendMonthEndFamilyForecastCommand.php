<?php

declare(strict_types=1);

namespace App\Command;

use App\Diagrams\Calculators\ProjectedNextMonthSpendingCalculator;
use App\Entity\Family;
use App\Entity\User;
use App\Repository\FamilyRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;

#[AsCommand(
    name: 'app:send-month-end-family-forecast',
    description: 'Sends on the last day of the month an email to family admins with next month’s expected expenses'
)]
final class SendMonthEndFamilyForecastCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly BodyRendererInterface $bodyRenderer,
        private readonly FamilyRepository $familyRepository,
        private readonly ProjectedNextMonthSpendingCalculator $nextMonthCalculator,
        private readonly Address $fromAddress = new Address('reports@example.test', 'Monthly Forecasts') // inject via DI in prod
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // Optional: limit to a specific family (for testing)
            ->addOption('family-id', null, InputOption::VALUE_REQUIRED, 'Send only for this family id')
            // Do not actually send emails, just simulate
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not send, just simulate')
            // Safety guard: exit if not the last day of the month (in case the scheduler is not strict)
            ->addOption('only-last-day', null, InputOption::VALUE_NONE, 'Exit if today is not last day of the month');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($input->getOption('only-last-day') && !$this->isLastDayOfMonth(new \DateTime())) {
            $io->writeln('<comment>Not the last day of the month, skipping.</comment>');
            return Command::SUCCESS;
        }

        $families = [];
        $familyId = $input->getOption('family-id');
        if ($familyId !== null) {
            $f = $this->familyRepository->find((int) $familyId);
            if (!$f instanceof Family) {
                $io->error(sprintf('Family id %s not found.', (string) $familyId));
                return Command::FAILURE;
            }
            $families = [$f];
        } else {
            $families = $this->familyRepository->findAll();
        }

        $nextMonth = new \DateTime('first day of next month');
        $nextMonthLabel = $nextMonth->format('F Y');

        $sent = 0;
        foreach ($families as $family) {
            if (!$family instanceof Family) {
                continue;
            }

            $admins = $this->collectFamilyAdmins($family);
            if (count($admins) === 0) {
                $io->writeln(sprintf('<comment>No admins for family #%s, skipping.</comment>', (string) $family->getId()));
                continue;
            }

            // Family-level total: use the calculator aggregate to avoid divergence
            $familyResult = $this->nextMonthCalculator->calculateFamilyTotal($family);
            $familySum = (float) $familyResult->projectedTotal;

            // Per-user breakdown for display
            $perUser = [];
            foreach ($family->getUsers() as $u) {
                if (!$u instanceof User) {
                    continue;
                }
                $r = $this->nextMonthCalculator->calculate($u);
                $perUser[] = [
                    'name'  => $this->displayUserName($u),
                    'value' => (float) $r->projectedTotal,
                ];
            }

            // Send one email per admin
            foreach ($admins as $admin) {
                $to = $admin->getEmail();
                if (!$to) {
                    continue;
                }

                $email = (new TemplatedEmail())
                    ->from($this->fromAddress)
                    ->to($to)
                    ->subject(sprintf(
                        'Next month forecast — %s — %s',
                        (string) ($family->getName() ?? ('Family #'.(string) $family->getId())),
                        $nextMonthLabel
                    ))
                    ->htmlTemplate('email/monthly_family_forecast.html.twig')
                    ->context([
                        'family'                 => $family,
                        'next_month_label'       => $nextMonthLabel,
                        'projected_family_total' => $familySum,
                        'per_user'               => $perUser, // array<array{name:string,value:float}>
                    ]);

                // Render the Twig templates (also generates the text alternative)
                $this->bodyRenderer->render($email);

                if ($dryRun) {
                    $io->writeln(sprintf(
                        '[DRY-RUN] Would send month-end forecast to %s (family #%s, total: %s)',
                        $to,
                        (string) $family->getId(),
                        number_format($familySum, 2, '.', ',')
                    ));
                    continue;
                }

                $this->mailer->send($email);
                $sent++;
                $io->writeln(sprintf(
                    'Sent month-end forecast to %s (family #%s)',
                    $to,
                    (string) $family->getId()
                ));
            }
        }

        $io->success(sprintf('Done. Emails sent: %d', $sent));

        return Command::SUCCESS;
    }

    private function isLastDayOfMonth(\DateTime $date): bool
    {
        return $date->format('Y-m-d') === $date->format('Y-m-t');
    }

    /**
     * @return User[]
     */
    private function collectFamilyAdmins(Family $family): array
    {
        $admins = [];
        foreach ($family->getUsers() as $u) {
            if ($u instanceof User && in_array('ROLE_ADMIN', $u->getRoles(), true)) {
                $admins[] = $u;
            }
        }
        return $admins;
    }

    private function displayUserName(User $u): string
    {
        if (method_exists($u, 'getName') && $u->getName()) {
            return (string) $u->getName();
        }
        if (method_exists($u, 'getFullName') && $u->getFullName()) {
            return (string) $u->getFullName();
        }
        if (method_exists($u, 'getUserIdentifier') && $u->getUserIdentifier()) {
            return (string) $u->getUserIdentifier();
        }
        if (method_exists($u, 'getEmail') && $u->getEmail()) {
            return (string) $u->getEmail();
        }
        if (method_exists($u, 'getId')) {
            return 'User #' . (string) $u->getId();
        }
        return 'User';
    }
}
