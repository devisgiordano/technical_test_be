<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserProviderInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private UserRepository $userRepository,
        private TotpAuthenticatorInterface $totpAuthenticator
    ) {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['message' => 'Missing email or password'], 400);
        }

        if ($this->userRepository->findOneBy(['email' => $email])) {
            return $this->json(['message' => 'User already exists'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json(['message' => 'User created successfully'], 201);
    }

    #[Route('/login', name: 'api_login_custom', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Note: This overrides the firewall's json_login if the route matches.
        // If we kept the firewall on /api/login, we might need to use a different path
        // or ensure this controller takes precedence (it does if route matches).
        
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['message' => 'Missing credentials'], 400);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Invalid credentials'], 401);
        }

        if ($user->isTotpAuthenticationEnabled()) {
            // Generate a temporary JWT indicating 2FA is needed
            // Payload: sub=email, 2fa_pending=true
            $token = $this->jwtManager->createFromPayload($user, ['2fa_pending' => true]);
            return $this->json([
                '2fa_required' => true,
                'temp_token' => $token
            ]);
        }

        // Standard Login
        $token = $this->jwtManager->create($user);
        return $this->json(['token' => $token]);
    }

    #[Route('/2fa/login', name: 'api_2fa_login_verify', methods: ['POST'])]
    public function verifyLogin2fa(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tempToken = $data['temp_token'] ?? null;
        $code = $data['code'] ?? null;

        if (!$tempToken || !$code) {
            return $this->json(['message' => 'Missing token or code'], 400);
        }

        // Verify temp token manually (decode it)
        // In a real app, we should verify signature using JWTEncoder.
        // For simplicity, we rely on the client sending it back.
        // WAIT: Unsafe. We MUST verify the token.
        // Better approach: Require the user to be authenticated with the temp token via Bearer?
        // But our firewall blocks "2fa_pending" tokens? No, firewall verifies signature.
        // If we configure firewall to allow access to THIS endpoint with that token, we are good.
        // But checking the payload "2fa_pending" is crucial.
        
        try {
             $payload = $this->jwtManager->parse($tempToken);
        } catch (\Exception $e) {
             return $this->json(['message' => 'Invalid token'], 401);
        }

        if (!isset($payload['2fa_pending']) || $payload['2fa_pending'] !== true) {
             return $this->json(['message' => 'Invalid token type'], 401);
        }

        $email = $payload['username'] ?? $payload['sub'] ?? null; // Lexik uses username usually
        // Lexik default payload has 'username' key for UserInterface::getUserIdentifier()

        if (!$email) {
             return $this->json(['message' => 'Invalid token payload'], 401);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }

        if (!$this->totpAuthenticator->checkCode($user, $code)) {
            return $this->json(['message' => 'Invalid 2FA code'], 401);
        }

        // Success - Issue full token
        $token = $this->jwtManager->create($user);
        return $this->json(['token' => $token]);
    }
}
