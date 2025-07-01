<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\LeuchtfeuerCompanyPointsEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuditLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLogModel $auditLogModel,
        private IpLookupHelper $ipLookupHelper,
    ) {
        // Constructor logic if needed
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_POST_SAVE => [
                ['onSaveUpdateCompanyPointsAuditLog', 0],
            ],
            LeuchtfeuerCompanyPointsEvents::COMPANY_TRIGGER_POST_DELETE => [
                ['onDeleteCompanyPointsAuditLog', 0],
            ],
        ];
    }

    public function onSaveUpdateCompanyPointsAuditLog(CompanyTriggerEvent $companyTriggerEvent): void
    {
        $isNew  = $companyTriggerEvent->isNew();
        $action = $isNew ? 'added' : 'updated';
        $args   = $this->getArgsFromCompanyPointsToAuditLog($companyTriggerEvent, $action, 'company_point_trigger');
        $this->auditLogModel->writeToLog($args);
    }

    public function onDeleteCompanyPointsAuditLog(CompanyTriggerEvent $companyTriggerEvent): void
    {
        $args = $this->getArgsFromCompanyPointsToAuditLog($companyTriggerEvent, 'deleted', 'company_point_trigger');
        $this->auditLogModel->writeToLog($args);
    }

    /**
     * Prepare the arguments for the audit log entry.
     */
    private function getArgsFromCompanyPointsToAuditLog(CompanyTriggerEvent $companyTriggerEvent, string $action, string $object): array
    {
        $objectId = $companyTriggerEvent->getTrigger()->getId() ?? 0;
        $details  = $companyTriggerEvent->getChanges();

        $details['object_description']         = $companyTriggerEvent->getTrigger()->getName();
        $details['company_point_trigger_name'] = $companyTriggerEvent->getTrigger()->getName();
        $details['company_point_trigger_id']   = $objectId;

        return [
            'object'             => $object,
            'action'             => $action,
            'objectId'           => $objectId,
            'object_description' => $companyTriggerEvent->getTrigger()->getName(),
            'bundle'             => 'company',
            'details'            => $details,
            'ipAddress'          => $this->ipLookupHelper->getIpAddressFromRequest(),
        ];
    }
}
