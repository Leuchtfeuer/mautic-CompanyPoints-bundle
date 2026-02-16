<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Event;

use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\Translation\TranslatorInterface;

class CompanyTriggerBuilderEvent extends Event
{
    private array $events = [];

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Adds an action to the list of available .
     *
     * @param string $key   - a unique identifier; it is recommended that it be namespaced i.e. lead.action
     * @param array  $event - can contain the following keys:
     *                      'label'           => (required) what to display in the list
     *                      'group'           => (required) group for organizing events
     *                      'description'     => (optional) short description of event
     *                      'template'        => (optional) template to use for the action's HTML in the point builder
     *                      i.e AcmeMyBundle:PointAction:theaction.html.twig
     *                      'formType'        => (optional) name of the form type SERVICE for the action
     *                      'formTypeOptions' => (optional) array of options to pass to formType
     *                      'eventName'       => (required) event name that will be dispatched when the action is triggered
     *
     * @throws InvalidArgumentException
     */
    public function addEvent($key, array $event): void
    {
        if (array_key_exists($key, $this->events)) {
            throw new InvalidArgumentException("The key, '$key' is already used by another action. Please use a different key.");
        }

        // check for required keys
        $this->verifyComponent(
            ['group', 'label', 'eventName'],
            $event
        );

        $event['label']       = $this->translator->trans($event['label']);
        $event['group']       = $this->translator->trans($event['group']);
        $event['description'] = (isset($event['description'])) ? $this->translator->trans($event['description']) : '';

        $this->events[$key] = $event;
    }

    /**
     * @return array
     */
    public function getEvents()
    {
        uasort($this->events, fn ($a, $b): int => strnatcasecmp(
            $a['label'], $b['label']));

        return $this->events;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function verifyComponent(array $keys, array $component): void
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $component)) {
                throw new InvalidArgumentException("The key, '$k' is missing.");
            }
        }
    }
}
