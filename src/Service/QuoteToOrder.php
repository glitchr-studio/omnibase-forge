<?php

namespace Base\Forge\Service;

use Base\Entity\User;
use Base\Forge\Entity\Product\HourPack;
use Base\Forge\Entity\Quote;
use Base\Market\Entity\Order;
use Base\Market\Entity\Store;
use Base\Market\Service\Cart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The client accepts a quote: it becomes a one-off HourPack of the support
 * store - its hours, its discounted total, one in stock - dropped in their
 * cart. From there it is an order like any other; OrderPaidSubscriber
 * credits the hours and marks the quote paid once it is.
 */
class QuoteToOrder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Cart $cart,
        #[Autowire('%forge.support_store%')] private readonly string $storeSlug = 'support',
    ) {
    }

    public function accept(Quote $quote, User $client): Order
    {
        // Sent and still valid - or accepted already and not paid yet: back to
        // the cart it goes.
        if (!$quote->isAcceptable() && !QuoteStatusGuard::isAwaitingPayment($quote)) {
            throw new \DomainException('quote.error.not_acceptable');
        }

        $store = $this->entityManager->getRepository(Store::class)->findOneBy(['slug' => $this->storeSlug])
            ?? throw new \LogicException(sprintf('The support store "%s" does not exist: create it in the back office.', $this->storeSlug));

        $pack = $quote->getProduct();
        if (!$pack) {
            $pack = new HourPack(null, $store, $quote->getTotal(), $quote->getCurrency());
            $pack->setTitle(sprintf('%s — %s', $quote->getReference(), $quote->getTitle()));
            $pack->setSlug(strtolower($quote->getReference()).'-'.substr($quote->getToken(), 0, 6));
            $pack->setExcerpt($quote->getTitle());
            $pack->setMinutes($quote->getTotalMinutes());
            $pack->setListed(false);
            $pack->setStock(1);
            $this->entityManager->persist($pack);

            $quote->setProduct($pack);
            $quote->setClient($quote->getClient() ?? $client);
            $quote->accept();
            $this->entityManager->flush();
        }

        return $this->cart->add($pack, 1);
    }
}
