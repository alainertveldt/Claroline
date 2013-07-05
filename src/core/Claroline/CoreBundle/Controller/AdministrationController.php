<?php

namespace Claroline\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Group;
use Claroline\CoreBundle\Form\Factory\FormFactory;
use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;
use Claroline\CoreBundle\Library\Event\PluginOptionsEvent;
use Claroline\CoreBundle\Library\Event\LogUserDeleteEvent;
use Claroline\CoreBundle\Library\Event\LogGroupCreateEvent;
use Claroline\CoreBundle\Library\Event\LogGroupAddUserEvent;
use Claroline\CoreBundle\Library\Event\LogGroupRemoveUserEvent;
use Claroline\CoreBundle\Library\Event\LogGroupDeleteEvent;
use Claroline\CoreBundle\Library\Event\LogGroupUpdateEvent;
use Claroline\CoreBundle\Library\Configuration\UnwritableException;
use Claroline\CoreBundle\Manager\GroupManager;
use Claroline\CoreBundle\Manager\RoleManager;
use Claroline\CoreBundle\Manager\UserManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Symfony\Component\Form\FormError;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * Controller of the platform administration section (users, groups,
 * workspaces, platform settings, etc.).
 */
class AdministrationController extends Controller
{
    const USER_PER_PAGE = 40;
    const GROUP_PER_PAGE = 40;

    private $userManager;
    private $roleManager;
    private $groupManager;
    private $security;
    private $pagerFactory;
    private $eventDispatcher;
    private $configHandler;
    private $formFactory;

    /**
     * @DI\InjectParams({
     *     "userManager"    = @DI\Inject("claroline.manager.user_manager"),
     *     "roleManager"    = @DI\Inject("claroline.manager.role_manager"),
     *     "groupManager"    = @DI\Inject("claroline.manager.group_manager"),
     *     "security"       = @DI\Inject("security.context"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "eventDispatcher"    = @DI\Inject("event_dispatcher"),
     *     "configHandler"    = @DI\Inject("claroline.config.platform_config_handler"),
     *     "formFactory" = @DI\Inject("claroline.form.factory")
     * })
     */
    public function __construct(
        UserManager $userManager,
        RoleManager $roleManager,
        GroupManager $groupManager,
        SecurityContextInterface $security,
        PagerFactory $pagerFactory,
        EventDispatcher $eventDispatcher,
        PlatformConfigurationHandler $configHandler,
        FormFactory $formFactory
    )
    {
        $this->userManager = $userManager;
        $this->roleManager = $roleManager;
        $this->groupManager = $groupManager;
        $this->security = $security;
        $this->pagerFactory = $pagerFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->configHandler = $configHandler;
        $this->formFactory = $formFactory;
    }

    /**
     * @EXT\Template("ClarolineCoreBundle:Administration:index.html.twig")
     *
     * Displays the administration section index.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return array();
    }

    /**
     * @EXT\Route(
     *     "/user/form",
     *     name="claro_admin_user_creation_form"
     * )
     * @EXT\Method("GET")
     * @EXT\ParamConverter("currentUser", options={"authenticatedUser" = true})
     *
     * @EXT\Template()
     *
     * Displays the user creation form.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function userCreationFormAction(User $currentUser)
    {
        $roles = $this->roleManager->getPlatformRoles($currentUser);
        $form = $this->formFactory->create(FormFactory::TYPE_USER, array($roles));

        return array('form_complete_user' => $form->createView());
    }

    /**
     * @EXT\Route(
     *     "/user",
     *     name="claro_admin_create_user"
     * )
     * @EXT\Method("POST")
     * @EXT\ParamConverter("currentUser", options={"authenticatedUser" = true})
     *
     * @EXT\Template("ClarolineCoreBundle:Administration:userCreationForm.html.twig")
     *
     * Creates an user (and its personal workspace) and redirects to the user list.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createUserAction(User $currentUser)
    {
        $request = $this->get('request');
        $roles = $this->roleManager->getPlatformRoles($currentUser);
        $form = $this->formFactory->create(FormFactory::TYPE_USER, array($roles), new User());
        $form->handleRequest($request);

        if ($form->isValid()) {
            $user = $form->getData();
            $newRoles = $form->get('platformRoles')->getData();
            $this->userManager->insertUserWithRoles($user, $newRoles);

            return $this->redirect($this->generateUrl('claro_admin_user_list'));
        }

        return array('form_complete_user' => $form->createView());
    }

    /**
     * @EXT\Route(
     *     "/users",
     *     name="claro_admin_multidelete_user",
     *     options = {"expose"=true}
     * )
     * @EXT\Method("DELETE")
     * @EXT\ParamConverter(
     *     "users",
     *      class="ClarolineCoreBundle:User",
     *      options={"multipleIds" = true}
     * )
     *
     * Removes many users from the platform.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteUsersAction(array $users)
    {
        foreach ($users as $user) {
            $this->userManager->deleteUser($user);

            $log = new LogUserDeleteEvent($user);
            $this->eventDispatcher->dispatch('log', $log);
        }

        return new Response('user(s) removed', 204);
    }

    /**
     * @EXT\Route(
     *     "users/page/{page}",
     *     name="claro_admin_user_list",
     *     defaults={"page"=1, "search"=""},
     *     options = {"expose"=true}
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Route(
     *     "users/page/{page}/search/{search}",
     *     name="claro_admin_user_list_search",
     *     defaults={"page"=1},
     *     options = {"expose"=true}
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Template()
     *
     * Displays the platform user list.
     */
    public function userListAction($page, $search)
    {
        $query = ($search === '') ?
            $this->userManager->getAllUsers(true) :
            $this->userManager->getUsersByName($search, true);
        $pager = $this->pagerFactory->createPager($query, $page);

        return array('pager' => $pager, 'search' => $search);
    }

