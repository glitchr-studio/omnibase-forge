<?php

namespace Base\Forge\Controller\Client;

use Base\Forge\Service\ComposerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The Composer repository's single document. Anonymous: the free packages.
 * With HTTP Basic credentials (e-mail + licence key, what `composer config
 * http-basic.<host>` stores): the licensed packages their licences cover
 * too. Wrong credentials are a 401, so Composer asks again rather than
 * silently resolving without them.
 */
class ComposerController extends AbstractController
{
    public function __construct(private readonly ComposerRepository $repository)
    {
    }

    #[Route('/composer/packages.json', name: 'forge_composer_packages', methods: ['GET'])]
    public function packages(Request $request): Response
    {
        $email = $request->getUser();
        $key = $request->getPassword();
        $licenses = [];

        if (null !== $email || null !== $key) {
            [$owner, $licenses] = $this->repository->authenticate($email, $key);
            if (!$owner) {
                return new JsonResponse(['error' => 'Invalid e-mail or licence key.'], Response::HTTP_UNAUTHORIZED, [
                    'WWW-Authenticate' => 'Basic realm="Glitchr packages"',
                ]);
            }
        }

        $response = new JsonResponse($this->repository->packages($licenses));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $response;
    }
}
