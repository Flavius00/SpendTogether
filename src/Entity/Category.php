<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 51)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 51)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9\s]+$/',
        message: 'The category name can only contain letters, numbers, and spaces.'
    )]
    #[Assert\Unique]
    private ?string $name = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?bool $isDeleted = null;

    /**
     * @var Collection<int, Expense>
     */
    #[ORM\OneToMany(targetEntity: Expense::class, mappedBy: 'categoryId', orphanRemoval: true)]
    private Collection $expenses;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'category')]
    private Collection $subscriptions;

    /**
     * @var Collection<int, Threshold>
     */
    #[ORM\OneToMany(targetEntity: Threshold::class, mappedBy: 'category')]
    private Collection $thresholds;

    public function __construct()
    {
        $this->expenses = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->thresholds = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function isDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    /**
     * @return Collection<int, Expense>
     */
    public function getExpenses(): Collection
    {
        return $this->expenses;
    }

    public function addExpense(Expense $expense): static
    {
        if (!$this->expenses->contains($expense)) {
            $this->expenses->add($expense);
            $expense->setCategoryId($this);
        }

        return $this;
    }

    public function removeExpense(Expense $expense): static
    {
        if ($this->expenses->removeElement($expense)) {
            // set the owning side to null (unless already changed)
            if ($expense->getCategoryId() === $this) {
                $expense->setCategoryId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setCategory($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // set the owning side to null (unless already changed)
            if ($subscription->getCategory() === $this) {
                $subscription->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Threshold>
     */
    public function getThresholds(): Collection
    {
        return $this->thresholds;
    }

    public function addThreshold(Threshold $threshold): static
    {
        if (!$this->thresholds->contains($threshold)) {
            $this->thresholds->add($threshold);
            $threshold->setCategory($this);
        }

        return $this;
    }

    public function removeThreshold(Threshold $threshold): static
    {
        if ($this->thresholds->removeElement($threshold)) {
            // set the owning side to null (unless already changed)
            if ($threshold->getCategory() === $this) {
                $threshold->setCategory(null);
            }
        }

        return $this;
    }
}
