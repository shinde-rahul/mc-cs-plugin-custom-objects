<?php

declare(strict_types=1);

/*
 * @copyright   2019 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\CustomObjectsBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Event\ImportInitEvent;
use Mautic\LeadBundle\Event\ImportMappingEvent;
use Mautic\LeadBundle\Event\ImportProcessEvent;
use MauticPlugin\CustomObjectsBundle\Exception\NotFoundException;
use MauticPlugin\CustomObjectsBundle\Model\CustomObjectModel;
use MauticPlugin\CustomObjectsBundle\Model\CustomItemImportModel;
use MauticPlugin\CustomObjectsBundle\Provider\CustomItemRouteProvider;
use MauticPlugin\CustomObjectsBundle\Provider\ConfigProvider;
use MauticPlugin\CustomObjectsBundle\Provider\CustomItemPermissionProvider;
use MauticPlugin\CustomObjectsBundle\Exception\ForbiddenException;
use Mautic\LeadBundle\Event\ImportValidateEvent;
use Symfony\Component\Form\FormError;
use MauticPlugin\CustomObjectsBundle\Repository\CustomFieldRepository;
use Symfony\Component\Form\Form;
use Mautic\CoreBundle\Helper\ArrayHelper;
use MauticPlugin\CustomObjectsBundle\Entity\CustomField;
use Symfony\Component\Translation\TranslatorInterface;

class ImportSubscriber extends CommonSubscriber
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;
    /**
     * @var CustomObjectModel
     */
    private $customObjectModel;

    /**
     * @var CustomItemImportModel
     */
    private $customItemImportModel;

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var CustomItemPermissionProvider
     */
    private $permissionProvider;

    /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;

    /**
     * @param CustomObjectModel            $customObjectModel
     * @param CustomItemImportModel        $customItemImportModel
     * @param ConfigProvider               $configProvider
     * @param CustomItemPermissionProvider $permissionProvider
     * @param CustomFieldRepository        $customFieldRepository
     * @param TranslatorInterface          $translator
     */
    public function __construct(
        CustomObjectModel $customObjectModel,
        CustomItemImportModel $customItemImportModel,
        ConfigProvider $configProvider,
        CustomItemPermissionProvider $permissionProvider,
        CustomFieldRepository $customFieldRepository,
        TranslatorInterface $translator
    ) {
        $this->customObjectModel     = $customObjectModel;
        $this->customItemImportModel = $customItemImportModel;
        $this->configProvider        = $configProvider;
        $this->permissionProvider    = $permissionProvider;
        $this->customFieldRepository = $customFieldRepository;
        $this->translator            = $translator;
    }

    /**
     * @return mixed[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::IMPORT_ON_INITIALIZE    => 'onImportInit',
            LeadEvents::IMPORT_ON_FIELD_MAPPING => 'onFieldMapping',
            LeadEvents::IMPORT_ON_PROCESS       => 'onImportProcess',
            LeadEvents::IMPORT_ON_VALIDATE      => 'onValidateImport',
        ];
    }

    /**
     * @param ImportInitEvent $event
     */
    public function onImportInit(ImportInitEvent $event): void
    {
        if (!$this->configProvider->pluginIsEnabled()) {
            return;
        }

        try {
            $customObjectId = $this->getCustomObjectId($event->getRouteObjectName());
            $this->permissionProvider->canCreate($customObjectId);
            $customObject = $this->customObjectModel->fetchEntity($customObjectId);
            $event->setObjectIsSupported(true);
            $event->setObjectSingular($event->getRouteObjectName());
            $event->setObjectName($customObject->getNamePlural());
            $event->setActiveLink("#mautic_custom_object_{$customObjectId}");
            $event->setIndexRoute(CustomItemRouteProvider::ROUTE_LIST, ['objectId' => $customObjectId]);
            $event->stopPropagation();
        } catch (NotFoundException | ForbiddenException $e) {
        }
    }

    /**
     * @param ImportMappingEvent $event
     */
    public function onFieldMapping(ImportMappingEvent $event): void
    {
        if (!$this->configProvider->pluginIsEnabled()) {
            return;
        }

        try {
            $customObjectId = $this->getCustomObjectId($event->getRouteObjectName());
            $this->permissionProvider->canCreate($customObjectId);
            $customObject  = $this->customObjectModel->fetchEntity($customObjectId);
            $customFields  = $customObject->getCustomFields();
            $specialFields = [
                'linkedContactIds' => 'custom.item.link.contact.ids',
            ];

            $fieldList = [
                'customItemId'   => 'mautic.core.id',
                'customItemName' => 'custom.item.name.label',
            ];

            foreach ($customFields as $customField) {
                $fieldList[$customField->getId()] = $customField->getName();
            }

            $event->setFields([
                $customObject->getNamePlural() => $fieldList,
                'mautic.lead.special_fields'   => $specialFields,
            ]);
        } catch (NotFoundException | ForbiddenException $e) {
        }
    }

    /**
     * @param ImportValidateEvent $event
     */
    public function onValidateImport(ImportValidateEvent $event): void
    {
        if (!$this->configProvider->pluginIsEnabled()) {
            return;
        }

        try {
            $customObjectId = $this->getCustomObjectId($event->getRouteObjectName());
        } catch (NotFoundException $e) {
            // This is not a Custom Object import. Abort.
            return;
        }

        $form          = $event->getForm();
        $matchedFields = $form->getData();

        $event->setOwnerId($this->handleValidateOwner($matchedFields));

        $matchedFields = array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, array_filter($matchedFields));

        if (empty($matchedFields)) {
            $form->addError(
                new FormError(
                    $this->translator->trans('mautic.lead.import.matchfields', [], 'validators')
                )
            );
        }

        $this->handleValidateRequired($form, $customObjectId, $matchedFields);

        $event->setMatchedFields($matchedFields);
    }

    /**
     * @param ImportProcessEvent $event
     */
    public function onImportProcess(ImportProcessEvent $event): void
    {
        if (!$this->configProvider->pluginIsEnabled()) {
            return;
        }

        try {
            $customObjectId = $this->getCustomObjectId($event->getImport()->getObject());
            $this->permissionProvider->canCreate($customObjectId);
            $customObject = $this->customObjectModel->fetchEntity($customObjectId);
            $merged       = $this->customItemImportModel->import($event->getImport(), $event->getRowData(), $customObject);
            $event->setWasMerged($merged);
        } catch (NotFoundException $e) {
            // Not a Custom Object import or the custom object doesn't exist anymore. Move on.
        }
    }

    /**
     * @param string $routeObjectName
     *
     * @return int
     *
     * @throws NotFoundException
     */
    private function getCustomObjectId(string $routeObjectName): int
    {
        $matches = [];

        if (preg_match('/custom-object:(\d*)/', $routeObjectName, $matches)) {
            return (int) $matches[1];
        }

        throw new NotFoundException("{$routeObjectName} is not a custom object import");
    }

    /**
     * @param mixed[] $matchedFields
     *
     * @return ?int
     */
    private function handleValidateOwner(array $matchedFields): ?int
    {
        $owner = ArrayHelper::getValue('owner', $matchedFields);

        return $owner ? $owner->getId() : null;
    }

    /**
     * Validate that required fields are mapped.
     *
     * @param Form  $form
     * @param int   $customObjectId
     * @param array $matchedFields
     */
    private function handleValidateRequired(Form $form, int $customObjectId, array $matchedFields): void
    {
        $requiredFields = $this->customFieldRepository->getRequiredCustomFieldsForCustomObject($customObjectId);

        $missingRequiredFields = $requiredFields->filter(function (CustomField $customField) use ($matchedFields) {
            return !array_key_exists($customField->getAlias(), $matchedFields);
        })->map(function (CustomField $customField) {
            return "{$customField->getLabel()} ({$customField->getAlias()})";
        });

        if (!in_array('customItemName', $matchedFields, true)) {
            $missingRequiredFields[] = $this->translator->trans('custom.item.name.label');
        }

        if (count($missingRequiredFields)) {
            $form->addError(
                new FormError(
                    $this->translator->trans(
                        'mautic.import.missing.required.fields',
                        [
                            '%requiredFields%' => implode(', ', $missingRequiredFields->toArray()),
                            '%fieldOrFields%'  => 1 === count($missingRequiredFields) ? 'field' : 'fields',
                        ],
                        'validators'
                    )
                )
            );
        }
    }
}
