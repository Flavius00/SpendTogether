<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ApiResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SerializerInterface $serializer)
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

        // We are only interested in API routes that returned an array
        if (!str_starts_with($request->getPathInfo(), '/api') || !is_array($controllerResult)) {
            return;
        }

        // Extract the data and status (if specified)
        $data = $controllerResult['data'] ?? $controllerResult;
        $statusCode = $controllerResult['status'] ?? Response::HTTP_OK;

        // Determine the desired format (from the 'Accept' header or the '_format' parameter)
        // with fallback to 'json'
        $format = $request->getPreferredFormat('json');

        // We only support json and xml
        if (!in_array($format, ['json', 'xml'])) {
            $format = 'json';
        }

        // Serialize the data in the required format
        $serializedData = $this->serializer->serialize($data, $format);

        // Create the new Response with the correct content and header
        $response = new Response($serializedData, $statusCode, [
            'Content-Type' => "application/{$format}",
        ]);

        // Set the response to the event to be sent to the client
        $event->setResponse($response);
    }
}
