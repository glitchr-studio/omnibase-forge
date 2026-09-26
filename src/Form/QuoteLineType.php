<?php

namespace Base\Forge\Form;

use Base\Forge\Entity\QuoteLine;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** One line of a quote in the back office: the work, its hours, the rate per hour (cents). */
class QuoteLineType extends AbstractType
{
    public function __construct(#[Autowire('%forge.hourly_rate%')] private readonly int $hourlyRate = 6000)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['label' => 'quote.line.label'])
            ->add('hours', NumberType::class, ['label' => 'quote.line.hours', 'scale' => 2, 'html5' => true, 'attr' => ['step' => '0.25', 'min' => 0]])
            ->add('hourlyRate', IntegerType::class, ['label' => 'quote.line.rate', 'empty_data' => (string) $this->hourlyRate, 'help' => 'quote.line.rate_help']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuoteLine::class,
            'empty_data' => fn () => new QuoteLine('', 60, $this->hourlyRate),
            'translation_domain' => 'forge',
        ]);
    }
}
