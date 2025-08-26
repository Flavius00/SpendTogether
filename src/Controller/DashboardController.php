<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Facades\SelectedMonthVsLastMonthFacade;
use App\Facades\TotalPerMonthFacade;
use App\Facades\ProjectedSpendingFacade;
use App\Facades\SubscriptionsVsOneTimeFacade;
use App\Facades\TopExpensesFacade;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/dashboard')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'app_dashboard')]
    public function index(
        Request                            $request,
        #[CurrentUser]
        User                               $user,
        AuthorizationCheckerInterface      $authCheck,
        TotalPerMonthFacade                $monthSvgService,
        SubscriptionsVsOneTimeFacade       $subscriptionsVsOneTimeFacade,
        SelectedMonthVsLastMonthFacade     $selectedMonthVsLastSvg,
        TopExpensesFacade                  $topExpensesFacade,
        ProjectedSpendingFacade            $projectedSpendingFacade,
    ): Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $viewType = $request->query->get('viewType', 'user');
        $selectedMonth = $request->query->get('month', date('Y-m'));
        $typeOfPrediction = $request->query->get('chartComparison', 'selected');

        $pieChartSvg = $monthSvgService->generateSvg($user, $selectedMonth, $viewType);
        $barsSvg = $subscriptionsVsOneTimeFacade->generateSvg( $viewType, $user);
        $normal2LineGraphicSvg = $selectedMonthVsLastSvg->generateSvg($user, $selectedMonth, $viewType);
        $topExpensesSvg = $topExpensesFacade->generateSvg($viewType, $user, $selectedMonth);
        $projectionsSvg = $projectedSpendingFacade->generateSvg($viewType, $user, $selectedMonth, $typeOfPrediction);

        return $this->render('dashboard/index.html.twig', [
            'pieChartSvg' => $pieChartSvg,
            'barsSvg' => $barsSvg,
            'normal2LineGraphicSvg' => $normal2LineGraphicSvg,
            'topExpensesSvg' => $topExpensesSvg,
            'projectionsSvg' => $projectionsSvg,
        ]);
    }
}
