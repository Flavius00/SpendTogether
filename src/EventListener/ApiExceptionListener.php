<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\ApiContentNegotiation;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener('kernel.exception', method: 'onKernelException', priority: -100)]
final class ApiExceptionListener
{
    public function __construct(
        // private readonly string $kernelEnvironment,
        private readonly ApiContentNegotiation $responseFactory,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api') && !str_starts_with($path, '/admin/api')) {
            return;
        }

        $exception = $event->getThrowable();

        $responseData = [];
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR; // 500

        if ($exception instanceof RateLimitExceededException) {
            $statusCode = Response::HTTP_TOO_MANY_REQUESTS; // 429
            $responseData['error'] = 'Too Many Requests';
        } elseif ($exception instanceof AccessDeniedException) {
            $statusCode = Response::HTTP_FORBIDDEN; // 403
            $responseData['error'] = $exception->getMessage();
        } elseif ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $responseData['error'] = $exception->getMessage();
        } else {
            $responseData['error'] = 'An unexpected server error occurred';
        }

        $response = $this->responseFactory->createResponse($request, $responseData, $statusCode);

        $event->setResponse($response);
    }
}
