<?php

declare(strict_types=1);

/*
 * @copyright   2018 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\CustomObjectsBundle\Controller\CustomItem;

use Symfony\Component\HttpFoundation\Response;
use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\CustomObjectsBundle\Model\CustomItemModel;
use MauticPlugin\CustomObjectsBundle\Exception\NotFoundException;
use MauticPlugin\CustomObjectsBundle\Provider\CustomItemPermissionProvider;
use MauticPlugin\CustomObjectsBundle\Exception\ForbiddenException;
use MauticPlugin\CustomObjectsBundle\Provider\CustomItemRouteProvider;
use MauticPlugin\CustomObjectsBundle\Provider\CustomItemSessionProvider;
use Mautic\CoreBundle\Service\FlashBag;

class DeleteController extends CommonController
{
    /**
     * @var CustomItemModel
     */
    private $customItemModel;

    /**
     * @var CustomItemSessionProvider
     */
    private $sessionProvider;

    /**
     * @var CustomItemPermissionProvider
     */
    private $permissionProvider;

    /**
     * @var CustomItemRouteProvider
     */
    private $routeProvider;

    /**
     * @var FlashBag
     */
    private $flashBag;

    /**
     * @param CustomItemModel              $customItemModel
     * @param CustomItemSessionProvider    $sessionProvider
     * @param FlashBag                     $flashBag
     * @param CustomItemPermissionProvider $permissionProvider
     * @param CustomItemRouteProvider      $routeProvider
     */
    public function __construct(
        CustomItemModel $customItemModel,
        CustomItemSessionProvider $sessionProvider,
        FlashBag $flashBag,
        CustomItemPermissionProvider $permissionProvider,
        CustomItemRouteProvider $routeProvider
    ) {
        $this->customItemModel    = $customItemModel;
        $this->sessionProvider    = $sessionProvider;
        $this->flashBag           = $flashBag;
        $this->permissionProvider = $permissionProvider;
        $this->routeProvider      = $routeProvider;
    }

    /**
     * @param int $objectId
     * @param int $itemId
     *
     * @return Response
     */
    public function deleteAction(int $objectId, int $itemId): Response
    {
        try {
            $customItem = $this->customItemModel->fetchEntity($itemId);
            $this->permissionProvider->canDelete($customItem);
        } catch (NotFoundException $e) {
            return $this->notFound($e->getMessage());
        } catch (ForbiddenException $e) {
            return $this->accessDenied(false, $e->getMessage());
        }

        $this->customItemModel->delete($customItem);

        $this->flashBag->add(
            'mautic.core.notice.deleted',
            [
                '%name%' => $customItem->getName(),
                '%id%'   => $customItem->getId(),
            ]
        );

        $page = $this->sessionProvider->getPage();

        return $this->postActionRedirect(
            [
                'returnUrl'       => $this->routeProvider->buildListRoute($objectId, $page),
                'viewParameters'  => ['objectId' => $objectId, 'page' => $page],
                'contentTemplate' => 'CustomObjectsBundle:CustomItem\List:list',
                'passthroughVars' => [
                    'mauticContent' => 'customItem',
                ],
            ]
        );
    }
}