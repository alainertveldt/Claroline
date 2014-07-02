<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Library\Maintenance;

class MaintenanceHandler
{
    public static function enableMaintenance()
    {
        throw new \Exception('gotcha');
        if (!file_exists($file = self::getFlagPath())) {
            touch($file);
        }
    }

    public static function disableMaintenance()
    {
        if (file_exists($file = self::getFlagPath())) {
            @unlink($file);
        }
    }

    private static function getFlagPath()
    {
        return __DIR__ . '/../../../../../../../app/config/.update';
    }
}
