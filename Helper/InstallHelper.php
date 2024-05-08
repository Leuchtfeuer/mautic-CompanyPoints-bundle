<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Helper;

use Mautic\CoreBundle\Factory\MauticFactory;

class InstallHelper
{
    //    public static function installFields(MauticFactory $factory, string $tablePrefix, string $alias, string $name, string $type): void
    //    {
    //        $q             = $factory->getEntityManager()->getConnection();
    //        $sql           = 'Select * from '.$tablePrefix.'lead_fields where alias = "'.$alias.'"';
    //        $stmt          = $q->prepare($sql);
    //        $result        = $stmt->executeStatement();
    //        $datetime      = \date('Y-m-d H:i:s');
    //        if (empty($result)) {
    //            $sql = 'INSERT INTO '.$tablePrefix.'lead_fields
    //            (
    //                is_published,
    //                type,
    //                alias,
    //                label,
    //                object,
    //                field_group,
    //                field_order,
    //                properties,
    //                date_added,
    //                date_modified,
    //                is_required,
    //                is_fixed,
    //                is_visible,
    //                is_short_visible,
    //                is_listable,
    //                is_publicly_updatable,
    //                is_unique_identifer,
    //                is_index,
    //                char_length_limit
    //                ) VALUES (
    //                1,
    //                "'.$type.'",
    //                "'.$alias.'",
    //                "'.$name.'",
    //                "company",
    //                "core",
    //                1,
    //                "a:0:{}",
    //                "'.$datetime.'",
    //                "'.$datetime.'",
    //                0,
    //                0,
    //                1,
    //                0,
    //                1,
    //                0,
    //                0,
    //                0,
    //                64
    //            )';
    //            $stmt = $q->prepare($sql);
    //            $stmt->executeQuery();
    //            if ('textarea' == $type) {
    //                $type = 'longtext';
    //            }
    //            if ('number' == $type) {
    //                $type = 'int';
    //            }
    //            $sql  = 'ALTER TABLE '.$tablePrefix.'companies ADD '.$alias.' '.$type;
    //            $stmt = $q->prepare($sql);
    //            $stmt->executeQuery();
    //        }
    //    }

    public static function installField(MauticFactory $factory, string $tablePrefix, string $alias, string $name, string $type): void
    {
        $q             = $factory->getEntityManager()->getConnection();
        $sql           = 'Select * from '.$tablePrefix.'lead_fields where alias = "'.$alias.'"';
        $stmt          = $q->prepare($sql);
        $result        = $stmt->executeStatement();
        $datetime      = \date('Y-m-d H:i:s');
        if (empty($result)) {
            $sql = 'INSERT INTO '.$tablePrefix.'lead_fields 
            (
                is_published, 
                type, 
                alias, 
                label, 
                object, 
                field_group, 
                field_order, 
                properties, 
                date_added, 
                date_modified,
                is_required,
                is_fixed,
                is_visible,
                is_short_visible,
                is_listable,
                is_publicly_updatable,
                is_unique_identifer,
                is_index,
                char_length_limit
                ) VALUES (
                1, 
                "'.$type.'", 
                "'.$alias.'", 
                "'.$name.'", 
                "company", 
                "core", 
                1, 
                "a:0:{}", 
                "'.$datetime.'", 
                "'.$datetime.'",
                0,
                0,
                1,
                0,
                1,
                0,
                0,
                0,
                64
            )';
            $stmt = $q->prepare($sql);
            $stmt->executeQuery();
            if ('textarea' == $type) {
                $type = 'longtext';
            }
            if ('number' == $type) {
                $type = 'int';
            }
            $sql  = 'ALTER TABLE '.$tablePrefix.'companies ADD '.$alias.' '.$type;
            $stmt = $q->prepare($sql);
            $stmt->executeQuery();
        }
    }
}
