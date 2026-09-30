<?php

namespace Base\Forge\Controller\Admin\Crud;

use Base\Field\BooleanField;
use Base\Field\NumberField;
use Base\Forge\Entity\Product\HourPack;
use Base\Marketplace\Controller\Admin\Crud\ProductCrudController;

/** Hour packs: a market product (store, price, stock) plus the hours it credits. */
class HourPackCrudController extends ProductCrudController
{
    public static function getEntityFqcn(): string
    {
        return HourPack::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-clock';
    }

    public function configureFields(string $pageName): iterable
    {
        yield from parent::configureFields($pageName);
        yield NumberField::new('hours')->setColumns(3)->setHelp('Credited per unit bought');
        yield BooleanField::new('listed')->setColumns(3)->setHelp('Off for the one-off pack of a quote');
    }
}
