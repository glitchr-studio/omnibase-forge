<?php

namespace Base\Forge\Controller\Client;

use Base\Entity\User;
use Base\Forge\Entity\Download;
use Base\Forge\Entity\License;
use Base\Forge\Repository\LicenseRepository;
use Base\Forge\Repository\QuoteRepository;
use Base\Forge\Service\HourLedger;
use Base\Forge\Service\LicenseIssuer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The client's side of the forge: their licences and keys, how to install
 * from the Composer repository, their support hours, their quotes and what
 * they downloaded.
 */
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly LicenseRepository $licenses,
        private readonly QuoteRepository $quotes,
        private readonly HourLedger $ledger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/compte/licences', name: 'forge_account_licenses')]
    public function licenses(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('@Forge/client/account/licenses.html.twig', [
            'licenses' => $this->licenses->findOwnedBy($user),
            'balance' => $this->ledger->balance($user),
            'history' => $this->ledger->history($user, 20),
            'quotes' => $this->quotes->findForClient($user),
            'downloads' => $this->entityManager->getRepository(Download::class)->findBy(['user' => $user], ['downloadedAt' => 'DESC'], 10),
            'key' => null,
        ]);
    }

    /** A new key, shown once on the page that answers; the old one stops working. */
    #[Route('/compte/licences/{id}/cle', name: 'forge_license_rekey', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rekey(Request $request, int $id, LicenseIssuer $issuer): Response
    {
        $license = $this->licenses->find($id);
        /** @var User $user */
        $user = $this->getUser();
        if (!$license instanceof License || $license->getOwner()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('No such licence.');
        }
        if (!$this->isCsrfTokenValid('forge_license_'.$license->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid token.');
        }
        if (!$license->isValid()) {
            $this->addFlash('warning', '@forge.license.not_valid');

            return $this->redirectToRoute('forge_account_licenses');
        }

        $key = $issuer->rekey($license);

        $response = $this->render('@Forge/client/account/licenses.html.twig', [
            'licenses' => $this->licenses->findOwnedBy($user),
            'balance' => $this->ledger->balance($user),
            'history' => $this->ledger->history($user, 20),
            'quotes' => $this->quotes->findForClient($user),
            'downloads' => $this->entityManager->getRepository(Download::class)->findBy(['user' => $user], ['downloadedAt' => 'DESC'], 10),
            'key' => ['license' => $license, 'value' => $key],
        ]);
        // The key is in this page only: never cached, never replayed.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
