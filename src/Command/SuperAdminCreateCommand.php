<?php

namespace App\Command;

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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

#[AsCommand(
    name: 'app:super-admin:create',
    description: 'Creates a new SUPER_ADMIN user.',
)]
class SuperAdminCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The email of the super admin.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the super admin.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $name = $input->getArgument('name');

        try {
            if ($this->userRepository->findOneBy(['email' => $email])) {
                throw new \RuntimeException("User with email '{$email}' already exists.");
            }

            $password = $io->askHidden('Please enter the password for the new super admin:');
            if (empty($password)) {
                throw new \RuntimeException('Password cannot be empty.');
            }

            $user = new User();
            $user->setEmail($email);
            $user->setName($name);

            $user->setRoles(['ROLE_SUPER_ADMIN']);

            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                throw new \RuntimeException("Validation failed: \n" . implode("\n", $errorMessages));
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success('SUPER_ADMIN user created successfully!');
            $io->table(
                ['ID', 'Name', 'Email', 'Roles'],
                [
                    [$user->getId(), $user->getName(), $user->getEmail(), implode(', ', $user->getRoles())]
                ]
            );

            return Command::SUCCESS;

        } catch (Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
