<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Service\TotalPerMonthSvgService;
use App\Controller\Service\SubscriptionsVsOneTimeSvgService;
use App\Controller\Service\TopExpensesSvgService;
use App\Controller\Service\ProjectedSpendingSvgService;
use App\Entity\User;
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
        Request $request,
        #[CurrentUser]
        User $user,
        AuthorizationCheckerInterface $authCheck,
        TotalPerMonthSvgService $monthSvgService,
        SubscriptionsVsOneTimeSvgService $compareSvgService,
        TopExpensesSvgService $topExpensesSvgService,
        ProjectedSpendingSvgService $projectedSvgService,
    ): Response
    {
        if (!$authCheck->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_login');
        }

        $viewType = $request->query->get('viewType', 'user');
        $selectedMonth = $request->query->get('month', date('Y-m'));

        $svg1 = $monthSvgService->generateSvg($viewType, $selectedMonth, $user);
        $svg2 = $compareSvgService->generateSvgForLastMonths(12, $viewType, $user);
        $svgTop = $topExpensesSvgService->generateSvg($viewType, $user, $selectedMonth);
        $svgProjected = $projectedSvgService->generateSvg($viewType, $user, $selectedMonth);

        return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
            'svg1' => $svg1,
            'svg2' => $svg2,
            'svgTop' => $svgTop,
            'svgProjected' => $svgProjected,
        ]);
    }
}
