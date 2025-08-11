<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

abstract class ApiBaseController extends AbstractController
{
    /**
     * Helper method to check request limit.
     *  Throws a 429 exception if the limit is exceeded.
     */
    protected function checkRateLimit(Request $request, RateLimiterFactory $limiterFactory): void
    {
        // We create a limiter based on the client's IP address
        $limiter = $limiterFactory->create($request->getClientIp());

        // We consume a "token". The ensureAccepted() method automatically throws the exception.
        $limiter->consume(1)->ensureAccepted();
    }
}