    /**
     * @EXT\Route(
     *     "/groups/page/{page}",
     *     name="claro_admin_group_list",
     *     options={"expose"=true},
     *     defaults={"page"=1, "search"=""}
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Route(
     *     "groups/page/{page}/search/{search}",
     *     name="claro_admin_group_list_search",
     *     defaults={"page"=1},
     *     options = {"expose"=true}
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Template()
     *
     * Returns the platform group list.
     */
    public function groupListAction($page, $search)
    {
        $query = ($search === '') ?
            $this->groupManager->getAllGroups(true) :
            $this->groupManager->getGroupsByName($search, true);
        $pager = $this->pagerFactory->createPager($query, $page);

        return array('pager' => $pager, 'search' => $search);
    }

    /**
     * @EXT\Route(
     *     "/group/{groupId}/users/page/{page}",
     *     name="claro_admin_user_of_group_list",
     *     options={"expose"=true},
     *     defaults={"page"=1, "search"=""}
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Route(
     *     "/group/{groupId}/users/page/{page}/search/{search}",
     *     name="claro_admin_user_of_group_list_search",
     *     options={"expose"=true},
     *     defaults={"page"=1}
     * )
     * @EXT\Method("GET")
     * @EXT\ParamConverter(
     *      "group",
     *      class="ClarolineCoreBundle:Group",
     *      options={"id" = "groupId", "strictId" = true}
     * )
     *
     * @EXT\Template()
     *
     * Returns the users of a group.
     */
    public function usersOfGroupListAction(Group $group, $page, $search)
    {
        $query = ($search === '') ?
            $this->userManager->getUsersByGroup($group, true) :
            $this->userManager->getUsersByNameAndGroup($search, $group, true);
        $pager = $this->pagerFactory->createPager($query, $page);

        return array('pager' => $pager, 'search' => $search, 'group' => $group);
    }

    /**
     * @EXT\Route(
     *     "/group/add/{groupId}/page/{page}",
     *     name="claro_admin_outside_of_group_user_list",
     *     options={"expose"=true},
     *     defaults={"page"=1, "search"=""}
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Route(
     *     "/group/add/{groupId}/page/{page}/search/{search}",
     *     name="claro_admin_outside_of_group_user_list_search",
     *     options={"expose"=true},
     *     defaults={"page"=1}
     * )
     * @EXT\Method("GET")
     * @EXT\ParamConverter(
     *      "group",
     *      class="ClarolineCoreBundle:Group",
     *      options={"id" = "groupId", "strictId" = true}
     * )
     *
     * @EXT\Template()
     *
     * Displays the user list with a control allowing to add them to a group.
     */
    public function outsideOfGroupUserListAction(Group $group, $page, $search)
    {
        $query = ($search === '') ?
            $this->userManager->getGroupOutsiders($group, true) :
            $this->userManager->getGroupOutsidersByName($group, $search, true);
        $pager = $this->pagerFactory->createPager($query, $page);

        return array('pager' => $pager, 'search' => $search, 'group' => $group);
    }

