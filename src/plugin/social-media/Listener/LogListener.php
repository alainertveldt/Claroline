<?php
/**
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * Author: Panagiotis TSAVDARIS
 *
 * Date: 5/13/15
 */

namespace Icap\SocialmediaBundle\Listener;

use Claroline\CoreBundle\Event\Log\LogCreateDelegateViewEvent;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class LogListener.
 */
class LogListener
{
    use ContainerAwareTrait;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function onCreateLogListItem(LogCreateDelegateViewEvent $event)
    {
        $content = $this->container->get('twig')->render(
            '@IcapSocialmedia/log/log_list_item.html.twig',
            ['log' => $event->getLog()]
        );

        $event->setResponseContent($content);
        $event->stopPropagation();
    }

    public function onCreateLogDetails(LogCreateDelegateViewEvent $event)
    {
        $content = $this->container->get('twig')->render(
            '@IcapSocialmedia/log/log_details.html.twig',
            [
                'log' => $event->getLog(),
                'listItemView' => $this->container->get('twig')->render(
                    '@IcapSocialmedia/log/log_list_item.html.twig',
                    ['log' => $event->getLog()]
                ),
            ]
        );

        $event->setResponseContent($content);
        $event->stopPropagation();
    }
}
