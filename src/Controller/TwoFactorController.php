<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/2fa')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TwoFactorController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/setup', name: 'api_2fa_setup', methods: ['POST'])]
    public function setup(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Generate a new TOTP object with a random secret
        $totp = TOTP::create();
        $totp->setLabel($user->getUserIdentifier());
        $totp->setIssuer('IliadApp');

        return $this->json([
            'secret' => $totp->getSecret(),
            'qrCode' => $totp->getProvisioningUri(),
        ]);
    }

    #[Route('/enable', name: 'api_2fa_enable', methods: ['POST'])]
    public function enable(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $secret = $data['secret'] ?? null;
        $code = $data['code'] ?? null;

        if (!$secret || !$code) {
            return $this->json(['message' => 'Missing secret or code'], 400);
        }

        // Verify the code against the provided secret
        $totp = TOTP::create($secret);
        
        // OTPHP verify method might need strict timing or window
        // By default verify($code) checks current time
        if (!$totp->verify($code)) {
            return $this->json(['message' => 'Invalid code'], 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->setGoogleAuthenticatorSecret($secret);
        
        $this->entityManager->flush();

        return $this->json(['message' => '2FA Enabled successfully']);
    }

    #[Route('/disable', name: 'api_2fa_disable', methods: ['POST'])]
    public function disable(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setGoogleAuthenticatorSecret(null);
        
        $this->entityManager->flush();

        return $this->json(['message' => '2FA Disabled successfully']);
    }
}
