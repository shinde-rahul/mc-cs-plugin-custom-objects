<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Mautic\LeadBundle\Event\LeadListFiltersChoicesEvent;
use Mautic\LeadBundle\Event\OverrideOperatorLabelEvent;
use Mautic\LeadBundle\Event\TypeOperatorsEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Provider\FilterOperatorProviderInterface;
use Mautic\LeadBundle\Provider\TypeOperatorProvider;
use Mautic\LeadBundle\Provider\TypeOperatorProviderInterface;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\CustomObjectsBundle\CustomFieldType\DateType;
use MauticPlugin\CustomObjectsBundle\CustomFieldType\IntType;
use MauticPlugin\CustomObjectsBundle\Entity\CustomField;
use MauticPlugin\CustomObjectsBundle\Entity\CustomObject;
use MauticPlugin\CustomObjectsBundle\EventListener\SegmentFiltersChoicesGenerateSubscriber;
use MauticPlugin\CustomObjectsBundle\Provider\ConfigProvider;
use MauticPlugin\CustomObjectsBundle\Provider\CustomFieldTypeProvider;
use MauticPlugin\CustomObjectsBundle\Repository\CustomObjectRepository;
use MauticPlugin\CustomObjectsBundle\Tests\ProjectVersionTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;

class SegmentFiltersChoicesGenerateSubscriberTest extends TestCase
{
    use ProjectVersionTrait;

    /**
     * @var CustomObjectRepository|MockObject
     */
    private $customObjectRepository;

    /**
     * @var TranslatorInterface|MockObject
     */
    private $translator;

    /**
     * @var ConfigProvider|MockObject
     */
    private $configProvider;

    /**
     * @var CustomFieldTypeProvider|MockObject
     */
    private $fieldTypeProvider;

    /**
     * @var SegmentFiltersChoicesGenerateSubscriber
     */
    private $subscriber;

    /**
     * @var FilterOperatorProviderInterface|MockObject
     */
    private $filterOperatorProvider;

    /**
     * @var MockObject|TypeOperatorProviderInterface
     */
    private $typeOperatorProvider;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $dispatcher;

    public function setUp(): void
    {
        parent::setUp();

        $this->customObjectRepository = $this->createMock(CustomObjectRepository::class);
        $this->translator             = $this->createMock(TranslatorInterface::class);
        $this->configProvider         = $this->createMock(ConfigProvider::class);
        $this->fieldTypeProvider      = $this->createMock(CustomFieldTypeProvider::class);
        $this->filterOperatorProvider = $this->createMock(FilterOperatorProviderInterface::class);
        $this->dispatcher             = $this->createMock(EventDispatcherInterface::class);
        $this->typeOperatorProvider   = new TypeOperatorProvider(
            $this->dispatcher,
            $this->filterOperatorProvider
        );

        $this->subscriber = new SegmentFiltersChoicesGenerateSubscriber(
            $this->customObjectRepository,
            $this->translator,
            $this->configProvider,
            $this->fieldTypeProvider,
            $this->typeOperatorProvider
        );
    }

