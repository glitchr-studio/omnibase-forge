<?php

namespace Base\Forge\Form;

use Base\Forge\Model\ReviewAnswer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The review form: five stars (radios, drawn as stars), a few words, how to
 * sign. The texts name their domain (@forge.…): base-bundle gives every
 * field the "fields" domain.
 */
class ReviewAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => '@forge.review.rating',
                'expanded' => true,
                'choices' => ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5],
                'choice_translation_domain' => false,
            ])
            ->add('comment', TextareaType::class, ['label' => '@forge.review.comment', 'required' => false, 'attr' => ['rows' => 5, 'placeholder' => '@forge.review.comment_placeholder']])
            ->add('signature', TextType::class, ['label' => '@forge.review.signature', 'required' => false, 'help' => '@forge.review.signature_help']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ReviewAnswer::class]);
    }
}
