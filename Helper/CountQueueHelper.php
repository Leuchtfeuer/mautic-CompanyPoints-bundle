<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper;

class CountQueueHelper
{
    public const PRE_PHP = '<?php ';

    public const DEFAULT_PARAMETERS = [
        'batch'         => 0,
        'total'         => 0,
        'last'          => 0,
        'lastBatch'     => 0,
        'currentOffset' => 0,
    ];

    private string $path       = __DIR__.'/../Assets/json/count.json.php';

    public function __construct()
    {
        if (!file_exists($this->path)) {
            $this->generate();
        }
    }

    /**
     * @return array<mixed>
     */
    public function get(): array
    {
        $json = file_get_contents($this->path);
        $json = str_replace(self::PRE_PHP, '', $json);

        return json_decode($json, true);
    }

    public function generate(): void
    {
        $json = json_encode(self::DEFAULT_PARAMETERS);
        file_put_contents($this->path, self::PRE_PHP.$json);
    }

    /**
     * @param array<mixed> $parameters
     */
    public function set(array $parameters): void
    {
        $localParameters = $this->get();
        $parameters      = array_merge($localParameters, $parameters);
        $json            = json_encode($parameters);
        file_put_contents($this->path, self::PRE_PHP.$json);
    }

    public function getOffset(): int
    {
        $parameters = $this->get();

        return $parameters['currentOffset'];
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
