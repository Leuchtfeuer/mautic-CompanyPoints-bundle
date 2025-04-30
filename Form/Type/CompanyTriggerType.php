<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type;

use Mautic\CategoryBundle\Form\Type\CategoryListType;
use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Mautic\CoreBundle\Form\Type\FormButtonsType;
use Mautic\CoreBundle\Form\Type\PublishDownDateType;
use Mautic\CoreBundle\Form\Type\PublishUpDateType;
use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
// use Mautic\PointBundle\Entity\Trigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
// use Mautic\PointBundle\Form\Type\GroupListType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CompanyTrigger>
 */
class CompanyTriggerType extends AbstractType
{
    public function __construct(
        private CorePermissions $security
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new CleanFormSubscriber(['description' => 'html']));
        $builder->addEventSubscriber(new FormExitSubscriber('point', $options));

        $builder->add(
            'name',
            TextType::class,
            [
                'label'      => 'mautic.core.name',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control'],
            ]
        );

        $builder->add(
            'description',
            TextareaType::class,
            [
                'label'      => 'mautic.core.description',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control editor'],
                'required'   => false,
            ]
        );

        $builder->add(
            'category',
            CategoryListType::class,
            [
                'bundle' => 'point',
            ]
        );

        $builder->add(
            'points',
            NumberType::class,
            [
                'label'      => 'mautic.companypoint.trigger.form.points',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.companypoint.trigger.form.points_descr',
                ],
                'required' => false,
            ]
        );

        $color = $options['data']->getColor();

        $builder->add(
            'color',
            TextType::class,
            [
                'label'      => 'mautic.companypoint.trigger.form.color',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'data-toggle' => 'color',
                    'tooltip'     => 'mautic.companypoint.trigger.form.color_descr',
                ],
                'required'   => false,
                'data'       => (!empty($color)) ? $color : 'a0acb8',
                'empty_data' => 'a0acb8',
            ]
        );

        if (!empty($options['data']) && $options['data']->getId()) {
            $readonly = !$this->security->isGranted('companypoint:triggers:publish');
            $data     = $options['data']->isPublished(false);
        } elseif (!$this->security->isGranted('companypoint:triggers:publish')) {
            $readonly = true;
            $data     = false;
        } else {
            $readonly = false;
            $data     = false;
        }

        $builder->add(
            'isPublished',
            YesNoButtonGroupType::class,
            [
                'data'      => $data,
                'attr'      => [
                    'readonly' => $readonly,
                ],
            ]
        );

        $builder->add('publishUp', PublishUpDateType::class);
        $builder->add('publishDown', PublishDownDateType::class);

        $builder->add(
            'sessionId',
            HiddenType::class,
            [
                'mapped' => false,
            ]
        );

        $builder->add('buttons', FormButtonsType::class);

        if (!empty($options['action'])) {
            $builder->setAction($options['action']);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => CompanyTrigger::class,
            ]
        );
    }

    public function getBlockPrefix()
    {
        return 'companypointtrigger';
    }
}