    /**
     * @EXT\Route(
     *     "/group/form",
     *     name="claro_admin_group_creation_form"
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Template()
     *
     * Displays the group creation form.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function groupCreationFormAction()
    {
        $form = $this->formFactory->create(FormFactory::TYPE_GROUP, array());

        return array('form_group' => $form->createView());
    }

    /**
     * @EXT\Route(
     *     "/group",
     *     name="claro_admin_create_group"
     * )
     * @EXT\Method("POST")
     *
     * @EXT\Template("ClarolineCoreBundle:Administration:groupCreationForm.html.twig")
     *
     * Creates a group and redirects to the group list.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function createGroupAction()
    {
        $request = $this->get('request');
        $form = $this->formFactory->create(FormFactory::TYPE_GROUP, array());
        $form->handleRequest($request);

        if ($form->isValid()) {
            $group = $form->getData();
            $userRole = $this->roleManager->getRoleByName('ROLE_USER');
            $group->setPlatformRole($userRole);
            $this->groupManager->insertGroup($group);

            $log = new LogGroupCreateEvent($group);
            $this->eventDispatcher->dispatch('log', $log);

            return $this->redirect($this->generateUrl('claro_admin_group_list'));
        }

        return array('form_group' => $form->createView());
    }

    /**
     * @EXT\Route(
     *     "/group/{groupId}/users",
     *     name="claro_admin_multiadd_user_to_group",
     *     requirements={"groupId"="^(?=.*[0-9].*$)\d*$"},
     *     options={"expose"=true}
     * )
     * @EXT\Method("PUT")
     * @EXT\ParamConverter(
     *      "group",
     *      class="ClarolineCoreBundle:Group",
     *      options={"id" = "groupId", "strictId" = true}
     * )
     * @EXT\ParamConverter(
     *     "users",
     *      class="ClarolineCoreBundle:User",
     *      options={"multipleIds" = true}
     * )
     *
     * Adds multiple user to a group.
     *
     * @param integer $groupId
     *
     * @return Response
     */
    public function addUsersToGroupAction(Group $group, array $users)
    {
        $this->groupManager->addUsersToGroup($group, $users);

        foreach ($users as $user) {
            $log = new LogGroupAddUserEvent($group, $user);
            $this->eventDispatcher->dispatch('log', $log);
        }

        return new Response('success', 204);
    }

    /**
     * @EXT\Route(
     *     "/group/{groupId}/users",
     *     name="claro_admin_multidelete_user_from_group",
     *     options={"expose"=true},
     *     requirements={"groupId"="^(?=.*[1-9].*$)\d*$"}
     * )
     * @EXT\Method("DELETE")
     * @EXT\ParamConverter(
     *      "group",
     *      class="ClarolineCoreBundle:Group",
     *      options={"id" = "groupId", "strictId" = true}
     * )
     * @EXT\ParamConverter(
     *     "users",
     *      class="ClarolineCoreBundle:User",
     *      options={"multipleIds" = true}
     * )
     *
     * Removes users from a group.
     *
     * @param integer $groupId
     *
     * @return Response
     */
    public function deleteUsersFromGroupAction(Group $group, array $users)
    {
        $this->groupManager->removeUsersFromGroup($group, $users);

        foreach ($users as $user) {
            $log = new LogGroupRemoveUserEvent($group, $user);
            $this->eventDispatcher->dispatch('log', $log);
        }

        return new Response('user removed', 204);
    }

