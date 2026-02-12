<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Tests\Support;

use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Integration\LeuchtfeuerCompanyPointsIntegration;

trait ActivePluginTrait
{
    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $nameBundle  = 'LeuchtfeuerCompanyPointsBundle';
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => LeuchtfeuerCompanyPointsIntegration::INTEGRATION_NAME]);
        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle]);
            $integration = new Integration();
            $integration->setName(str_replace('Bundle', '', $nameBundle));
            $integration->setPlugin($plugin);
        }
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);

        $nameBundle2      = 'LeuchtfeuerCompanyTagsBundle';
        $nameIntegration2 = 'LeuchtfeuerCompanyTags';
        $integration2     = $this->em->getRepository(Integration::class)->findOneBy(['name' => $nameIntegration2]);
        if (empty($integration2)) {
            $plugin2      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle2]);
            $integration2 = new Integration();
            $integration2->setName(str_replace('Bundle', '', $nameBundle2));
            $integration2->setPlugin($plugin2);
        }
        $integration2->setIsPublished($isPublished);
        $this->em->persist($integration2);

        $nameBundle3      = 'LeuchtfeuerCompanySegmentsBundle';
        $nameIntegration3 = 'LeuchtfeuerCompanySegments';
        $integration3     = $this->em->getRepository(Integration::class)->findOneBy(['name' => $nameIntegration3]);
        if (empty($integration3)) {
            $plugin3      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle3]);
            $integration3 = new Integration();
            $integration3->setName(str_replace('Bundle', '', $nameBundle3));
            $integration3->setPlugin($plugin3);
        }
        $integration3->setIsPublished($isPublished);
        $this->em->persist($integration3);

        $this->em->flush();
    }
}
