<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\EventListener;

use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\PluginEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class PluginInstallSubscriber implements EventSubscriberInterface
{
    public const FIELD_DATA = [
        'alias' => 'score_calculated',
        'name'  => 'Score Calculated',
        'type'  => 'number',
    ];

    public function __construct(private FieldModel $fieldModel, private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onPluginInstall', 0],
        ];
    }

    public function onPluginInstall(PluginInstallEvent $event): void
    {

        if (!$event->checkContext('Company Points by Leuchtfeuer')) {
            return;
        }

        $this->createField(
            self::FIELD_DATA['alias'],
            self::FIELD_DATA['name'],
            self::FIELD_DATA['type']
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createField(string $alias, string $label, string $type, array $options = []): void
    {
        $properties = [];
        if (!empty($options['properties'])) {
            $properties = $options['properties'];
        }

        $field = new LeadField();
        $field->setAlias($alias);
        $field->setLabel($label);
        $field->setType($type);
        $field->setObject('company');
        $field->setGroup('professional');
        $field->setIsRequired(false);
        $field->setIsFixed(false);
        $field->setIsVisible(true);
        $field->setIsShortVisible(true);
        $field->setIsListable(true);
        $field->setIsPubliclyUpdatable(false);
        $field->setIsUniqueIdentifier(false);

        if (!empty($properties)) {
            $result = $this->fieldModel->setFieldProperties($field, $properties);
        }

        try {
            $this->fieldModel->saveEntity($field);
        } catch (\Exception $e) {
            $this->logger->error('Field could not be saved : '.$e->getMessage());
        }
    }
}
