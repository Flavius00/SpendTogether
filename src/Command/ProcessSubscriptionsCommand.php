<?php
namespace App\Command;

use App\Entity\Expense;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:subscriptions:process',
    description: 'Generate expenses for due subscriptions and advance next_due_date'
)]
final class ProcessSubscriptionsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');

        $repo = $this->em->getRepository(Subscription::class);
        $qb = $repo->createQueryBuilder('s')
            ->andWhere('s.isActive = :active')->setParameter('active', true)
            ->andWhere('s.nextDueDate <= :today')->setParameter('today', $today->format('Y-m-d 23:59:59'));

        /** @var Subscription[] $due */
        $due = $qb->getQuery()->getResult();
        $count = 0;

        foreach ($due as $sub) {
            $owner = $sub->getUserObject();
            $category = $sub->getCategory();
            if (!$owner || !$category) {
                continue;
            }

            // Create expenses
            $expense = new Expense();
            $expense
                ->setName($sub->getName())
                ->setAmount($sub->getAmount())
                ->setDescription('Auto generated from subscription')
                ->setDate(\DateTime::createFromInterface($today))
                ->setCategoryId($category)
                ->setUserObject($owner)
                ->setSubscription($sub);

            $this->em->persist($expense);

            // Move next_due_date
            $next = \DateTimeImmutable::createFromMutable($sub->getNextDueDate() ?? new \DateTime());
            switch ($sub->getFrequency()) {
                case 'weekly':
                    $next = $next->modify('+1 week');
                    break;
                case 'yearly':
                    $next = $next->modify('+1 year');
                    break;
                default:
                    $next = $next->modify('+1 month');
                    break;
            }
            $sub->setNextDueDate(\DateTime::createFromImmutable($next));

            $count++;
        }

        $this->em->flush();
        $io->success(sprintf('Processed %d due subscriptions.', $count));

        return Command::SUCCESS;
    }
}
