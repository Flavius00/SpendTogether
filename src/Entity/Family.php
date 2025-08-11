<?php

namespace App\Entity;

use App\Repository\FamilyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FamilyRepository::class)]
class Family
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 75)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $monthlyTargetBudget = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'family')]
    private Collection $users;

    /**
     * @var Collection<int, Thresholds>
     */
    #[ORM\OneToMany(targetEntity: Thresholds::class, mappedBy: 'family')]
    private Collection $thresholds;

    public function __construct()
    {
        $this->users = new ArrayCollection();
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

    public function getMonthlyTargetBudget(): ?string
    {
        return $this->monthlyTargetBudget;
    }

    public function setMonthlyTargetBudget(string $monthlyTargetBudget): static
    {
        $this->monthlyTargetBudget = $monthlyTargetBudget;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setFamily($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getFamily() === $this) {
                $user->setFamily(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Thresholds>
     */
    public function getThresholds(): Collection
    {
        return $this->thresholds;
    }

    public function addThreshold(Thresholds $threshold): static
    {
        if (!$this->thresholds->contains($threshold)) {
            $this->thresholds->add($threshold);
            $threshold->setFamily($this);
        }

        return $this;
    }

    public function removeThreshold(Thresholds $threshold): static
    {
        if ($this->thresholds->removeElement($threshold)) {
            // set the owning side to null (unless already changed)
            if ($threshold->getFamily() === $this) {
                $threshold->setFamily(null);
            }
        }

        return $this;
    }
}
