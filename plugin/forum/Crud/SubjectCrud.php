<?php

namespace Claroline\ForumBundle\Crud;

use Claroline\AppBundle\Event\Crud\CreateEvent;
use Claroline\AppBundle\Persistence\ObjectManager;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Security\PermissionCheckerTrait;
use Claroline\ForumBundle\Entity\Forum;
use Claroline\MessageBundle\Manager\MessageManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class SubjectCrud
{
    use PermissionCheckerTrait;

    /**
     * ForumSerializer constructor.
     *
     * @param FinderProvider $finder
     */
    public function __construct(
        ObjectManager $om,
        TokenStorageInterface $tokenStorage,
        MessageManager $messageManager,
        AuthorizationCheckerInterface $authorization
    ) {
        $this->om = $om;
        $this->tokenStorage = $tokenStorage;
        $this->messageManager = $messageManager;
        $this->authorization = $authorization;
    }

    /**
     * @param CreateEvent $event
     *
     * @return ResourceNode
     */
    public function preCreate(CreateEvent $event)
    {
        $subject = $event->getObject();
        $forum = $subject->getForum();

        //create user if not here
        $user = $this->om->getRepository('ClarolineForumBundle:Validation\User')->findOneBy([
          'user' => $subject->getCreator(),
          'forum' => $forum,
        ]);

        if (!$user) {
            $user = new User();
            $user->setForum($forum);
            $user->setUser($subject->getCreator());
        }

        $messages = $subject->getMessages();
        $first = $messages && isset($messages[0]) ? $messages[0] : null;

        if (!$this->checkPermission('EDIT', $forum->getResourceNode())) {
            if (Forum::VALIDATE_PRIOR_ALL === $forum->getValidationMode()) {
                $subject->setModerated(Forum::VALIDATE_PRIOR_ALL);
                if ($first) {
                    $first->setModerated(Forum::VALIDATE_PRIOR_ALL);
                }
            }

            if (Forum::VALIDATE_PRIOR_ONCE === $forum->getValidationMode()) {
                $subject->setModerated($user->getAccess() ? Forum::VALIDATE_NONE : Forum::VALIDATE_PRIOR_ONCE);
                if ($first) {
                    $first->setModerated(Forum::VALIDATE_PRIOR_ALL);
                }
            }
        } else {
            $subject->setModerated(Forum::VALIDATE_NONE);
            if ($first) {
                $first->setModerated(Forum::VALIDATE_PRIOR_ALL);
            }
        }

        if ($first) {
            $this->om->persist($first);
        }

        return $subject;
    }
}