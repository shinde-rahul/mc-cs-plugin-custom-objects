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

use MauticPlugin\CustomObjectsBundle\Entity\CustomObject;
use MauticPlugin\CustomObjectsBundle\Form\Type\CustomObjectType;
use MauticPlugin\CustomObjectsBundle\Model\CustomFieldModel;
use MauticPlugin\CustomObjectsBundle\Provider\CustomFieldTypeProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormFactory;
use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\CustomObjectsBundle\Model\CustomObjectModel;
use MauticPlugin\CustomObjectsBundle\Exception\NotFoundException;
use MauticPlugin\CustomObjectsBundle\Exception\ForbiddenException;
use MauticPlugin\CustomObjectsBundle\Provider\CustomObjectPermissionProvider;
use MauticPlugin\CustomObjectsBundle\Provider\CustomObjectRouteProvider;

class FormController extends CommonController
{
    /**
     * @var FormFactory
     */
    private $formFactory;

    /**
     * @var CustomObjectModel
     */
    private $customObjectModel;

    /**
     * @var CustomFieldModel
     */
    private $customFieldModel;

    /**
     * @var CustomObjectPermissionProvider
     */
    private $permissionProvider;

    /**
     * @var CustomObjectRouteProvider
     */
    private $routeProvider;

    /**
     * @var CustomFieldTypeProvider
     */
    private $customFieldTypeProvider;

    /**
     * @param FormFactory                    $formFactory
     * @param CustomObjectModel              $customObjectModel
     * @param CustomFieldModel               $customFieldModel
     * @param CustomObjectPermissionProvider $permissionProvider
     * @param CustomObjectRouteProvider      $routeProvider
     * @param CustomFieldTypeProvider        $customFieldTypeProvider
     */
    public function __construct(
        FormFactory $formFactory,
        CustomObjectModel $customObjectModel,
        CustomFieldModel $customFieldModel,
        CustomObjectPermissionProvider $permissionProvider,
        CustomObjectRouteProvider $routeProvider,
        CustomFieldTypeProvider $customFieldTypeProvider
    ) {
        $this->formFactory             = $formFactory;
        $this->customObjectModel       = $customObjectModel;
        $this->customFieldModel        = $customFieldModel;
        $this->permissionProvider      = $permissionProvider;
        $this->routeProvider           = $routeProvider;
        $this->customFieldTypeProvider = $customFieldTypeProvider;
    }

    /**
     * @return Response
     */
    public function newAction(): Response
    {
        try {
            $this->permissionProvider->canCreate();
            $customObject = new CustomObject();
        } catch (NotFoundException $e) {
            return $this->notFound($e->getMessage());
        } catch (ForbiddenException $e) {
            return $this->accessDenied(false, $e->getMessage());
        }

        return $this->renderForm($customObject, $this->routeProvider->buildNewRoute());
    }

    /**
     * @param int $objectId
     *
     * @return Response
     */
    public function editAction(int $objectId): Response
    {
        try {
            $customObject = $this->customObjectModel->fetchEntity($objectId);
            $this->permissionProvider->canEdit($customObject);
        } catch (NotFoundException $e) {
            return $this->notFound($e->getMessage());
        } catch (ForbiddenException $e) {
            return $this->accessDenied(false, $e->getMessage());
        }

        return $this->renderForm($customObject, $this->routeProvider->buildEditRoute($objectId));
    }

    /**
     * @param int $objectId
     *
     * @return Response
     */
    public function cloneAction(int $objectId): Response
    {
        try {
            $customObject = clone $this->customObjectModel->fetchEntity($objectId);
            $this->permissionProvider->canClone($customObject);
        } catch (NotFoundException $e) {
            return $this->notFound($e->getMessage());
        } catch (ForbiddenException $e) {
            return $this->accessDenied(false, $e->getMessage());
        }

        return $this->renderForm($customObject, $this->routeProvider->buildCloneRoute($objectId));
    }

    /**
     * @param CustomObject $customObject
     * @param string       $route
     *
     * @return Response
     */
    private function renderForm(CustomObject $customObject, string $route): Response
    {
        $form = $this->formFactory->create(
            CustomObjectType::class,
            $customObject,
            ['action' => $this->routeProvider->buildSaveRoute($customObject->getId())]
        );

        return $this->delegateView(
            [
                'returnUrl'      => $this->routeProvider->buildListRoute(),
                'viewParameters' => [
                    'customObject'        => $customObject,
                    'availableFieldTypes' => $this->customFieldTypeProvider->getTypes(),
                    'customFields'        => $this->customFieldModel->fetchCustomFieldsForObject($customObject),
                    'deletedFields'       => [],
                    'form'                => $form->createView(),
                ],
                'contentTemplate' => 'CustomObjectsBundle:CustomObject:form.html.php',
                'passthroughVars' => [
                    'mauticContent' => 'customObject',
                    'route'         => $route,
                ],
            ]
        );
    }
}