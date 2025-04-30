<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model;

use Mautic\CoreBundle\Model\FormModel as CommonFormModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEvent;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTriggerEventRepository;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Form\Type\CompanyTriggerEventType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class CompanyTriggerEventModel extends CommonFormModel
{
    /**
     * @return CompanyTriggerEventRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository(CompanyTriggerEvent::class);
    }

    public function getPermissionBase(): string
    {
        return 'companypoint:triggers';
    }

    public function getEntity($id = null): ?CompanyTriggerEvent
    {
        if (null === $id) {
            return new CompanyTriggerEvent();
        }

        return parent::getEntity($id);
    }

    /**
     * @throws MethodNotAllowedHttpException
     */
    public function createForm($entity, FormFactoryInterface $formFactory, $action = null, $options = []): \Symfony\Component\Form\FormInterface
    {
        if (!$entity instanceof CompanyTriggerEvent) {
            throw new MethodNotAllowedHttpException(['CompanyTriggerEvent']);
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create(CompanyTriggerEventType::class, $entity, $options);
    }

    /**
     * Get segments which are dependent on given segment.
     *
     * @param int $segmentId
     */
    public function getReportIdsWithDependenciesOnSegment($segmentId): array
    {
        $filter = [
            'force'  => [
                ['column' => 'e.type', 'expr' => 'eq', 'value'=>'lead.changelists'],
            ],
        ];
        $entities = $this->getEntities(
            [
                'filter'     => $filter,
            ]
        );
        $dependents = [];
        foreach ($entities as $entity) {
            $retrFilters = $entity->getProperties();
            foreach ($retrFilters as $eachFilter) {
                if (in_array($segmentId, $eachFilter)) {
                    $dependents[] = $entity->getTrigger()->getId();
                }
            }
        }

        return $dependents;
    }

    /**
     * @return array<int, int>
     */
    public function getPointTriggerIdsWithDependenciesOnEmail(int $emailId): array
    {
        $filter = [
            'force'  => [
                ['column' => 'e.type', 'expr' => 'in', 'value' => ['email.send', 'email.send_to_user']],
            ],
        ];
        $entities = $this->getEntities(
            [
                'filter'     => $filter,
            ]
        );
        $triggerIds = [];
        foreach ($entities as $entity) {
            $properties = $entity->getProperties();
            if (isset($properties['email']) && (int) $properties['email'] === $emailId) {
                $triggerIds[] = $entity->getTrigger()->getId();
            }
            if (isset($properties['useremail']['email']) && (int) $properties['useremail']['email'] === $emailId) {
                $triggerIds[] = $entity->getTrigger()->getId();
            }
        }

        return array_unique($triggerIds);
    }
}
