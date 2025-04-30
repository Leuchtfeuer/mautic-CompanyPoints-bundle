<?php


namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type;

use Mautic\ChannelBundle\Entity\MessageQueue;
use Mautic\CoreBundle\Form\ToBcBccFieldsTrait;
use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Form\Type\EmailListType;
use Mautic\FormBundle\Form\Type\FormFieldTrait;
use Mautic\UserBundle\Form\Type\UserListType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class CompanySubmitActionEmailType extends AbstractType
{
    use FormFieldTrait;
    use ToBcBccFieldsTrait;

    public function __construct(
        private TranslatorInterface    $translator,
        protected CoreParametersHelper $coreParametersHelper,
        private RouterInterface $router
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder->add(
            'user_id',
            UserListType::class,
            [
                'label' => 'mautic.email.form.users',
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.core.help.autocomplete',
                ],
                'required' => false,
            ]
        );

        $default = $options['data']['email_to_owner'] ?? false;
        $builder->add(
            'email_to_owner',
            YesNoButtonGroupType::class,
            [
                'label' => 'mautic.companypoints.sendemail.emailtoowner',
                'data' => $default,
            ]
        );

        $this->addToBcBccFields($builder);

        $builder->add(
            'email',
            EmailListType::class,
            [
                'label' => 'mautic.companypoints.sendemail.email.template',
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.email.choose.emails_descr',
                    'onchange' => 'Mautic.disabledEmailAction(window, this)',
                ],
                'multiple' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        ['message' => 'mautic.email.chooseemail.notblank']
                    ),
                ],
            ]
        );
        $options['update_select'] = 'companypointtriggerevent_properties_email';

        $windowUrl = $this->router->generate(
            'mautic_email_action',
            [
                'objectAction' => 'new',
                'contentOnly'  => 1,
                'updateSelect' => $options['update_select'],
            ]
        );

        $builder->add(
            'newEmailButton',
            ButtonType::class,
            [
                'attr' => [
                    'class'   => 'btn btn-default btn-nospin',
                    'onclick' => 'Mautic.loadNewWindow({
                        "windowUrl": "'.$windowUrl.'"
                    })',
                    'icon' => 'fa fa-plus',
                ],
                'label' => 'mautic.email.send.new.email',
            ]
        );

        // create button edit email
        $windowUrlEdit = $this->router->generate(
            'mautic_email_action',
            [
                'objectAction' => 'edit',
                'objectId'     => 'emailId',
                'contentOnly'  => 1,
                'updateSelect' => $options['update_select'],
            ]
        );

        $builder->add(
            'editEmailButton',
            ButtonType::class,
            [
                'attr' => [
                    'class'    => 'btn btn-default btn-nospin',
                    'onclick'  => 'Mautic.loadNewWindow(Mautic.standardEmailUrl({"windowUrl": "'.$windowUrlEdit.'","origin":"#'.$options['update_select'].'"}))',
                    'disabled' => !isset($options['data']['email']) && !isset($options['attr']['email']),
                    'icon'     => 'fa fa-edit',
                ],
                'label' => 'mautic.email.send.edit.email',
            ]
        );

        // create button preview email
        $windowUrlPreview = $this->router->generate('mautic_email_preview', ['objectId' => 'emailId']);

        $builder->add(
            'previewEmailButton',
            ButtonType::class,
            [
                'attr' => [
                    'class'    => 'btn btn-default btn-nospin',
                    'onclick'  => 'Mautic.loadNewWindow(Mautic.standardEmailUrl({"windowUrl": "'.$windowUrlPreview.'","origin":"#'.$options['update_select'].'"}))',
                    'disabled' => !isset($options['data']['email']) && !isset($options['attr']['email']),
                    'icon'     => 'fa fa-external-link',
                ],
                'label' => 'mautic.email.send.preview.email',
            ]
        );

        if (!empty($options['with_email_types'])) {
            $data = (!isset($options['data']['priority'])) ? 2 : (int) $options['data']['priority'];
            $builder->add(
                'priority',
                ChoiceType::class,
                [
                    'choices' => [
                        'mautic.channel.message.send.priority.normal' => MessageQueue::PRIORITY_NORMAL,
                        'mautic.channel.message.send.priority.high'   => MessageQueue::PRIORITY_HIGH,
                    ],
                    'label'    => 'mautic.channel.message.send.priority',
                    'required' => false,
                    'attr'     => [
                        'class'        => 'form-control',
                        'tooltip'      => 'mautic.channel.message.send.priority.tooltip',
                        'data-show-on' => '{"campaignevent_properties_email_type_1":"checked"}',
                    ],
                    'data'        => $data,
                    'placeholder' => false,
                ]
            );

            $data = (!isset($options['data']['attempts'])) ? 3 : (int) $options['data']['attempts'];
            $builder->add(
                'attempts',
                NumberType::class,
                [
                    'label' => 'mautic.channel.message.send.attempts',
                    'attr'  => [
                        'class'        => 'form-control',
                        'tooltip'      => 'mautic.channel.message.send.attempts.tooltip',
                        'data-show-on' => '{"campaignevent_properties_email_type_1":"checked"}',
                    ],
                    'data'       => $data,
                    'empty_data' => 0,
                    'required'   => false,
                ]
            );

        }


    }

    public function getBlockPrefix(): string
    {
        return 'form_company_submitaction_sendemail';
    }

    /**
     * @throws \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['update_select']);
    }

}
