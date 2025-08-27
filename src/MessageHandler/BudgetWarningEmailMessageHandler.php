<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\BudgetAlertLog;
use App\Entity\Category;
use App\Entity\Family;
use App\Entity\User;
use App\Message\BudgetWarningEmailMessage;
use App\Repository\BudgetAlertLogRepository;
use App\Repository\CategoryRepository;
use App\Repository\FamilyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;

#[AsMessageHandler]
final class BudgetWarningEmailMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly BodyRendererInterface $bodyRenderer,
        private readonly EntityManagerInterface $em,
        private readonly FamilyRepository $familyRepo,
        private readonly UserRepository $userRepo,
        private readonly CategoryRepository $categoryRepo,
        private readonly BudgetAlertLogRepository $alertRepo,
        private readonly Address $fromAddress = new Address('reports@example.test', 'Budget Alerts')
    ) {
    }

    public function __invoke(BudgetWarningEmailMessage $message): void
    {
        $family = $this->familyRepo->find($message->familyId);
        $user   = $this->userRepo->find($message->userId);
        if (!$family instanceof Family || !$user instanceof User) {
            return;
        }
        $to = $user->getEmail();
        if (!$to) {
            return;
        }

        $category = null;
        if ($message->type === 'category_threshold' && $message->categoryId !== null) {
            $category = $this->categoryRepo->find($message->categoryId);
            if (!$category instanceof Category) {
                return;
            }
        }

        $prettyMonth = \DateTime::createFromFormat('Y-m-d', $message->month . '-01')?->format('F Y') ?? $message->month;
        $subject = $message->type === 'category_threshold'
            ? sprintf(
                'Category threshold exceeded — %s — %s — %s',
                (string) ($family->getName() ?? ('Family #'.(string) $family->getId())),
                $prettyMonth,
                $category?->getName() ?? 'Unknown'
            )
            : sprintf(
                'Budget warning — %s — %s',
                (string) ($family->getName() ?? ('Family #'.(string) $family->getId())),
                $prettyMonth
            );

        $email = (new TemplatedEmail())
            ->from($this->fromAddress)
            ->to($to)
            ->subject($subject)
            ->htmlTemplate('email/budget_warning.html.twig')
            ->context([
                'family'    => $family,
                'month'     => $prettyMonth,
                'projected' => $message->projectedTotal,
                'budget'    => $message->budget,
                'user'      => $user,
                'category'  => $category, // null for family budget
                'type'      => $message->type,
            ]);

        $this->bodyRenderer->render($email);
        $this->mailer->send($email);

        // Log idempotent
        if ($message->type === 'category_threshold' && $category instanceof Category) {
            if (!$this->alertRepo->existsForFamilyMonthAmountAndCategory($family, 'category_threshold', $message->month, $message->projectedTotal, $category)) {
                $log = (new BudgetAlertLog())
                    ->setFamily($family)
                    ->setType('category_threshold')
                    ->setMonth($message->month)
                    ->setProjectedAmount(number_format($message->projectedTotal, 2, '.', ''))
                    ->setBudgetAmount(number_format($message->budget, 2, '.', ''))
                    ->setCategory($category)
                    ->setCreatedAt(new \DateTime());
                $this->em->persist($log);
                try { $this->em->flush(); } catch (\Throwable) {}
            }
        } else {
            if (!$this->alertRepo->existsForFamilyMonthAmount($family, 'family_budget', $message->month, $message->projectedTotal)) {
                $log = (new BudgetAlertLog())
                    ->setFamily($family)
                    ->setType('family_budget')
                    ->setMonth($message->month)
                    ->setProjectedAmount(number_format($message->projectedTotal, 2, '.', ''))
                    ->setBudgetAmount(number_format($message->budget, 2, '.', ''))
                    ->setCreatedAt(new \DateTime());
                $this->em->persist($log);
                try { $this->em->flush(); } catch (\Throwable) {}
            }
        }
    }
}
