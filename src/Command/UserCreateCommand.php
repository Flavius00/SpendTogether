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
    name: 'app:user:create',
    description: 'Creates a new user from a JSON file.',
)]
class UserCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly UserRepository $userRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('json-file', InputArgument::REQUIRED, 'The path to the JSON file containing user data.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('json-file');

        try {
            // Read and validate JSON file
            if (!file_exists($filePath)) {
                throw new \InvalidArgumentException("File not found at path: {$filePath}");
            }
            $jsonContent = file_get_contents($filePath);
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON format.');
            }

            $requiredKeys = ['email', 'password', 'name'];
            foreach ($requiredKeys as $key) {
                if (!isset($data[$key])) {
                    throw new \InvalidArgumentException("Missing required key in JSON: '{$key}'");
                }
            }

            if ($this->userRepository->findOneBy(['email' => $data['email']])) {
                throw new \RuntimeException("User with email '{$data['email']}' already exists.");
            }

            $user = new User();
            $user->setEmail($data['email']);
            $user->setRoles(['ROLE_USER']);
            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
            $user->setName($data['name']);

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

            $io->success('User created successfully!');
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
