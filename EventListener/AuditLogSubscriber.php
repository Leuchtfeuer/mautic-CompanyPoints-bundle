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
    private function getArgsFromCompanyPointsToAuditLog(CompanyTriggerEvent $CompanyTriggerEvent, string $action, string $object): array
    {
        $objectId = $CompanyTriggerEvent->getTrigger()->getId() ?? 0;

        return [
            'object'             => $object,
            'action'             => $action,
            'objectId'           => $objectId,
            'object_description' => $CompanyTriggerEvent->getTrigger()->getName(),
            'bundle'             => 'company',
            'details'            => [
                'company_segment_id'   => $objectId,
                'company_segment_name' => $CompanyTriggerEvent->getTrigger()->getName(),
                'object_description'   => $CompanyTriggerEvent->getTrigger()->getName(),
            ],
            'ipAddress' => $this->ipLookupHelper->getIpAddressFromRequest(),
        ];
    }
}
