<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ApiContentNegotiation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class ApiResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ApiContentNegotiation $responseFactory)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => 'onKernelView',
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    public function onKernelView(ViewEvent $event): void
    {
        $controllerResult = $event->getControllerResult();
        $request = $event->getRequest();

        $isApiRoute = str_starts_with($request->getPathInfo(), '/api');
        $isAdminApiRoute = str_starts_with($request->getPathInfo(), '/admin/api');

        // We are only interested in API routes that returned an array
        if ((!$isApiRoute && !$isAdminApiRoute) || !is_array($controllerResult)) {
            return;
        }

        $data = $controllerResult['data'] ?? $controllerResult;
        $statusCode = $controllerResult['status'] ?? Response::HTTP_OK;

        $response = $this->responseFactory->createResponse($request, $data, $statusCode);

        $event->setResponse($response);
    }
}
