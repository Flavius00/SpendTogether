<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExpenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
class Expense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive(message: 'The expense amount must be a positive number.')]
    private ?string $amount = null;

    #[ORM\Column(length: 51)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 51)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\s]+$/',
        message: 'The name can only contain letters, numbers, and spaces.'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\s,.\'?!-]*$/',
        message: 'The description can only contain letters, numbers, spaces, commas, periods, and hyphens.'
    )]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull]
    // #[Assert\Date(message: 'The date must be a valid date.')]
    private ?\DateTime $date = null;

    #[ORM\Column(length: 255, nullable: true)]
    // #[Assert\Image]
    private ?string $receiptImage = null;

    #[ORM\ManyToOne(inversedBy: 'expenses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'expenses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $userObject = null;

    #[ORM\ManyToOne(inversedBy: 'expenses')]
    private ?Subscription $subscription = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getReceiptImage(): ?string
    {
        return $this->receiptImage;
    }

    public function setReceiptImage(?string $receiptImage): static
    {
        $this->receiptImage = $receiptImage;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getUserObject(): ?User
    {
        return $this->userObject;
    }

    public function setUserObject(?User $userObject): static
    {
        $this->userObject = $userObject;

        return $this;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): static
    {
        $this->subscription = $subscription;

        return $this;
    }
}
