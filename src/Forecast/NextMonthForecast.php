<?php

namespace App\Forecast;

use App\Entity\User;

class NextMonthForecast
{

    public function getNextMonthForecast(User $user): float
    {
        if ($this->existsThreeMonthsPast($user)){
            $threeMonthsAverage = $this->getPastThreeMonthsAverage($user);
            if ($this->existsLastYearSameMonths($user)){
                $lastYearThreeMonthsAverage = $this->getLastYearSameMonthsAverage($user);
                $nextMonthsPrediction = $this->getLastYearThisMonth($user);
                $nextMonthsPrediction += abs($lastYearThreeMonthsAverage - $threeMonthsAverage);

                return round($nextMonthsPrediction, 2);
            }
            else
                return $threeMonthsAverage;
        }

        return -1.0;
    }

    public function existsThreeMonthsPast(User $user): bool
    {
        $lastMonth = new \DateTime();
        $lastMonth->modify('-1 months')->format('Y-m');
        $expenses = $user->getExpenses()->filter(function ($expense) use ($lastMonth) {
            return $expense->getDate()->format('Y-m') === $lastMonth;
        });

        if ($expenses->isEmpty()) {
            return false;
        }

        $secondLastMonth = new \DateTime();
        $secondLastMonth->modify('-2 months')->format('Y-m');
        $expenses = $expenses->filter(function ($expense) use ($secondLastMonth) {
            return $expense->getDate()->format('Y-m') === $secondLastMonth;
        });

        if ($expenses->isEmpty()) {
            return false;
        }

        $thirdLastMonth = new \DateTime();
        $thirdLastMonth->modify('-3 months')->format('Y-m');
        $expenses = $expenses->filter(function ($expense) use ($thirdLastMonth) {
            return $expense->getDate()->format('Y-m') === $thirdLastMonth;
        });

        if ($expenses->isEmpty()) {
            return false;
        }

        return true;
    }

    public function getPastThreeMonthsAverage(User $user): float
    {
        $lastMonth = new \DateTime();
        $lastMonth->modify('-1 months')->format('Y-m');
        $expenses = $user->getExpenses()->filter(function ($expense) use ($lastMonth) {
            return $expense->getDate()->format('Y-m') === $lastMonth;
        });

        $total = 0.0;
        foreach ($expenses as $expense) {
            $total += $expense->getAmount();
        }

        $secondLastMonth = new \DateTime();
        $secondLastMonth->modify('-2 months')->format('Y-m');
        $expenses = $expenses->filter(function ($expense) use ($secondLastMonth) {
            return $expense->getDate()->format('Y-m') === $secondLastMonth;
        });

        foreach ($expenses as $expense) {
            $total += $expense->getAmount();
        }

        $thirdLastMonth = new \DateTime();
        $thirdLastMonth->modify('-3 months')->format('Y-m');
        $expenses = $expenses->filter(function ($expense) use ($thirdLastMonth) {
            return $expense->getDate()->format('Y-m') === $thirdLastMonth;
        });

        foreach ($expenses as $expense) {
            $total += $expense->getAmount();
        }

        return round($total / 3, 2);
    }

    public function existsLastYearSameMonths(User $user): bool
    {
        $lastYearLastMonth = new \DateTime();
        $lastYearLastMonth->modify('-1 years')->modify('-1 months')->format('Y-m');
        $expenses = $user->getExpenses()->filter(function ($expense) use ($lastYearLastMonth) {
            return $expense->getDate()->format('Y-m') === $lastYearLastMonth;
        });

        if ($expenses->isEmpty()) {
            return false;
        }

        $lastYearSecondLastMonth = new \DateTime();
        $lastYearSecondLastMonth->modify('-1 years')->modify('-2 months')->format('Y-m');
        $expenses = $user->getExpenses()->filter(function ($expense) use ($lastYearSecondLastMonth) {
            return $expense->getDate()->format('Y-m') === $lastYearSecondLastMonth;
        });

        if ($expenses->isEmpty()) {
            return false;
        }

        $lastYearThirdLastMonth = new \DateTime();
        $lastYearThirdLastMonth->modify('-1 years')->modify('-3 months')->format('Y-m');
        $expenses = $user->getExpenses()->filter(function ($expense) use ($lastYearThirdLastMonth) {
            return $expense->getDate()->format('Y-m') === $lastYearThirdLastMonth;
        });

        if ($expenses->isEmpty()) {
            return false;
        }

        return true;
    }

    public function getLastYearSameMonthsAverage(User $user): float
    {
        $lastYearLastMonth = new \DateTime();
        $lastYearLastMonth->modify('-1 years')->modify('-1 months')->format('Y-m');
        $expenses = $user->getExpenses()->filter(function ($expense) use ($lastYearLastMonth) {
            return $expense->getDate()->format('Y-m') === $lastYearLastMonth;
        });

        $total = 0.0;
        foreach ($expenses as $expense) {
            $total += $expense->getAmount();
        }

        $lastYearSecondLastMonth = new \DateTime();
        $lastYearSecondLastMonth->modify('-1 years')->modify('-2 months')->format('Y-m');
        $expenses = $user->getExpenses()->filter(function ($expense) use ($lastYearSecondLastMonth) {
            return $expense->getDate()->format('Y-m') === $lastYearSecondLastMonth;
        });

        foreach ($expenses as $expense) {
            $total += $expense->getAmount();
        }

        $lastYearThirdLastMonth = new \DateTime();
        $lastYearThirdLastMonth->modify('-1 years')->modify('-3 months')->format('Y-m');
        $expenses = $user->getExpenses()->filter(function ($expense) use ($lastYearThirdLastMonth) {
            return $expense->getDate()->format('Y-m') === $lastYearThirdLastMonth;
        });

        foreach ($expenses as $expense) {
            $total += $expense->getAmount();
        }

        return round($total / 3, 2);
    }

    public function getLastYearThisMonth(User $user): float
    {
        $lastYearThisMonth = new \DateTime();
        $lastYearThisMonth->modify('-1 years')->format('Y-m');
        $expenses = $user->getExpenses()->filter(function ($expense) use ($lastYearThisMonth) {
            return $expense->getDate()->format('Y-m') === $lastYearThisMonth;
        });

        $total = 0.0;
        foreach ($expenses as $expense) {
            $total += $expense->getAmount();
        }

        return round($total, 2);
    }
}
