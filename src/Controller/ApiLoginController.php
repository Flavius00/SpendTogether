<?php

namespace App\Controller;

use App\Entity\AccessToken;
use App\Repository\AccessTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')] // Common prefix for routes in this controller
final class ApiLoginController extends AbstractController
{
    /**
     * Endpoint for creating an API token at login.
     * The user sends email and password and receives a token.
     */
    #[Route('/login', name: 'api_login_token', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        // Retrieve data from the JSON request body
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['message' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        // We search for the user by email
        $user = $userRepository->findOneBy(['email' => $email]);

        // We check if the user exists and if the password is correct
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // We generate a new and unique token
        $tokenValue = bin2hex(random_bytes(32));
        $accessToken = new AccessToken();
        $accessToken->setUserObject($user);
        $accessToken->setToken($tokenValue);
        $accessToken->setCreatedAt(new \DateTime());
        $accessToken->setExpiresAt(new \DateTime('+30 days'));


        // We save the new token in the database
        $em->persist($accessToken);
        $em->flush();

        // We return the token to the client
        return $this->json(['token' => $tokenValue], Response::HTTP_OK);
    }

    /**
     * Endpoint for invalidating (deleting) the API token on logout.
     * The user must send the current token in the header to be able to log out.
     */
    #[Route('/logout', name: 'api_logout_token', methods: ['POST'])]
    public function logout(
        Request $request,
        AccessTokenRepository $accessTokenRepository,
        EntityManagerInterface $em
    ): JsonResponse
    {
        // The 'api' firewall protects this endpoint.
        // The authenticator has already verified that the token is valid.
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->json(['message' => 'Authorization token not found'], Response::HTTP_UNAUTHORIZED);
        }

        // We extract the token (without "Bearer")
        $tokenValue = substr($authHeader, 7);

        // We search for the token in the database
        $accessToken = $accessTokenRepository->findOneBy(['token' => $tokenValue]);

        // If we find it, we delete it.
        if ($accessToken) {
            $em->remove($accessToken);
            $em->flush();
        }

        // We return a success message.
        return $this->json(['message' => 'Successfully logged out'], Response::HTTP_OK);
    }
}
