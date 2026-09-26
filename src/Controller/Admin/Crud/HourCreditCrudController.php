<?php

namespace Base\Forge\Controller\Admin\Crud;

use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Controller\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\TextField;
use Base\Forge\Entity\HourCredit;

/**
 * The support-hour ledgers, read-only: lines come from paid orders and
 * logged time. A manual correction is an adjustment line from the
 * forge_admin_hours_adjust action (to keep the history honest).
 */
class HourCreditCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return HourCredit::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-hourglass-half';
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield AssociationField::new('user');
        yield IntegerField::new('minutes');
        yield TextField::new('reason')->formatValue(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value);
        yield TextField::new('note');
        yield TextField::new('orderReference');
        yield DateTimeField::new('createdAt');
    }
}
