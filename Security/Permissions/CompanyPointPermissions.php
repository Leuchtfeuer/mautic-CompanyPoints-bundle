<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Security\Permissions;

use Mautic\CoreBundle\Security\Permissions\AbstractPermissions;
use Symfony\Component\Form\FormBuilderInterface;

class CompanyPointPermissions extends AbstractPermissions
{
    public function __construct($params)
    {
        parent::__construct($params);

        $this->addStandardPermissions(['points', 'triggers', 'groups', 'categories']);
    }

    public function getName(): string
    {
        return 'companypoint';
    }

    public function buildForm(FormBuilderInterface &$builder, array $options, array $data): void
    {
        $this->addStandardFormFields('copmanypoint', 'categories', $builder, $data);
        $this->addStandardFormFields('copmanypoint', 'points', $builder, $data);
        $this->addStandardFormFields('copmanypoint', 'triggers', $builder, $data);
        $this->addStandardFormFields('copmanypoint', 'groups', $builder, $data);
    }
}
