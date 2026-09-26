<?php

namespace Base\Forge\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\TextField;
use Base\Forge\Entity\License;
use Base\Forge\Enum\LicenseStatus;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/**
 * Licences: issued when an offer is paid; granted by hand here (a gift, a
 * partner). The key is never shown - its owner generates it from their account.
 */
class LicenseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return License::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-key';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield AssociationField::new('software')->setColumns(4);
        yield AssociationField::new('owner')->setColumns(4);
        yield TextField::new('status')->setColumns(2)
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => LicenseStatus::class])
            ->formatValue(fn ($value) => $value instanceof LicenseStatus ? $value->value : $value);
        yield IntegerField::new('seats')->setColumns(2);
        yield TextField::new('keyPrefix', 'Key')->onlyOnIndex();
        yield DateTimeField::new('expiresAt')->setColumns(4);
        yield DateTimeField::new('updatesUntil')->setColumns(4)->hideOnIndex();
        yield TextField::new('orderReference')->setColumns(4)->hideOnIndex();
        yield DateTimeField::new('createdAt')->onlyOnIndex();
    }

}
