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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contactName', TextType::class, ['label' => 'quote.form.name'])
            ->add('email', EmailType::class, ['label' => 'quote.form.email'])
            ->add('title', TextType::class, ['label' => 'quote.form.title', 'attr' => ['placeholder' => 'quote.form.title_placeholder']])
            ->add('request', TextareaType::class, [
                'label' => 'quote.form.request',
                'help' => 'quote.form.request_help',
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
