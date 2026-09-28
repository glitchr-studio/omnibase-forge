<?php

namespace Base\Forge\Controller\Admin;

use Base\Admin\Context\AdminContext;
use Base\Admin\Menu\MenuBuilder;
use Base\Entity\User;
use Base\Forge\Exception\HourRefundException;
use Base\Forge\Service\HourRefunds;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Refunding hours a client bought (HourRefunds): what the order paid and
 * what is left of its hours, an amount and the hours to take back -
 * suggested from what is unused -, and the explanation the client
 * receives. Nothing is sent without one.
 */
#[IsGranted('ROLE_ADMIN')]
class RefundController extends AbstractController
{
    public function __construct(
        private readonly HourRefunds $refunds,
        private readonly AdminContext $adminContext,
        private readonly MenuBuilder $menuBuilder,
    ) {
    }

    #[Route('/admin/forge/order/{reference}/refund', name: 'forge_admin_order_refund', requirements: ['reference' => '[A-Za-z0-9\-]+'], methods: ['GET', 'POST'])]
    public function refund(Request $request, string $reference): Response
    {
        $order = $this->refunds->order($reference) ?? throw $this->createNotFoundException();
        $summary = $this->refunds->summary($order);
        $values = [
            'amount' => number_format($summary['suggested'] / 100, 2, ',', ''),
            'minutes' => $summary['unused'],
            'reason' => '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forge_order_refund_'.$reference, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid token.');
            }
            $values = [
                'amount' => (string) $request->request->get('amount'),
                'minutes' => (int) $request->request->get('minutes'),
                'reason' => (string) $request->request->get('reason'),
            ];
            $cents = (int) round(100 * (float) str_replace([' ', ','], ['', '.'], $values['amount']));
            $by = $this->getUser();

            try {
                $refund = $this->refunds->refund($order, $cents, $values['minutes'], $values['reason'], $by instanceof User ? $by : null);
                $this->addFlash('success', $refund->isByTransfer()
                    ? sprintf('%s : remboursement enregistré, à faire par virement. Le client est prévenu.', $reference)
                    : sprintf('%s : remboursé sur la carte (%s). Le client est prévenu.', $reference, $refund->getStripeRefund()));

                return $this->redirectToRoute('forge_admin_order_refund', ['reference' => $reference]);
            } catch (HourRefundException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        if ([] === $this->adminContext->getMainMenu()) {
            $this->adminContext->setMainMenu($this->menuBuilder->buildDefault());
        }
        if ([] === $this->adminContext->getUserMenu()) {
            $this->adminContext->setUserMenu($this->menuBuilder->buildUserMenuDefault($this->getUser()));
        }

        return $this->render('@Forge/admin/refund.html.twig', [
            'admin_context' => $this->adminContext,
            'order' => $order,
            'summary' => $summary,
            'history' => $this->refunds->refunds($reference),
            'values' => $values,
        ]);
    }
}