    public function testOnGenerateSegmentFilters(): void
    {
        $customObject = new CustomObject();
        $customObject->setId(1);
        $customObject->setNameSingular('Product');
        $customObject->setNamePlural('Products');
        $customObject->setIsPublished(true);

        $intType     = new IntType($this->translator, $this->filterOperatorProvider);
        $customField = new CustomField();
        $customField->setId(1);
        $customField->setType('int');
        $customField->setTypeObject($intType);
        $customField->setLabel('Price');
        $customField->setAlias('price');

        $customObject->addCustomField($customField);

        $criteria = new Criteria(Criteria::expr()->eq('isPublished', 1));

        $keyTypeMapping = [
            'custom.field.type.int'    => 'int',
            'custom.field.type.text'   => 'text',
            'custom.field.type.hidden' => 'hidden',
        ];

        $allOperators = [
            '=' => [
                    'label'       => 'equals',
                    'expr'        => 'eq',
                    'negate_expr' => 'neq',
                ],
            '!=' => [
                    'label'       => 'not equal',
                    'expr'        => 'neq',
                    'negate_expr' => 'eq',
                ],
            'gt' => [
                    'label'       => 'greater than',
                    'expr'        => 'gt',
                    'negate_expr' => 'lt',
                ],
            'gte' => [
                    'label'       => 'greater than or equal',
                    'expr'        => 'gte',
                    'negate_expr' => 'lt',
                ],
            'lt' => [
                    'label'       => 'less than',
                    'expr'        => 'lt',
                    'negate_expr' => 'gt',
                ],
            'lte' => [
                    'label'       => 'less than or equal',
                    'expr'        => 'lte',
                    'negate_expr' => 'gt',
                ],
            'empty' => [
                    'label'       => 'empty',
                    'expr'        => 'empty',
                    'negate_expr' => 'notEmpty',
                ],
            '!empty' => [
                    'label'       => 'not empty',
                    'expr'        => 'notEmpty',
                    'negate_expr' => 'empty',
                ],
            'like' => [
                    'label'       => 'like',
                    'expr'        => 'like',
                    'negate_expr' => 'notLike',
                ],
            '!like' => [
                    'label'       => 'not like',
                    'expr'        => 'notLike',
                    'negate_expr' => 'like',
                ],
            'between' => [
                    'label'       => 'between',
                    'expr'        => 'between',
                    'negate_expr' => 'notBetween',
                ],
            '!between' => [
                    'label'       => 'not between',
                    'expr'        => 'notBetween',
                    'negate_expr' => 'between',
                ],
            'in' => [
                    'label'       => 'including',
                    'expr'        => 'in',
                    'negate_expr' => 'notIn',
                ],
            '!in' => [
                    'label'       => 'excluding',
                    'expr'        => 'notIn',
                    'negate_expr' => 'in',
                ],
            'regexp' => [
                    'label'       => 'regexp',
                    'expr'        => 'regexp',
                    'negate_expr' => 'notRegexp',
                ],
            '!regexp' => [
                    'label'       => 'not regexp',
                    'expr'        => 'notRegexp',
                    'negate_expr' => 'regexp',
                ],
            'date' => [
                    'label'       => 'date',
                    'expr'        => 'date',
                    'negate_expr' => 'date',
                    'hide'        => true,
                ],
            'startsWith' => [
                    'label'       => 'starts with',
                    'expr'        => 'startsWith',
                    'negate_expr' => 'startsWith',
                ],
            'endsWith' => [
                    'label'       => 'ends with',
                    'expr'        => 'endsWith',
                    'negate_expr' => 'endsWith',
                ],
            'contains' => [
                    'label'       => 'contains',
                    'expr'        => 'contains',
                    'negate_expr' => 'contains',
                ],
            'withinCustomObjects' => [
                    'label'       => 'within custom objects',
                    'expr'        => 'withinCustomObjects',
                    'negate_expr' => 'notWithinCustomObjects',
                ],
        ];

        $betweenOperators = $this->isCloudProject() ? [
            'between'               => 'between',
            'not between'           => '!between',
        ] : [];

        $fieldOperators = array_merge([
            'equals'                => '=',
            'not equal'             => '!=',
            'greater than'          => 'gt',
            'greater than or equal' => 'gte',
            'less than'             => 'lt',
            'less than or equal'    => 'lte',
            'empty'                 => 'empty',
            'not empty'             => '!empty',
        ], $betweenOperators);

        $event = new LeadListFiltersChoicesEvent([], [], $this->translator);

        $this->configProvider->expects($this->once())
            ->method('pluginIsEnabled')
            ->willReturn(true);

        $this->fieldTypeProvider->expects($this->once())
            ->method('getKeyTypeMapping')
            ->willReturn($keyTypeMapping);

        $this->customObjectRepository->expects($this->once())
            ->method('matching')
            ->with($criteria)
            ->willReturn(new ArrayCollection([$customObject]));

        $translationsKeys = array_filter([
            ['custom.item.name.label'],
            ['mautic.lead.list.form.operator.equals'],
            ['mautic.lead.list.form.operator.notequals'],
            ['mautic.lead.list.form.operator.isempty'],
            ['mautic.lead.list.form.operator.isnotempty'],
            ['mautic.lead.list.form.operator.islike'],
            ['mautic.lead.list.form.operator.isnotlike'],
            ['mautic.lead.list.form.operator.regexp'],
            ['mautic.lead.list.form.operator.notregexp'],
            ['mautic.core.operator.starts.with'],
            ['mautic.core.operator.ends.with'],
            ['mautic.core.operator.contains'],
            ['mautic.lead.list.form.operator.equals'],
            ['mautic.lead.list.form.operator.notequals'],
            ['mautic.lead.list.form.operator.greaterthan'],
            ['mautic.lead.list.form.operator.greaterthanequals'],
            ['mautic.lead.list.form.operator.lessthan'],
            ['mautic.lead.list.form.operator.lessthanequals'],
            ['mautic.lead.list.form.operator.isempty'],
            ['mautic.lead.list.form.operator.isnotempty'],
            ['mautic.lead.list.form.operator.islike'],
            ['mautic.lead.list.form.operator.isnotlike'],
            $this->isCloudProject() ? ['mautic.lead.list.form.operator.between'] : null,
            $this->isCloudProject() ? ['mautic.lead.list.form.operator.notbetween'] : null,
            ['mautic.lead.list.form.operator.regexp'],
            ['mautic.lead.list.form.operator.notregexp'],
            ['mautic.core.operator.starts.with'],
            ['mautic.core.operator.ends.with'],
            ['mautic.core.operator.contains'],
        ]);

        $translations = array_filter([
            'Mobile',
            'equals',
            'not equal',
            'empty',
            'not empty',
            'like',
            'not like',
            'regexp',
            'not regexp',
            'starts with',
            'ends with',
            'contains',
            'equals',
            'not equal',
            'greater than',
            'greater than or equal',
            'less than',
            'less than or equal',
            'empty',
            'not empty',
            'like',
            'not like',
            $this->isCloudProject() ? 'between' : null,
            $this->isCloudProject() ? 'not between' : null,
            'regexp',
            'not regexp',
            'starts with',
            'ends with',
            'contains',
        ]);

        $this->translator
            ->method('trans')
            ->withConsecutive(...$translationsKeys)
            ->willReturn(...$translations);

        $this->filterOperatorProvider->expects($this->any())
            ->method('getAllOperators')
            ->willReturn($allOperators);

        $this->subscriber->onGenerateSegmentFilters($event);

        $choices = $event->getChoices();
        $this->assertIsArray($choices);
        $this->assertArrayHasKey('custom_object', $choices);
        $this->assertArrayHasKey('cmo_1', $choices['custom_object']);
        $this->assertArrayHasKey('cmf_1', $choices['custom_object']);
        $this->assertSame('Products Mobile', $choices['custom_object']['cmo_1']['label']);
        $this->assertSame('Products : Price', $choices['custom_object']['cmf_1']['label']);
        $this->assertSame($fieldOperators, $choices['custom_object']['cmf_1']['operators']);
    }

