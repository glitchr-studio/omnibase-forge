<?php

namespace Base\Forge\Controller\Admin\Crud;

use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Controller\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\CollectionField;
use Base\Field\DateField;
use Base\Field\DateTimeField;
use Base\Field\EmailField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Forge\Entity\Quote;
use Base\Forge\Enum\QuoteStatus;
use Base\Forge\Form\QuoteLineType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/**
 * Quotes and special offers: requests arrive from the site; price the lines,
 * set a discount and a date, then "Send" mails the client their link.
 */
class QuoteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Quote::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-file-invoice';
    }

    public function configureActions(Actions $actions): Actions
    {
        $send = Action::new('forgeSend', 'Send', 'fa-solid fa-paper-plane')
            ->linkToRoute('forge_admin_quote_send', fn (Quote $quote) => ['id' => $quote->getId()])
            ->displayIf(fn (Quote $quote) => \in_array($quote->getStatus(), [QuoteStatus::REQUESTED, QuoteStatus::DRAFT, QuoteStatus::SENT], true));

        return parent::configureActions($actions)->add(Action::INDEX, $send)->add(Action::DETAIL, $send);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('reference')->setColumns(3)->setDisabled();
        yield TextField::new('title')->setColumns(9);
        yield TextField::new('contactName')->setColumns(4);
        yield EmailField::new('email')->setColumns(4);
        yield AssociationField::new('client')->setColumns(4)->hideOnIndex();
        yield TextField::new('siret', 'SIRET')->setColumns(4)->hideOnIndex();
        yield TextField::new('companyBadge', 'Entreprise (registre)')->setColumns(8)->setDisabled()->hideOnForm();
        yield TextField::new('status')->setColumns(3)
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => QuoteStatus::class])
            ->formatValue(fn ($value) => $value instanceof QuoteStatus ? $value->value : $value);
        yield DateField::new('validUntil')->setColumns(3);
        yield IntegerField::new('discountPercent', 'Discount %')->setColumns(3);
        yield AssociationField::new('project')->setColumns(3)->hideOnIndex();
        yield TextareaField::new('request')->hideOnIndex()->setHelp('What the client asked');
        yield TextareaField::new('message')->hideOnIndex()->setHelp('Shown to the client above the lines');
        yield CollectionField::new('lines')->setEntryType(QuoteLineType::class)->allowAdd()->allowDelete()->hideOnIndex()
            ->setFormTypeOptions(['by_reference' => false]);
        yield IntegerField::new('total', 'Total (cents)')->onlyOnIndex();
        yield DateTimeField::new('createdAt')->onlyOnIndex();
    }

    public function createEntity(string $entityFqcn): object
    {
        $reference = $this->entityManager->getRepository(Quote::class)->nextReference();

        return (new Quote($reference))->setStatus(QuoteStatus::DRAFT);
    }
}
