<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper;

use Mautic\CoreBundle\Configurator\Configurator;

class CountQueueHelper
{
    private const DEFAULT_PARAMETERS = [
        'batch'         => 0,
        'total'         => 0,
        'last'          => 0,
        'lastBatch'     => 0,
        'currentOffset' => 0,
    ];

    private const CONFIGURATION_KEY = 'company_points_count_queue';

    private Configurator $configurator;

    public function __construct(Configurator $configurator)
    {
        $this->configurator = $configurator;
    }

    /**
     * @return array<mixed>
     */
    public function get(): array
    {
        return $this->configurator->getParameters()[self::CONFIGURATION_KEY] ?? self::DEFAULT_PARAMETERS;
    }

    /**
     * @param array<mixed> $parameters
     */
    public function set(array $parameters): void
    {
        $localParameters = $this->get();
        $parameters      = array_merge($localParameters, $parameters);

        $this->configurator->mergeParameters([self::CONFIGURATION_KEY => $parameters]);
        $this->configurator->write();
    }

    public function getOffset(): int
    {
        $parameters = $this->get();

        $currentOffset = $parameters['currentOffset'];

        if (!is_numeric($currentOffset)) {
            throw new \RuntimeException('The "currentOffset" must be numeric value.');
        }

        return (int) $currentOffset;
    }

    public function setOffset(int $offset): void
    {
        $this->set(['currentOffset' => $offset]);
    }

    public function resetOffset(): void
    {
        $this->setOffset(0);
    }
}
