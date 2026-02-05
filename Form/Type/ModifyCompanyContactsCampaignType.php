<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type;

use Mautic\CampaignBundle\Form\Type\CampaignListType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ModifyCompanyContactsCampaignType extends AbstractType
{
    public function __construct(private Config $companySegmentsConfig)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            'mautic.companypoints.action.modifycampaigns.triggercontacts.youngest_contact'                        => CompanyTriggerEvent::COMPANY_YOUNGEST_CONTACT,
            'mautic.companypoints.action.modifycampaigns.triggercontacts.youngest_known_contact'                  => CompanyTriggerEvent::COMPANY_YOUNGEST_KNOWN_CONTACT,
            'mautic.companypoints.action.modifycampaigns.triggercontacts.oldest_contact'                          => CompanyTriggerEvent::COMPANY_OLDEST_CONTACT,
            'mautic.companypoints.action.modifycampaigns.triggercontacts.oldest_known_contact'                    => CompanyTriggerEvent::COMPANY_OLDEST_KNOWN_CONTACT,
            'mautic.companypoints.action.modifycampaigns.triggercontacts.contact_with_most_recent_activity'       => CompanyTriggerEvent::COMPANY_MOST_RECENT_ACTIVITY_CONTACT,
            'mautic.companypoints.action.modifycampaigns.triggercontacts.known_contact_with_most_recent_activity' => CompanyTriggerEvent::COMPANY_MOST_RECENT_ACTIVITY_KNOWN_CONTACT,
            'mautic.companypoints.action.modifycampaigns.triggercontacts.all_contacts_with_recent_activity'       => CompanyTriggerEvent::COMPANY_ALL_CONTACTS_WITH_RECENT_ACTIVITY,
            'mautic.companypoints.action.modifycampaigns.triggercontacts.all_known_contacts_with_recent_activity' => CompanyTriggerEvent::COMPANY_ALL_KNOWN_CONTACTS_WITH_RECENT_ACTIVITY,
            'mautic.companypoints.action.modifycampaigns.triggercontacts.all_contacts'                            => CompanyTriggerEvent::COMPANY_ALL_CONTACTS,
            'mautic.companypoints.action.modifycampaigns.triggercontacts.all_known_contacts'                      => CompanyTriggerEvent::COMPANY_ALL_KNOWN_CONTACTS,
        ];

        $isPlaceholderFeatureEnabled = $this->companySegmentsConfig->isPublished()
            && $this->companySegmentsConfig->getCreatePlaceholderContact();

        $existingValue = null;
        if (isset($options['data']) && is_array($options['data']) && isset($options['data']['triggerContacts'])) {
            $existingValue = $options['data']['triggerContacts'];
        }
        $isPlaceholderCurrentlySelected = CompanyTriggerEvent::PLACEHOLDER_CONTACT === $existingValue;

        if ($isPlaceholderFeatureEnabled || $isPlaceholderCurrentlySelected) {
            $choices['mautic.companypoints.action.modifycampaigns.triggercontacts.placeholder_contact'] = CompanyTriggerEvent::PLACEHOLDER_CONTACT;
        }

        $builder->add(
            'triggerContacts',
            ChoiceType::class,
            [
                'label'      => 'mautic.companypoints.modifycampaigns.triggercontacts.label',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                ],
                'choices'     => $choices,
                'required'    => true,
                'placeholder' => 'mautic.core.form.chooseone',
                'constraints' => [
                    new NotBlank(
                        ['message' => 'mautic.core.value.required']
                    ),
                ],
            ]
        );

        $builder->add('addToCampaign', CampaignListType::class, [
            'label'      => 'mautic.companypoints.modifycampaigns.form.addtocampaigns',
            'label_attr' => ['class' => 'control-label mt-32'],
            'attr'       => [
                'class' => 'form-control',
            ],
            'required'         => false,
        ]);

        $builder->add('removeFromCampaign', CampaignListType::class, [
            'label'      => 'mautic.companypoints.modifycampaigns.form.removefromcampaigns',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class' => 'form-control',
            ],
            'required'     => false,
        ]);

        $builder->add('restartOrAddToCampaign', CampaignListType::class, [
            'label'      => 'mautic.companypoints.modifycampaigns.form.addtoorrestartcampaigns',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class' => 'form-control',
            ],
            'required'     => false,
        ]);
    }
}