    /**
     * @EXT\Route(
     *     "/groups",
     *     name="claro_admin_multidelete_group",
     *     options={"expose"=true}
     * )
     * @EXT\Method("DELETE")
     * @EXT\ParamConverter(
     *     "groups",
     *      class="ClarolineCoreBundle:Group",
     *      options={"multipleIds" = true}
     * )
     *
     * Deletes multiple groups.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteGroupsAction(array $groups)
    {
        foreach ($groups as $group) {
            $this->groupManager->deleteGroup($group);

            $log = new LogGroupDeleteEvent($group);
            $this->eventDispatcher->dispatch('log', $log);
        }

        return new Response('groups removed', 204);
    }

    /**
     * @EXT\Route(
     *     "/group/settings/form/{groupId}",
     *     name="claro_admin_group_settings_form",
     *     requirements={"groupId"="^(?=.*[1-9].*$)\d*$"}
     * )
     * @EXT\Method("GET")
     * @EXT\ParamConverter(
     *      "group",
     *      class="ClarolineCoreBundle:Group",
     *      options={"id" = "groupId", "strictId" = true}
     * )
     *
     * @EXT\Template()
     *
     * Displays an edition form for a group.
     *
     * @param integer $groupId
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function groupSettingsFormAction(Group $group)
    {
        $form = $this->formFactory->create(FormFactory::TYPE_GROUP_SETTINGS, array(), $group);

        return array(
            'group' => $group,
            'form_settings' => $form->createView()
        );
    }

    /**
     * @EXT\Route(
     *     "/group/settings/update/{groupId}",
     *     name="claro_admin_update_group_settings"
     * )
     * @EXT\ParamConverter(
     *      "group",
     *      class="ClarolineCoreBundle:Group",
     *      options={"id" = "groupId", "strictId" = true}
     * )
     *
     * @EXT\Template("ClarolineCoreBundle:Administration:groupSettingsForm.html.twig")
     *
     * Updates the settings of a group and redirects to the group list.
     *
     * @param integer $groupId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function updateGroupSettingsAction(Group $group)
    {
        $request = $this->get('request');
        $em = $this->getDoctrine()->getManager();

        $oldPlatformRoleTransactionKey = $group->getPlatformRole()->getTranslationKey();

        $form = $this->formFactory->create(FormFactory::TYPE_GROUP_SETTINGS, array(), $group);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $group = $form->getData();

            $unitOfWork = $em->getUnitOfWork();
            $unitOfWork->computeChangeSets();
            $changeSet = $unitOfWork->getEntityChangeSet($group);

            //The changeSet don't manage manyToMany
            $newPlatformRoleTransactionKey = $group->getPlatformRole()->getTranslationKey();

            if ($oldPlatformRoleTransactionKey !== $newPlatformRoleTransactionKey) {
                $changeSet['platformRole'] = array($oldPlatformRoleTransactionKey, $newPlatformRoleTransactionKey);
            }
            $this->groupManager->updateGroup($group);

            $log = new LogGroupUpdateEvent($group, $changeSet);
            $this->eventDispatcher->dispatch('log', $log);

            return $this->redirect($this->generateUrl('claro_admin_group_list'));
        }

        return array(
            'group' => $group,
            'form_settings' => $form->createView()
        );
    }

    /**
     * @EXT\Route(
     *     "/platform/settings/form",
     *     name="claro_admin_platform_settings_form"
     * )
     * @EXT\Route(
     *     "/",
     *     name="claro_admin_index",
     *     options={"expose"=true}
     * )
     *
     * @EXT\Template()
     *
     * Displays the platform settings.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function platformSettingsFormAction()
    {
        $platformConfig = $this->configHandler->getPlatformConfig();
        $form = $this->formFactory->create(
            FormFactory::TYPE_PLATFORM_PARAMETERS,
            array($this->getThemes()),
            $platformConfig
        );

        return array('form_settings' => $form->createView());
    }

    /**
     * @EXT\Route(
     *     "claro_admin_update_platform_settings",
     *     name="claro_admin_update_platform_settings"
     * )
     *
     * @EXT\Template("ClarolineCoreBundle:Administration:platformSettingsForm.html.twig")
     *
     * Updates the platform settings and redirects to the settings form.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function updatePlatformSettingsAction()
    {
        $request = $this->get('request');
        $form = $this->formFactory->create(
            FormFactory::TYPE_PLATFORM_PARAMETERS,
            array($this->getThemes())
        );
        $form->handleRequest($request);

        if ($form->isValid()) {
            try {
                $this->configHandler->setParameter(
                    'allow_self_registration',
                    $form['selfRegistration'] ->getData()
                );
                $this->configHandler->setParameter(
                    'locale_language',
                    $form['localLanguage']->getData()
                );
                $this->configHandler->setParameter(
                    'theme',
                    $form['theme']->getData()
                );
            } catch (UnwritableException $e) {
                $form->addError(
                    new FormError(
                        $this->get('translator')
                        ->trans('unwritable_file_exception', array('%path%' => $e->getPath()), 'platform')
                    )
                );

                return array('form_settings' => $form->createView());
            }
        }

        return $this->redirect($this->generateUrl('claro_admin_platform_settings_form'));
    }

    /**
     * @EXT\Route(
     *     "plugins",
     *     name="claro_admin_plugins"
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Template()
     *
     * Display the plugin list
     *
     * @return Response
     */
    public function pluginListAction()
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $plugins = $em->getRepository('ClarolineCoreBundle:Plugin')->findAll();

