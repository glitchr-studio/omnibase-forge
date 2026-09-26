<?php

namespace Base\Forge\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\BooleanField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\SlugField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Forge\Entity\Software;
use Base\Forge\Enum\Pricing;
use Base\Forge\Enum\SoftwareStatus;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/** The software: what the applications page shows, and what the forge releases. */
class SoftwareCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Software::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-cubes';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name')->setColumns(6);
        yield SlugField::new('slug')->setColumns(6)->hideOnIndex();
        yield TextField::new('tagline')->setColumns(12)->hideOnIndex();
        yield TextField::new('category')->setColumns(3)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOptions(['choices' => array_combine(Software::CATEGORIES, Software::CATEGORIES)]);
        yield TextField::new('status')->setColumns(3)
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => SoftwareStatus::class])
            ->formatValue(fn ($value) => $value instanceof SoftwareStatus ? $value->value : $value);
        yield TextField::new('pricing')->setColumns(3)
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => Pricing::class])
            ->formatValue(fn ($value) => $value instanceof Pricing ? $value->value : $value);
        yield TextField::new('year')->setColumns(3)->hideOnIndex();
        yield TextareaField::new('description')->hideOnIndex();
        yield TextField::new('stackAsText', 'Stack')->setColumns(6)->hideOnIndex()->setHelp('Comma separated: Symfony, TransparentJS, Stripe');
        yield TextField::new('image')->setColumns(6)->hideOnIndex()->setHelp('images/brand/… (public assets) or an absolute URL');
        yield TextField::new('homepage')->setColumns(4)->hideOnIndex();
        yield TextField::new('sourceUrl')->setColumns(4)->hideOnIndex();
        yield TextField::new('demoUrl')->setColumns(4)->hideOnIndex()->setHelp('Opened in a nested panel from the applications page');
        yield TextField::new('repository')->setColumns(4)->setHelp('The git-bundle repository name (releases are built from its tags)');
        yield TextField::new('repositoryUrl')->setColumns(4)->hideOnIndex()->setHelp('Cloned into forge.repositories_dir when not in git.repositories');
        yield TextField::new('packageName')->setColumns(4)->hideOnIndex()->setHelp('vendor/name, to serve it from the Composer repository');
        yield IntegerField::new('position')->setColumns(3);
        yield BooleanField::new('visible')->setColumns(3);
    }
}
