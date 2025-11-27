<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type;

use Mautic\CampaignBundle\Form\Type\CampaignListType;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Mautic\CampaignBundle\Form\Validator\Constraints\InfiniteLoop;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ModifyCompanyContactsCampaignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'triggerContacts',
            ChoiceType::class,
            [
                'label'      => 'mautic.companypoints.modifycampaigns.triggercontacts.label',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                ],
                'choices' => [
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.youngest_contact' => CompanyTriggerEvent::COMPANY_YOUNGEST_CONTACT,
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.youngest_known_contact' => CompanyTriggerEvent::COMPANY_YOUNGEST_KNOWN_CONTACT,
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.oldest_contact' => CompanyTriggerEvent::COMPANY_OLDEST_CONTACT,
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.oldest_known_contact' => CompanyTriggerEvent::COMPANY_OLDEST_KNOWN_CONTACT,
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.contact_with_most_recent_activity' => CompanyTriggerEvent::COMPANY_MOST_RECENT_ACTIVITY_CONTACT,
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.known_contact_with_most_recent_activity' => CompanyTriggerEvent::COMPANY_MOST_RECENT_ACTIVITY_KNOWN_CONTACT,
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.all_contacts_with_recent_activity' => CompanyTriggerEvent::COMPANY_ALL_CONTACTS_WITH_RECENT_ACTIVITY,
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.all_known_contacts_with_recent_activity' => CompanyTriggerEvent::COMPANY_ALL_KNOWN_CONTACTS_WITH_RECENT_ACTIVITY,
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.all_contacts' => CompanyTriggerEvent::COMPANY_ALL_CONTACTS,
                    'mautic.companypoints.action.modifycampaigns.triggercontacts.all_known_contacts' => CompanyTriggerEvent::COMPANY_ALL_KNOWN_CONTACTS,
                ],
                'required'    => true,
                'placeholder' => 'mautic.core.form.chooseone',
            ]
        );
        $builder->add('addTo', CampaignListType::class, [
            'label'      => 'mautic.campaign.form.addtocampaigns',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class' => 'form-control',
            ],
            'required'         => false,
            'include_this'     => $options['include_this'],
            'this_translation' => 'mautic.campaign.form.thiscampaign_restart',
            'constraints'      => [new InfiniteLoop()],
        ]);

        $builder->add('removeFrom', CampaignListType::class, [
            'label'      => 'mautic.campaign.form.removefromcampaigns',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class' => 'form-control',
            ],
            'required'     => false,
            'include_this' => $options['include_this'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'include_this' => false,
        ]);
    }
}