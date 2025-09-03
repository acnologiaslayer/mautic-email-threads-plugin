<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Url;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emailthreads_enabled', CheckboxType::class, [
                'label' => 'mautic.emailthreads.config.enabled',
                'required' => false,
                'attr' => [
                    'tooltip' => 'mautic.emailthreads.config.enabled.tooltip',
                ],
            ])
            ->add('emailthreads_domain', TextType::class, [
                'label' => 'mautic.emailthreads.config.domain',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 255]),
                ],
                'attr' => [
                    'tooltip' => 'mautic.emailthreads.config.domain.tooltip',
                    'placeholder' => 'https://your-domain.com',
                ],
            ])
            ->add('emailthreads_auto_thread', CheckboxType::class, [
                'label' => 'mautic.emailthreads.config.auto_thread',
                'required' => false,
                'attr' => [
                    'tooltip' => 'mautic.emailthreads.config.auto_thread.tooltip',
                ],
            ])
            ->add('emailthreads_thread_lifetime', IntegerType::class, [
                'label' => 'mautic.emailthreads.config.thread_lifetime',
                'required' => false,
                'constraints' => [
                    new GreaterThan(['value' => 0]),
                ],
                'attr' => [
                    'tooltip' => 'mautic.emailthreads.config.thread_lifetime.tooltip',
                    'class' => 'form-control',
                ],
            ])
            ->add('emailthreads_include_unsubscribe', CheckboxType::class, [
                'label' => 'mautic.emailthreads.config.include_unsubscribe',
                'required' => false,
                'attr' => [
                    'tooltip' => 'mautic.emailthreads.config.include_unsubscribe.tooltip',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'mautic.core.form.save',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'emailthreads_config',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'emailthreads_config';
    }
}
