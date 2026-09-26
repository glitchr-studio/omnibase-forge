<?php

namespace Base\Forge\Controller\Admin\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\DateField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\TextField;
use Base\Forge\Entity\Project;
use Base\Forge\Enum\ProjectStatus;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

/** Client projects: who, which repository, how many hours agreed. */
class ProjectCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Project::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-diagram-project';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('title')->setColumns(6);
        yield AssociationField::new('client')->setColumns(6);
        yield TextField::new('excerpt', 'Summary')->setColumns(12)->hideOnIndex();
        yield TextField::new('status')->setColumns(3)
            ->setFormType(EnumType::class)->setFormTypeOptions(['class' => ProjectStatus::class])
            ->formatValue(fn ($value) => $value instanceof ProjectStatus ? $value->value : $value);
        yield IntegerField::new('budgetMinutes', 'Budget (minutes)')->setColumns(3);
        yield DateField::new('dueOn')->setColumns(3);
        yield TextField::new('gitRepository', 'Git repository')->setColumns(4)->setHelp('git-bundle repository name');
        yield TextField::new('repositoryUrl')->setColumns(8)->hideOnIndex()->setHelp('Cloned by git:sync when not in git.repositories');
    }
}