        return array('plugins' => $plugins);
    }

    /**
     * @EXT\Route(
     *     "/plugin/{domain}/options",
     *     name="claro_admin_plugin_options"
     * )
     * @EXT\Method("GET")
     *
     * Redirects to the plugin mangagement page.
     *
     * @param string $domain
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function pluginParametersAction($domain)
    {
        $event = new PluginOptionsEvent();
        $eventName = "plugin_options_{$domain}";
        $this->eventDispatcher->dispatch($eventName, $event);

        if (!$event->getResponse() instanceof Response) {
            throw new \Exception(
                "Custom event '{$eventName}' didn't return any Response."
            );
        }

        return $event->getResponse();
    }

    /**
     * @EXT\Route(
     *    "user/management",
     *    name="claro_admin_users_management"
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Template()
     *
     * @return Response
     */
    public function usersManagementAction()
    {
        return array();
    }

    /**
     * @EXT\Route(
     *    "user/management/import/form",
     *     name="claro_admin_import_users_form"
     * )
     * @EXT\Method("GET")
     *
     * @EXT\Template()
     *
     * @return Response
     */
    public function importUsersFormAction()
    {
        $form = $this->formFactory->create(FormFactory::TYPE_USER_IMPORT);

        return array('form' => $form->createView());
    }

    /**
     * @EXT\Route(
     *     "user/management/import",
     *     name="claro_admin_import_users"
     * )
     *
     * @EXT\Method("POST")
     *
     * @EXT\Template("ClarolineCoreBundle:Administration:importUsersForm.html.twig")
     *
     * @return Response
     */
    public function importUsers()
    {
        $request = $this->get('request');
        $form = $this->formFactory->create(FormFactory::TYPE_USER_IMPORT);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $file = $form->get('file')->getData();
            $lines = str_getcsv(file_get_contents($file), PHP_EOL, ',');

            foreach ($lines as $line) {
                $users[] = str_getcsv($line);
            }

            $this->userManager->importUsers($users);

            return $this->redirect($this->generateUrl('claro_admin_users_management'));
        }

        return array('form' => $form->createView());
    }

    /**
     *  Get the list of themes availables.
     *  @TODO use directory iterator
     *
     *  @param $path string The path of the themes.
     *
     *  @return array with a list of the themes availables.
     */
    private function getThemes($path = "/../Resources/views/less/")
    {
        $tmp = array();

        $manager = $this->getDoctrine()->getManager();
        $themes = $manager->getRepository("ClarolineCoreBundle:Theme\Theme")->findAll();

        foreach ($themes as $theme) {
            $tmp[$theme->getPath()] = $theme->getName();
        }

        return $tmp;
    }

    /**
     * @EXT\Route(
     *     "/logs/",
     *     name="claro_admin_logs_show",
     *     defaults={"page" = 1}
     * )
     * @EXT\Route(
     *     "/logs/{page}",
     *     name="claro_admin_logs_show_paginated",
     *     requirements={"page" = "\d+"},
     *     defaults={"page" = 1}
     * )
     *
     * @EXT\Method("GET")
     *
     * @EXT\Template()
     *
     * Displays logs list using filter parameteres and page number
     *
     * @param $page int The requested page number.
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function logListAction($page)
    {
        return $this->get('claroline.log.manager')->getAdminList($page);
    }

    /**
     * @EXT\Route(
     *     "/analytics/",
     *     name="claro_admin_analytics_show"
     * )
     *
     * @EXT\Method("GET")
     *
     * @EXT\Template("ClarolineCoreBundle:Administration:analytics.html.twig")
     *
     * Displays platform analytics home page
     *
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function analyticsAction()
    {
        $actionsForRange = $this->get('claroline.analytics.manager')->getDailyActionNumberForDateRange();
        $lastMonthActions = $actionsForRange["chartData"];
        $mostViewedWS = $this->get('claroline.analytics.manager')->topWSByAction(null, 'ws_tool_read', 5);
        $mostViewedMedia = $this->get('claroline.analytics.manager')->topMediaByAction(null, 'resource_read', 5);
        $mostDownloadedResources = $this->get('claroline.analytics.manager')->topResourcesByAction(null, 'resource_export', 5);
        $usersCount = $this->userManager->getNbUsers();

        return array(
            'barChartData' => $lastMonthActions,
            'usersCount' => $usersCount,
            'mostViewedWS' => $mostViewedWS,
            'mostViewedMedia' => $mostViewedMedia,
            'mostDownloadedResources' => $mostDownloadedResources
        );
    }

    /**
     * @EXT\Route(
     *     "/analytics/connections",
     *     name="claro_admin_analytics_connections"
     * )
     *
     * @EXT\Method({"GET", "POST"})
     *
     * @EXT\Template("ClarolineCoreBundle:Administration:analytics_connections.html.twig")
     *
     * Displays platform analytics connections page
     *
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function analyticsConnectionsAction()
    {
        $request = $this->get('request');
        $criteria_form = $this->formFactory->create(FormFactory::TYPE_ADMIN_ANALYTICS_CONNECTIONS);
        $clone_form = clone $criteria_form;
        $criteria_form->bind($request);
        $unique = false;
        if ($criteria_form->isValid()) {
            $range = $criteria_form->get('range')->getData();
            $unique = ($criteria_form->get('unique')->getData()=='true') ? true : false;
        }
        $actionsForRange = $this
                        ->get('claroline.analytics.manager')
                        ->getDailyActionNumberForDateRange($range, 'user_login',$unique);
        if ($range === null) {
            $clone_form->get('range')->setData($actionsForRange['range']);
            $clone_form->get('unique')->setData($unique);
            $criteria_form = $clone_form;
        }

        $connections = $actionsForRange['chartData'];
        $activeUsers = $this->get('claroline.analytics.manager')->getActiveUsers();

        return array(
            'connections' => $connections,
            'form_criteria' => $criteria_form->createView(),
            'activeUsers' => $activeUsers
        );
    }

    /**
     * @EXT\Route(
     *     "/analytics/resources",
     *     name="claro_admin_analytics_resources"
     * )
     *
     * @EXT\Method("GET")
     *
     * @EXT\Template("ClarolineCoreBundle:Administration:analytics_resources.html.twig")
     *
     * Displays platform analytics resources page
     *
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function analyticsResourcesAction()
    {
        $manager = $this->get('doctrine.orm.entity_manager');
        $wsCount = $manager->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')
            ->count();
        $resourceCount = $manager->getRepository('ClarolineCoreBundle:Resource\ResourceType')
            ->countResourcesByType();

        return array(
            'wsCount' => $wsCount,
            'resourceCount' => $resourceCount
        );
    }

    /**
     * @EXT\Route(
     *     "/analytics/top/{top_type}",
     *     name="claro_admin_analytics_top",
     *     defaults={"top_type" = "top_users_connections"}
     * )
     *
     * @EXT\Method({"GET", "POST"})
     *
     * @EXT\Template("ClarolineCoreBundle:Administration:analytics_top.html.twig")
     *
     * Displays platform analytics top activity page
     *
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function analyticsTopAction($top_type)
    {
        $request = $this->get('request');
        $criteria_form = $this->formFactory->create(FormFactory::TYPE_ADMIN_ANALYTICS_TOP);
        $clone_form = clone $criteria_form;
        $criteria_form->bind($request);

        $range = $criteria_form->get('range')->getData();
        if($range===null) {
            $range = $this->get('claroline.analytics.manager')->getDefaultRange();
        }
        $top_type_temp = $criteria_form->get('top_type')->getData();
        $top_type = ($top_type_temp!==null)?$top_type_temp:$top_type;
        $max = $criteria_form->get('top_number')->getData();
        $max = ($max!==null)?intval($max):30;

        $listData = $this
                        ->get('claroline.analytics.manager')
                        ->getTopByCriteria($range, $top_type, $max);

        $clone_form->get('range')->setData($range);
        $clone_form->get('top_type')->setData($top_type);
        $clone_form->get('top_number')->setData($max);
        $criteria_form = $clone_form;

        return array(
            'form_criteria' => $criteria_form->createView(),
            'list_data' => $listData
        );
    }
}
