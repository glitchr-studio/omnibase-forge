<?php

namespace Base\Forge\Form;

use Base\Forge\Entity\Quote;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

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
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 30, max: 6000)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quote::class,
            'translation_domain' => 'forge',
        ]);
    }
}