    public function testOnGenerateSegmentFiltersForDate(): void
    {
        $customObject = new CustomObject();
        $customObject->setId(1);
        $customObject->setNameSingular('Product');
        $customObject->setNamePlural('Products');
        $customObject->setIsPublished(true);

        $dateType        = new DateType($this->translator, $this->filterOperatorProvider);
        $dateCustomField = new CustomField();
        $dateCustomField->setId(2);
        $dateCustomField->setType('date');
        $dateCustomField->setTypeObject($dateType);
        $dateCustomField->setLabel('Purchase Date');
        $dateCustomField->setAlias('purchase_date');

        $customObject->addCustomField($dateCustomField);

        $criteria = new Criteria(Criteria::expr()->eq('isPublished', 1));

        $keyTypeMapping = [
            'custom.field.type.text'   => 'text',
            'custom.field.type.date'   => 'date',
            'custom.field.type.hidden' => 'hidden',
        ];

        $event = new LeadListFiltersChoicesEvent([], [], $this->translator);

        $this->configProvider->expects($this->once())
            ->method('pluginIsEnabled')
            ->willReturn(true);

        $this->fieldTypeProvider->expects($this->once())
            ->method('getKeyTypeMapping')
            ->willReturn($keyTypeMapping);

        $this->customObjectRepository->expects($this->once())
            ->method('matching')
            ->with($criteria)
            ->willReturn(new ArrayCollection([$customObject]));

        $this->translator
            ->method('trans')
            ->withConsecutive(
                ['custom.item.name.label'],
            )
            ->willReturn(
                'Mobile'
            );

        $this->filterOperatorProvider->expects($this->once())
            ->method('getAllOperators')
            ->willReturn([
                OperatorOptions::GREATER_THAN => [
                    'label'       => 'grater than',
                    'expr'        => 'gt',
                    'negate_expr' => 'lt',
                ],
                OperatorOptions::GREATER_THAN_OR_EQUAL => [
                    'label'       => 'grater than or equal',
                    'expr'        => 'gte',
                    'negate_expr' => 'lt',
                ],
                OperatorOptions::LESS_THAN => [
                    'label'        => 'less than',
                    'expr'         => 'lt',
                    'negagte_expr' => 'gt',
                ],
                OperatorOptions::LESS_THAN_OR_EQUAL => [
                    'label'       => 'less than or equal',
                    'expr'        => 'lte',
                    'negate_expr' => 'gt',
                ],
            ]);

        $this->dispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->withConsecutive(
                [
                    LeadEvents::COLLECT_OPERATORS_FOR_FIELD_TYPE,
                    $this->callback(function (TypeOperatorsEvent $event) {
                        // Emulate a subscriber.
                        $event->setOperatorsForFieldType('date', [
                            'include' => [
                                OperatorOptions::GREATER_THAN,
                                OperatorOptions::GREATER_THAN_OR_EQUAL,
                                OperatorOptions::LESS_THAN,
                                OperatorOptions::LESS_THAN_OR_EQUAL,
                            ],
                        ]);

                        $event->setOperatorsForFieldType('text', [
                            'include' => [
                                OperatorOptions::GREATER_THAN,
                                OperatorOptions::GREATER_THAN_OR_EQUAL,
                                OperatorOptions::LESS_THAN,
                                OperatorOptions::LESS_THAN_OR_EQUAL,
                            ],
                        ]);

                        return true;
                    }),
                ],
                [
                    LeadEvents::OVERRIDE_OPERATOR_LABEL_FOR_FIELD_TYPE,
                    $this->callback(function (OverrideOperatorLabelEvent $event) {
                        // Emulate a subscriber.
                        $event->setTypeOperatorsChoices(
                            [
                                'Greater Than' => OperatorOptions::GREATER_THAN,
                            ]
                        );

                        return true;
                    }),
                ],
                [
                    LeadEvents::OVERRIDE_OPERATOR_LABEL_FOR_FIELD_TYPE,
                    $this->callback(function (OverrideOperatorLabelEvent $event) {
                        // Emulate a subscriber.
                        $event->setTypeOperatorsChoices(
                            [
                                'After'                  => OperatorOptions::GREATER_THAN,
                                'After (Including day)'  => OperatorOptions::GREATER_THAN_OR_EQUAL,
                                'Before'                 => OperatorOptions::LESS_THAN,
                                'Before (Including day)' => OperatorOptions::LESS_THAN_OR_EQUAL,
                            ]
                        );

                        return true;
                    }),
                ],
            );

        $this->subscriber->onGenerateSegmentFilters($event);

        $choices = $event->getChoices();
        $this->assertIsArray($choices);
        $this->assertArrayHasKey('custom_object', $choices);
        $this->assertArrayHasKey('cmo_1', $choices['custom_object']);
        $this->assertArrayHasKey('cmf_2', $choices['custom_object']);
        $this->assertSame('Products Mobile', $choices['custom_object']['cmo_1']['label']);
        $this->assertSame('Products : Purchase Date', $choices['custom_object']['cmf_2']['label']);
        $this->assertSame(
            [
                'After'                  => OperatorOptions::GREATER_THAN,
                'After (Including day)'  => OperatorOptions::GREATER_THAN_OR_EQUAL,
                'Before'                 => OperatorOptions::LESS_THAN,
                'Before (Including day)' => OperatorOptions::LESS_THAN_OR_EQUAL,
            ],
            $choices['custom_object']['cmf_2']['operators']
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertSame(
            [LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE => 'onGenerateSegmentFilters'],
            SegmentFiltersChoicesGenerateSubscriber::getSubscribedEvents()
        );
    }

    public function testOnGenerateSegmentFiltersPluginDisabled(): void
    {
        $this->configProvider->expects($this->once())
            ->method('pluginIsEnabled')
            ->willReturn(false);

        $this->fieldTypeProvider->expects($this->never())
            ->method('getKeyTypeMapping');

        $event = new LeadListFiltersChoicesEvent([], [], $this->translator);
        $this->subscriber->onGenerateSegmentFilters($event);
    }

    public function testOnGenerateSegmentFiltersPluginEnabled(): void
    {
        $this->configProvider->expects($this->once())
            ->method('pluginIsEnabled')
            ->willReturn(true);

        $this->fieldTypeProvider->expects($this->once())
            ->method('getKeyTypeMapping');

        $this->customObjectRepository->expects($this->once())
            ->method('matching')
            ->willReturn(new ArrayCollection());

        $event = new LeadListFiltersChoicesEvent([], [], $this->translator);
        $this->subscriber->onGenerateSegmentFilters($event);
    }
}
