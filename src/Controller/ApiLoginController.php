<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AccessToken;
use App\Repository\AccessTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
#[OA\Tag(name: "Authentication")]
final class ApiLoginController extends AbstractController
{
    #[Route('/login', name: 'api_login_token', methods: ['POST'])]
    #[OA\Post(
        summary: "User login to obtain an API token",
        requestBody: new OA\RequestBody(
            description: "User credentials",
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "email", type: "string", example: "test.user@example.com"),
                    new OA\Property(property: "password", type: "string", example: "SecurePassword123")
                ],
                type: "object"
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login successful, returns auth token",
                content: [
                    new OA\JsonContent(
                    properties: [new OA\Property(property: "token", type: "string")],
                    type: "object"
                    ),
                    new OA\XmlContent(
                        properties: [new OA\Property(property: "token", type: "string")],
                        type: "object",
                        xml: new OA\Xml(name: 'response')
                    ),
                ]
            ),
            new OA\Response(response: 401, description: "Invalid credentials"),
            new OA\Response(response: 429, description: "Too Many Requests")
        ]
    )]
    public function login(
        Request                     $request,
        UserRepository              $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface      $em,
    ): array { // JsonResponse
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return ['data' => ['message' => 'Invalid JSON body'], 'status' => Response::HTTP_BAD_REQUEST];
        }

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return ['data' => ['message' => 'Email and password are required'], 'status' => Response::HTTP_BAD_REQUEST];
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return ['data' => ['message' => 'Invalid credentials'], 'status' => Response::HTTP_UNAUTHORIZED];
        }

        // We generate a new and unique token
        $tokenValue = bin2hex(random_bytes(32));
        $accessToken = new AccessToken();
        $accessToken->setUserObject($user);
        $accessToken->setToken($tokenValue);
        $accessToken->setCreatedAt(new \DateTime());
        $accessToken->setExpiresAt(new \DateTime('+30 days'));

        $em->persist($accessToken);
        $em->flush();

        return ['data' => ['token' => $tokenValue], 'status' => Response::HTTP_OK];
    }

    #[Route('/logout', name: 'api_logout_token', methods: ['POST'])]
    #[OA\Post(
        description: "Invalidates the Bearer token used for the request, effectively logging the user out.",
        summary: "User logout and token invalidation",
        security: [["Bearer" => []]], // Indicates that this endpoint requires authentication
        responses: [
            new OA\Response(
                response: 200,
                description: "Successfully logged out",
                content: [
                    new OA\JsonContent(
                    properties: [new OA\Property(property: "message", type: "string", example: "Successfully logged out")],
                    type: "object"
                    ),
                    new OA\XmlContent(
                        properties: [new OA\Property(property: "message", type: "string", example: "Successfully logged out")],
                        type: "object",
                        xml: new OA\Xml(name: 'response')
                    )
                ]
            ),
            new OA\Response(response: 401, description: "Unauthorized - Invalid or missing token"),
            new OA\Response(response: 429, description: "Too Many Requests"),
        ]
    )]
    public function logout(
        Request                 $request,
        AccessTokenRepository   $accessTokenRepository,
        EntityManagerInterface  $em,
    ): array {
        // The 'api' firewall protects this endpoint.
        // The authenticator has already verified that the token is valid.
        $authHeader = $request->headers->get('Authorization');

        // We extract the token (without "Bearer")
        $tokenValue = substr($authHeader, 7);

        $accessToken = $accessTokenRepository->findOneBy(['token' => $tokenValue]);

        if ($accessToken) {
            $em->remove($accessToken);
            $em->flush();
        }

        return ['data' => ['message' => 'Successfully logged out'], 'status' => Response::HTTP_OK];
    }
}
