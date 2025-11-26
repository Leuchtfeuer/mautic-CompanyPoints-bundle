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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
            'type',
            ChoiceType::class,
            [
                'label'      => 'mautic.companypoint.trigger.form.type',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                ],
                'choices' => [
                    'mautic.companypoint.trigger.form.type.points' => CompanyTrigger::TYPE_POINTS,
                    'mautic.companypoint.trigger.form.type.member_activity' => CompanyTrigger::TYPE_MEMBER_ACTIVITY,
                ],
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

        $builder->add(
            'memberActivity',
            ChoiceType::class,
            [
                'label'      => 'mautic.companypoint.trigger.form.member_activity',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                ],
                'choices' => [
                    'mautic.companypoint.trigger.form.member_activity.first_contact_ever' => CompanyTrigger::ACTIVITY_FIRST_EVER,
                    'mautic.companypoint.trigger.form.member_activity.first_contact_within_30_days' => CompanyTrigger::ACTIVITY_FIRST_WITHIN_30_DAYS,
                    'mautic.companypoint.trigger.form.member_activity.first_of_new_contact' => CompanyTrigger::ACTIVITY_FIRST_OF_NEW_CONTACT,
                    'mautic.companypoint.trigger.form.member_activity.every_of_a_contact' => CompanyTrigger::ACTIVITY_EVERY_OF_A_CONTACT,
                    'mautic.companypoint.trigger.form.member_activity.every_of_known_contact' => CompanyTrigger::ACTIVITY_EVERY_OF_KNOWN_CONTACT,
                ],
                'required'    => false,
                'placeholder' => 'mautic.core.form.chooseone',
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

        // This listener will empty the appropriate fields based on the selected 'type'
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $data = $event->getData();

                if (empty($data) || empty($data['type'])) {
                    return;
                }

                if (CompanyTrigger::TYPE_MEMBER_ACTIVITY === $data['type']) {
                    // If the type is 'member_activity', reset points and color
                    $data['points'] = 0;
                    $data['color']  = 'a0acb8';
                } elseif (CompanyTrigger::TYPE_POINTS === $data['type']) {
                    // If the type is 'points', clear memberActivity
                    $data['memberActivity'] = null;
                }

                $event->setData($data);
            }
        );
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