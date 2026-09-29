<?php

namespace Base\Forge\Form;

use Base\Forge\Model\QuoteRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** What a visitor fills in to ask for a quote: who, what, in their words. */
class QuoteRequestType extends AbstractType
{
    /**
     * The texts name their domain (@forge.…): base-bundle gives every field the "fields" domain,
     * which the form's own translation_domain does not reach.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contactName', TextType::class, ['label' => '@forge.quote.form.name'])
            ->add('email', EmailType::class, ['label' => '@forge.quote.form.email'])
            ->add('siret', TextType::class, [
                'label' => '@market.company.siret',
                'required' => false,
                'help' => '@market.company.siret_help',
                'attr' => ['inputmode' => 'numeric', 'autocomplete' => 'off', 'data-controller' => 'siret', 'data-action' => 'siret#check', 'data-siret-url-value' => '/api/company/'],
            ])
            ->add('title', TextType::class, ['label' => '@forge.quote.form.title', 'attr' => ['placeholder' => '@forge.quote.form.title_placeholder']])
            ->add('request', TextareaType::class, [
                'label' => '@forge.quote.form.request',
                'help' => '@forge.quote.form.request_help',
                'attr' => ['rows' => 7],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuoteRequest::class,
            'translation_domain' => 'forge',
        ]);
    }
}
