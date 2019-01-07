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

namespace MauticPlugin\CustomObjectsBundle\Model;

use MauticPlugin\CustomObjectsBundle\Entity\CustomItem;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Model\FormModel;
use MauticPlugin\CustomObjectsBundle\Repository\CustomItemRepository;
use Mautic\CoreBundle\Entity\CommonRepository;
use MauticPlugin\CustomObjectsBundle\Exception\NotFoundException;
use Mautic\CoreBundle\Helper\UserHelper;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use MauticPlugin\CustomObjectsBundle\Provider\CustomItemPermissionProvider;
use MauticPlugin\CustomObjectsBundle\Exception\ForbiddenException;
use MauticPlugin\CustomObjectsBundle\Entity\CustomField;
use MauticPlugin\CustomObjectsBundle\Model\CustomFieldValueModel;
use MauticPlugin\CustomObjectsBundle\Entity\CustomFieldValueText;

class CustomItemModel extends FormModel
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var CustomItemRepository
     */
    private $customItemRepository;

    /**
     * @var CustomItemPermissionProvider
     */
    private $permissionProvider;

    /**
     * @var CustomFieldModel
     */
    private $customFieldModel;

    /**
     * @var CustomFieldValueModel
     */
    private $customFieldValueModel;

    /**
     * @param EntityManager $entityManager
     * @param CustomItemRepository $customItemRepository
     * @param CustomItemPermissionProvider $permissionProvider
     * @param UserHelper $userHelper
     * @param CustomFieldModel $customFieldModel
     * @param CustomFieldValueModel $customFieldValueModel
     */
    public function __construct(
        EntityManager $entityManager,
        CustomItemRepository $customItemRepository,
        CustomItemPermissionProvider $permissionProvider,
        UserHelper $userHelper,
        CustomFieldModel $customFieldModel,
        CustomFieldValueModel $customFieldValueModel
    )
    {
        $this->entityManager         = $entityManager;
        $this->customItemRepository  = $customItemRepository;
        $this->permissionProvider    = $permissionProvider;
        $this->userHelper            = $userHelper;
        $this->customFieldModel      = $customFieldModel;
        $this->customFieldValueModel = $customFieldValueModel;
    }

    /**
     * @param CustomItem $entity
     * 
     * @return CustomItem
     */
    public function save(CustomItem $entity): CustomItem
    {
        $user = $this->userHelper->getUser();
        $now  = new DateTimeHelper();

        if ($entity->isNew()) {
            $entity->setCreatedBy($user->getId());
            $entity->setCreatedByUser($user->getName());
            $entity->setDateAdded($now->getUtcDateTime());
        }

        $entity->setModifiedBy($user->getId());
        $entity->setModifiedByUser($user->getName());
        $entity->setDateModified($now->getUtcDateTime());

        $this->entityManager->persist($entity);

        if ($entity->isNew()) {
            $this->entityManager->flush();
        }

        foreach ($entity->getCustomFieldValues() as $value) {
            $this->entityManager->persist($value);
        }

        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @param integer $id
     * 
     * @return CustomItem
     * 
     * @throws NotFoundException
     */
    public function fetchEntity(int $id): CustomItem
    {
        $entity = parent::getEntity($id);

        if (null === $entity) {
            throw new NotFoundException("Custom Item with ID = {$id} was not found");
        }

        return $this->populateCustomFields($entity);
    }

    /**
     * @param CustomItem $customItem
     * 
     * @return CustomItem
     */
    public function populateCustomFields(CustomItem $customItem): CustomItem
    {
        $values            = $customItem->getCustomFieldValues();
        $customFieldValues = $this->customFieldValueModel->getValuesForItem($customItem);
        $customFields      = $this->customFieldModel->fetchCustomFieldsForObject($customItem->getCustomObject());

        foreach ($customFieldValues as $customFieldValue) {
            $values->set($customFieldValue->getCustomField()->getId(), $customFieldValue);
        }

        foreach ($customFields as $customField) {
            if (null === $values->get($customField->getId())) {
                // @todo the default value should come form the custom field.
                $values->set($customField->getId(), new CustomFieldValueText($customField, $customItem, ''));
            }
        }

        return $customItem;
    }

    /**
     * @param array $args
     * 
     * @return Paginator
     */
    public function fetchEntities(array $args = []): Paginator
    {
        return parent::getEntities($this->addCreatorLimit($args));
    }

    /**
     * Used only by Mautic's generic methods. Use DI instead.
     * 
     * @return CommonRepository
     */
    public function getRepository(): CommonRepository
    {
        return $this->customItemRepository;
    }

    /**
     * Used only by Mautic's generic methods. Use CustomFieldPermissionProvider instead.
     * 
     * @return string
     */
    public function getPermissionBase(): string
    {
        return 'custom_objects:custom_items';
    }

    /**
     * Adds condition for creator if the user doesn't have permissions to view other.
     *
     * @param array $args
     * 
     * @return array
     */
    private function addCreatorLimit(array $args): array
    {
        try {
            $this->permissionProvider->isGranted('viewother');
        } catch (ForbiddenException $e) {
            if (!isset($args['filter'])) {
                $args['filter'] = [];
            }

            if (!isset($args['filter']['force'])) {
                $args['filter']['force'] = [];
            }

            $limitOwnerFilter = [
                [
                    'column' => 'e.createdBy',
                    'expr'   => 'eq',
                    'value'  => $this->userHelper->getUser()->getId(),
                ],
            ];

            $args['filter']['force'] = $args['filter']['force'] + $limitOwnerFilter;
        }

        return $args;
    }
}
