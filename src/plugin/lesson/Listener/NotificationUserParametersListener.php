<?php
/**
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * Author: Panagiotis TSAVDARIS
 *
 * Date: 4/13/15
 */

namespace Icap\LessonBundle\Listener;

use Claroline\CoreBundle\Event\Notification\NotificationUserParametersEvent;

/**
 * Class NotificationUserParametersListener.
 */
class NotificationUserParametersListener
{
    /**
     * @param NotificationUserParametersEvent $event
     */
    public function onGetTypesForParameters(NotificationUserParametersEvent $event)
    {
        $event->addTypes('icap_lesson');
    }
}
