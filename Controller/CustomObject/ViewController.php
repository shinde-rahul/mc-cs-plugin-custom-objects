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

namespace MauticPlugin\CustomObjectsBundle\Controller\CustomObject;

use Mautic\CoreBundle\Form\Type\DateRangeType;
use Mautic\CoreBundle\Model\AuditLogModel;
use Symfony\Component\HttpFoundation\RequestStack;
use MauticPlugin\CustomObjectsBundle\Model\CustomObjectModel;
use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\CustomObjectsBundle\Exception\NotFoundException;
use MauticPlugin\CustomObjectsBundle\Provider\CustomObjectPermissionProvider;
use MauticPlugin\CustomObjectsBundle\Exception\ForbiddenException;
use MauticPlugin\CustomObjectsBundle\Provider\CustomObjectRouteProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormFactoryInterface;

class ViewController extends CommonController
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var CustomObjectModel
     */
    private $customObjectModel;

    /**
     * @var AuditLogModel
     */
    private $auditLogModel;

    /**
     * @var CustomObjectPermissionProvider
     */
    private $permissionProvider;

    /**
     * @var CustomObjectRouteProvider
     */
    private $routeProvider;

    /**
     * @param RequestStack                   $requestStack
     * @param FormFactoryInterface           $formFactory
     * @param CustomObjectModel              $customObjectModel
     * @param AuditLogModel                  $auditLogModel
     * @param CustomObjectPermissionProvider $permissionProvider
     * @param CustomObjectRouteProvider      $routeProvider
     */
    public function __construct(
        RequestStack $requestStack,
        FormFactoryInterface $formFactory,
        CustomObjectModel $customObjectModel,
        AuditLogModel $auditLogModel,
        CustomObjectPermissionProvider $permissionProvider,
        CustomObjectRouteProvider $routeProvider
    ) {
        $this->requestStack         = $requestStack;
        $this->formFactory          = $formFactory;
        $this->customObjectModel    = $customObjectModel;
        $this->auditLogModel        = $auditLogModel;
        $this->permissionProvider   = $permissionProvider;
        $this->routeProvider        = $routeProvider;
    }

    /**
     * @param int $objectId
     *
     * @return Response
     */
    public function viewAction(int $objectId): Response
    {
        try {
            $customObject = $this->customObjectModel->fetchEntity($objectId);
            $this->permissionProvider->canView($customObject);
        } catch (NotFoundException $e) {
            return $this->notFound($e->getMessage());
        } catch (ForbiddenException $e) {
            return $this->accessDenied(false, $e->getMessage());
        }

        $route         = $this->routeProvider->buildViewRoute($objectId);
        $dateRangeForm = $this->formFactory->create(
            DateRangeType::class,
            $this->requestStack->getCurrentRequest()->get('daterange', []),
            ['action' => $route]
        );
        $stats = $this->customObjectModel->getItemsLineChartData(
            new \DateTime($dateRangeForm->get('date_from')->getData()),
            new \DateTime($dateRangeForm->get('date_to')->getData()),
            $customObject
        );

        $auditLogs = $this->auditLogModel->getLogForObject(
            'customObject', $objectId, $customObject->getDateAdded(), 10, 'customObjects'
        );

        return $this->delegateView(
            [
                'returnUrl'      => $route,
                'viewParameters' => [
                    'customObject'  => $customObject,
                    'dateRangeForm' => $dateRangeForm->createView(),
                    'stats'         => $stats,
                    'logs'          => $auditLogs,
                ],
                'contentTemplate' => 'CustomObjectsBundle:CustomObject:detail.html.php',
                'passthroughVars' => [
                    'mauticContent' => 'customObject',
                    'activeLink'    => "#mautic_custom_object_{$objectId}",
                    'route'         => $route,
                ],
            ]
        );
    }
}