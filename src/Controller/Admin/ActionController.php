<?php

namespace Base\Forge\Controller\Admin;

use Base\Forge\Entity\Project;
use Base\Forge\Entity\Quote;
use Base\Forge\Entity\Release;
use Base\Forge\Enum\QuoteStatus;
use Base\Forge\Service\ProjectReviews;
use Base\Forge\Service\ReleaseSync;
use Base\Market\Entity\Order;
use Base\Market\Service\Checkout;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The back office's buttons that are not a form: rebuild a release's
 * archive, send a quote, ask a client for their review of a delivered
 * project, and mark an order paid when its bank transfer
 * arrives (the manual gateway leaves it pending; confirming it here is what
 * credits the hours and issues the licences).
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/forge')]
class ActionController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/release/{id}/build', name: 'forge_admin_release_build', requirements: ['id' => '\d+'])]
    public function build(Request $request, int $id, ReleaseSync $sync): RedirectResponse
    {
        $release = $this->entityManager->find(Release::class, $id) ?? throw $this->createNotFoundException();
        try {
            $sync->rebuild($release);
            $this->addFlash('success', sprintf('%s: archive built.', $release));
        } catch (\Throwable $e) {
            $this->addFlash('error', sprintf('%s: %s', $release, $e->getMessage()));
        }

        return $this->back($request);
    }

    #[Route('/quote/{id}/send', name: 'forge_admin_quote_send', requirements: ['id' => '\d+'])]
    public function send(Request $request, int $id, MailerInterface $mailer): RedirectResponse
    {
        $quote = $this->entityManager->find(Quote::class, $id) ?? throw $this->createNotFoundException();
        if ($quote->getTotalMinutes() <= 0) {
            $this->addFlash('error', sprintf('%s has no hours to sell: add its lines first.', $quote->getReference()));

            return $this->back($request);
        }

        $quote->setStatus(QuoteStatus::SENT);
        $quote->setValidUntil($quote->getValidUntil() ?? new \DateTimeImmutable('+30 days'));
        $this->entityManager->flush();

        $mailer->send((new TemplatedEmail())
            ->to($quote->getEmail())
            ->subject(sprintf('Votre devis %s — %s', $quote->getReference(), $quote->getTitle()))
            ->htmlTemplate('@Forge/email/quote_sent.html.twig')
            ->context(['quote' => $quote]));

        $this->addFlash('success', sprintf('%s sent to %s.', $quote->getReference(), $quote->getEmail()));

        return $this->back($request);
    }

    /** Closes the project - delivered - and asks its client for a review, by e-mail. */
    #[Route('/project/{id}/review', name: 'forge_admin_project_review', requirements: ['id' => '\d+'])]
    public function review(Request $request, int $id, ProjectReviews $reviews): RedirectResponse
    {
        $project = $this->entityManager->find(Project::class, $id) ?? throw $this->createNotFoundException();
        try {
            $reviews->request($project);
            $this->addFlash('success', sprintf('« %s » est livré : la demande d\'avis est partie à %s.', $project->getName(), $project->getClient()?->getEmail()));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('warning', $e->getMessage());
        }

        return $this->back($request);
    }

    #[Route('/order/{reference}/paid', name: 'forge_admin_order_paid', requirements: ['reference' => '[A-Za-z0-9\-]+'], methods: ['POST'])]
    public function paid(Request $request, string $reference, Checkout $checkout): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('forge_order_paid', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid token.');
        }

        $order = $this->entityManager->getRepository(Order::class)->findOneBy(['reference' => $reference]) ?? throw $this->createNotFoundException();
        $transaction = null;
        foreach ($order->getTransactions() as $candidate) {
            $transaction = $candidate;
        }
        if (!$transaction) {
            $this->addFlash('error', sprintf('Order %s has no payment to confirm.', $reference));

            return $this->back($request);
        }

        $checkout->confirm($order, $transaction);
        $this->addFlash('success', sprintf('Order %s marked paid.', $reference));

        return $this->back($request);
    }

    private function back(Request $request): RedirectResponse
    {
        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('admin'));
    }
}
