<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type;

use Mautic\CoreBundle\Form\ToBcBccFieldsTrait;
use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Form\Type\EmailListType;
use Mautic\FormBundle\Form\Type\FormFieldTrait;
use Mautic\UserBundle\Form\Type\UserListType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Translation\TranslatorInterface;

class CompanySubmitActionEmailType extends AbstractType
{
    use FormFieldTrait;
    use ToBcBccFieldsTrait;

    public function __construct(
        private TranslatorInterface $translator,
        protected CoreParametersHelper $coreParametersHelper
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $options['data']['subject'] ?? $this->translator->trans(
            'mautic.form.action.sendemail.subject.default'
        );
        $builder->add(
            'subject',
            TextType::class,
            [
                'label'      => 'mautic.form.action.sendemail.subject',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control'],
                'required'   => false,
                'data'       => $data,
            ]
        );
//        dump($options);
        if (!isset($options['data']['message'])) {
//            $fields  = $this->getFormFields($options['attr']['data-formid']);
            $message = 'Error: No message found.';

//            foreach ($fields as $token => $label) {
//                $message .= "<strong>$label</strong>: $token<br />";
//            }
        } else {
            $message = $options['data']['message'];
        }

        $builder->add(
            'message',
            TextareaType::class,
            [
                'label'      => 'mautic.form.action.sendemail.message',
                'label_attr' => ['class' => 'control-label'],
//                'attr'       => ['class' => 'form-control editor editor-basic'],
                'attr'       => [
                    'tooltip'              => 'mautic.email.form.body.help',
                    'class'                => 'form-control editor  editor-builder-tokens builder-html editor-email',
//                    'data-token-callback'  => 'email:getBuilderTokens',
//                    'data-token-activator' => '{',
                    'rows'                 => '15',
                ],
                'required'   => false,
                'data'       => $message,
            ]
        );

        $builder->add(
            'immediately',
            HiddenType::class,
            [
                'data' => false,
            ]
        );
        $builder->add(
            'user_id',
            UserListType::class,
            [
                'label'      => 'mautic.email.form.users',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.core.help.autocomplete',
                ],
                'required' => false,
            ]
        );

//        $default = $options['data']['copy_lead'] ?? false;
//        $builder->add(
//            'copy_lead',
//            YesNoButtonGroupType::class,
//            [
//                'label' => 'mautic.form.action.sendemail.copytolead',
//                'data'  => $default,
//            ]
//        );

//        $default = $options['data']['set_replyto'] ?? true;
//        $builder->add(
//            'set_replyto',
//            YesNoButtonGroupType::class,
//            [
//                'label' => 'mautic.form.action.sendemail.setreplyto',
//                'data'  => $default,
//                'attr'  => [
//                    'tooltip' => 'mautic.form.action.sendemail.setreplyto_tooltip',
//                ],
//            ]
//        );

        $default = $options['data']['email_to_owner'] ?? false;
        $builder->add(
            'email_to_owner',
            YesNoButtonGroupType::class,
            [
                'label' => 'mautic.form.action.sendemail.emailtoowner',
                'data'  => $default,
            ]
        );

        $builder->add(
            'templates',
            EmailListType::class,
            [
                'label'      => 'mautic.lead.email.template',
                'label_attr' => ['class' => 'control-label'],
                'required'   => false,
                'attr'       => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.getLeadEmailContent(this)',
                ],
                'multiple'   => false,
            ]
        );

        $this->addToBcBccFields($builder);
    }

    public function getBlockPrefix()
    {
        return 'form_company_submitaction_sendemail';
    }

//    public function buildView(FormView $view, FormInterface $form, array $options): void
//    {
//        $view->vars['formFields'] = $this->getFormFields($options['attr']['data-formid']);
//    }

}
