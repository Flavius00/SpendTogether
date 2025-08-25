<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

final class ApiContentNegotiation
{
    public function __construct(private readonly SerializerInterface $serializer)
    {
    }

    public function createResponse(Request $request, array $data, int $statusCode = Response::HTTP_OK): Response
    {
        $format = $request->getPreferredFormat('json');
        if (!in_array($format, ['json', 'xml'])) {
            $format = 'json';
        }

        $serializedData = $this->serializer->serialize($data, $format);

        return new Response($serializedData, $statusCode, [
            'Content-Type' => "application/{$format}",
        ]);
    }
}
