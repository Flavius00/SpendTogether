<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BudgetAlertLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BudgetAlertLogRepository::class)]
#[ORM\Table(name: 'budget_alert_log')]
class BudgetAlertLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: null)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Family $family = null;

    #[ORM\Column(length: 32)]
    private string $type = 'family_budget'; // family_budget | category_threshold

    #[ORM\Column(length: 7)]
    private string $month; // Y-m

    #[ORM\Column(type: 'decimal', precision: 11, scale: 2)]
    private string $projectedAmount;

    #[ORM\Column(type: 'decimal', precision: 11, scale: 2)]
    private string $budgetAmount;

    #[ORM\ManyToOne(inversedBy: null)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getFamily(): ?Family { return $this->family; }
    public function setFamily(Family $family): self { $this->family = $family; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getMonth(): string { return $this->month; }
    public function setMonth(string $month): self { $this->month = $month; return $this; }

    public function getProjectedAmount(): string { return $this->projectedAmount; }
    public function setProjectedAmount(string $projectedAmount): self { $this->projectedAmount = $projectedAmount; return $this; }

    public function getBudgetAmount(): string { return $this->budgetAmount; }
    public function setBudgetAmount(string $budgetAmount): self { $this->budgetAmount = $budgetAmount; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): self { $this->category = $category; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
