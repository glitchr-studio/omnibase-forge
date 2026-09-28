<?php

namespace Base\Forge\Service;

use Base\Entity\User;
use Base\Forge\Entity\HourCredit;
use Base\Forge\Entity\HourRefund;
use Base\Forge\Enum\CreditReason;
use Base\Forge\Exception\HourRefundException;
use Base\Market\Entity\Order;
use Base\Market\Entity\Order\Transaction;
use Base\Market\Payment\StripeGateway;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Paying hours back. The studio keeps the right to refund hours a client
 * bought - when the work does not sit with their values, or the studio's -
 * with an explanation, always. What is refundable is what the order paid,
 * less what was already refunded; what it suggests is the share of the
 * hours still unused (the ledger is one pool: the order's hours, capped by
 * the balance left).
 *
 * Paid by card (a Stripe Checkout session on the payment), the amount goes
 * back through Stripe - market's StripeGateway, on Omnipay, as the payment; otherwise (bank transfer) it is recorded,
 * to be wired back by hand. Then the hours leave the ledger, the order is
 * marked refunded once fully paid back, and the client gets the
 * explanation by e-mail.
 */
class HourRefunds
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HourLedger $ledger,
        private readonly StripeGateway $stripe,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function order(string $reference): ?Order
    {
        return $this->entityManager->getRepository(Order::class)->findOneBy(['reference' => $reference]);
    }

    /**
     * What this order can still give back.
     *
     * @return array{paid: int, refunded: int, refundable: int, minutes: int, refunded_minutes: int, unused: int, suggested: int, currency: string, stripe: bool}
     */
    public function summary(Order $order): array
    {
        $reference = (string) $order->getReference();
        $paid = null !== $order->getPaidAt() ? (int) $order->getNetPrice() : 0;
        $minutes = 0;
        foreach ($this->entityManager->getRepository(HourCredit::class)->findBy(['orderReference' => $reference, 'reason' => CreditReason::PURCHASE]) as $line) {
            $minutes += $line->getMinutes();
        }
        $refunded = $refundedMinutes = 0;
        foreach ($this->refunds($reference) as $refund) {
            $refunded += $refund->getAmount();
            $refundedMinutes += $refund->getMinutes();
        }

        $left = max(0, $minutes - $refundedMinutes);
        $balance = $order->getCustomer() ? $this->ledger->balance($order->getCustomer()) : 0;
        $unused = max(0, min($left, $balance));
        $refundable = max(0, $paid - $refunded);

        return [
            'paid' => $paid,
            'refunded' => $refunded,
            'refundable' => $refundable,
            'minutes' => $minutes,
            'refunded_minutes' => $refundedMinutes,
            'unused' => $unused,
            'suggested' => $minutes > 0 ? min($refundable, (int) round($paid * $unused / $minutes)) : $refundable,
            'currency' => $order->getCurrency(),
            'stripe' => null !== $this->stripeSession($order),
        ];
    }

    /** @return list<HourRefund> */
    public function refunds(string $reference): array
    {
        return $this->entityManager->getRepository(HourRefund::class)->findBy(['orderReference' => $reference], ['createdAt' => 'ASC']);
    }

    /**
     * Refunds $amount cents and takes $minutes back from the ledger, for
     * $reason (sent to the client). One at a time per order: the order's row
     * is locked while it runs, and Stripe gets an idempotency key.
     */
    public function refund(Order $order, int $amount, int $minutes, string $reason, ?User $by = null): HourRefund
    {
        $reason = trim($reason);
        if ('' === $reason) {
            throw new HourRefundException('Une explication est nécessaire : le client la reçoit.');
        }

        $refund = $this->entityManager->wrapInTransaction(function () use ($order, $amount, $minutes, $reason, $by) {
            $this->entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
            $summary = $this->summary($order);
            if ($summary['paid'] <= 0) {
                throw new HourRefundException(sprintf('La commande %s n\'est pas payée.', $order->getReference()));
            }
            if ($amount <= 0 || $amount > $summary['refundable']) {
                throw new HourRefundException(sprintf('Le montant doit être entre 0,01 et %s.', number_format($summary['refundable'] / 100, 2, ',', ' ')));
            }
            if ($minutes < 0 || $minutes > $summary['minutes'] - $summary['refunded_minutes']) {
                throw new HourRefundException(sprintf('Au plus %d minutes peuvent être reprises sur cette commande.', $summary['minutes'] - $summary['refunded_minutes']));
            }

            $session = $this->stripeSession($order);
            $refund = new HourRefund($order->getCustomer(), (string) $order->getReference(), $amount, $summary['currency'], $minutes, $reason, $session ? HourRefund::STRIPE : HourRefund::TRANSFER);
            $refund->setRefundedBy($by);
            if ($session) {
                $refund->setStripeRefund($this->refundOnStripe($order, $session, $amount, \count($this->refunds((string) $order->getReference()))));
            }
            $this->entityManager->persist($refund);

            if ($minutes > 0 && $order->getCustomer()) {
                $this->ledger->credit($order->getCustomer(), -$minutes, CreditReason::REFUND, mb_substr($reason, 0, 255), (string) $order->getReference());
            }
            if ($summary['refunded'] + $amount >= $summary['paid']) {
                $order->markAsRefunded();
                $this->paidTransaction($order)?->markAsRefunded();
            }
            $this->entityManager->flush();

            return $refund;
        });

        if ($order->getCustomer()?->getEmail()) {
            $this->mailer->send((new TemplatedEmail())
                ->to((string) $order->getCustomer()->getEmail())
                ->subject(sprintf('Remboursement de votre commande %s', $order->getReference()))
                ->htmlTemplate('@Forge/email/hours_refund.html.twig')
                ->context(['refund' => $refund, 'user' => $order->getCustomer()]));
        }

        return $refund;
    }

    /** The Checkout session the order was paid with, when it was by card. */
    private function stripeSession(Order $order): ?string
    {
        $session = $this->paidTransaction($order)?->getDetails()['stripe_session'] ?? null;

        return \is_string($session) && '' !== $session ? $session : null;
    }

    private function paidTransaction(Order $order): ?Transaction
    {
        $last = null;
        foreach ($order->getTransactions() as $transaction) {
            if (!$transaction->isCancelled()) {
                $last = $transaction;
            }
        }

        return $last;
    }

    /** Stripe's refund id (market's StripeGateway, on Omnipay); throws, having changed nothing, when Stripe says no. */
    private function refundOnStripe(Order $order, string $session, int $amount, int $previous): string
    {
        $method = $order->getPaymentMethod();
        if (!$method || '' === (string) ($method->getGatewayParameters()['api_key'] ?? '')) {
            throw new HourRefundException('Aucune clef Stripe : renseignez-la dans Clefs d\'API.');
        }

        try {
            return $this->stripe->refund($method, $session, $amount, $order->getCurrency(), sprintf('forge-refund-%s-%d', $order->getReference(), $previous + 1));
        } catch (\Throwable $e) {
            throw new HourRefundException('Stripe n\'a pas remboursé : '.$e->getMessage(), 0, $e);
        }
    }
}
