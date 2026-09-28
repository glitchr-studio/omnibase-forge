<?php

namespace Base\Forge\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\DateTimeField;
use Base\Field\EmailField;
use Base\Field\IdField;
use Base\Field\TextField;
use Base\Forge\Entity\LicenseSeat;

/**
 * The seats of the licences: who may use each - an e-mail. Saving one tells
 * its holder by e-mail (LicenseSeatListener); whoever signs in with that
 * address, verified, downloads and makes their key. Deleting it takes the
 * access back at once. Not more seats than the licence has: raise its
 * number first.
 */
class LicenseSeatCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LicenseSeat::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-user-tag';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield AssociationField::new('license')->setColumns(6);
        yield EmailField::new('email')->setColumns(6);
        yield AssociationField::new('user', 'Account')->onlyOnIndex();
        yield TextField::new('keyPrefix', 'Key')->onlyOnIndex();
        yield DateTimeField::new('assignedAt')->onlyOnIndex();
    }
}
