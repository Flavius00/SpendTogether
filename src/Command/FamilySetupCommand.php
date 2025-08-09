<?php

namespace App\Command;

use App\Entity\Family;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:family:setup',
    description: 'Sets up a new family and assigns members.',
)]
class FamilySetupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('admin-name', InputArgument::REQUIRED, 'Name of the user creating the family (will become admin).')
            ->addArgument('family-name', InputArgument::REQUIRED, 'The name for the new family.')
            ->addArgument('member-names', InputArgument::IS_ARRAY, 'Names of the members to add to the family.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $adminName = $input->getArgument('admin-name');
        $familyName = $input->getArgument('family-name');
        $memberNames = $input->getArgument('member-names');

        try {
            $potentialAdmins = $this->userRepository->findByName($adminName);
            $availableAdmins = array_filter($potentialAdmins, fn(User $user) => $user->getFamily() === null);

            if (count($availableAdmins) === 0) {
                throw new \RuntimeException("No available admin user found with name '{$adminName}'.");
            }

            if (count($availableAdmins) === 1) {
                $adminUser = array_values($availableAdmins)[0];
                $io->note("Automatically selected the only available user as admin: {$adminUser->getName()} ({$adminUser->getEmail()})");
            } else {
                $choices = [];
                foreach ($availableAdmins as $user) {
                    $choices[$user->getEmail()] = "{$user->getName()} ({$user->getEmail()})";
                }
                $chosenEmail = $io->choice("Multiple users found with name '{$adminName}'. Which one should be the admin?", $choices);
                $adminUser = $this->userRepository->findOneBy(['email' => $chosenEmail]);
            }

            $family = new Family();
            $family->setName($familyName);
            $budget = $io->ask('What is the monthly target budget for the family?', '1000');
            $family->setMonthlyTargetBudget($budget);

            $adminUser->setRoles(['ROLE_ADMIN']);
            $adminUser->setFamily($family);
            $this->entityManager->persist($family);

            $addedMembers = [];

            foreach ($memberNames as $memberName) {
                $potentialMembers = $this->userRepository->findByName($memberName);

                $availableMembers = array_filter($potentialMembers, fn(User $user) => $user->getFamily() === null);

                if (count($availableMembers) === 0) {
                    $io->warning("No available users found with the name '{$memberName}'. Skipping.");
                    continue;
                }

                $userToAdd = null;
                if (count($availableMembers) === 1) {
                    $userToAdd = array_values($availableMembers)[0];
                    $io->note("Automatically added the only available user: {$userToAdd->getName()} ({$userToAdd->getEmail()})");
                } else {
                    $choices = [];
                    foreach ($availableMembers as $user) {
                        $choices[$user->getEmail()] = "{$user->getName()} ({$user->getEmail()})";
                    }

                    $question = "Multiple users found with name '{$memberName}'. Which one do you want to add?";
                    $chosenEmail = $io->choice($question, $choices);

                    $userToAdd = $this->userRepository->findOneBy(['email' => $chosenEmail]);
                }

                if ($userToAdd) {
                    $userToAdd->setRoles(['ROLE_MEMBER']);
                    $userToAdd->setFamily($family);
                    $addedMembers[] = [$userToAdd->getName(), $userToAdd->getEmail()];
                }
            }

            $this->entityManager->flush();

            $io->success("Family '{$familyName}' created successfully by admin {$adminUser->getName()}!");
            $io->section('Family Members:');
            $io->table(
                ['Name', 'Email', 'Role'],
                [
                    [$adminUser->getName(), $adminUser->getEmail(), 'ADMIN'],
                    ...array_map(fn($member) => [$member[0], $member[1], 'MEMBER'], $addedMembers)
                ]
            );

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
