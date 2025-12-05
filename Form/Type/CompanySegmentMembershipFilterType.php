<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\CompanySegmentListType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompanySegmentMembershipFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'operator',
            ChoiceType::class,
            [
                'label'      => false,
                'choices'    => [
                    'mautic.core.operator.in' => 'in',
                    'mautic.core.operator.notin' => 'notIn',
                ],
                'attr'       => [
                    'class'   => 'form-control not-chosen',
                ],
                'required'   => false,
                'placeholder' => false,
            ]
        );

        $builder->add(
            'segments',
            CompanySegmentListType::class,
            [
                'label'      => false,
                'attr'       => [
                    'class'   => 'form-control',
                    'placeholder' => 'mautic.core.form.chooseone',
                ],
                'required'   => false,
                'multiple'   => true,
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['label_text'] = 'mautic.company_segments.filter.lists';
    }

    public function getBlockPrefix(): string
    {
        return 'company_segment_membership_filter';
    }
}