<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Functional\Segment\Query\Functional;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Command\UpdateLeadListsCommand;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;
use Mautic\LeadBundle\Provider\FilterOperatorProviderInterface;
use MauticPlugin\CustomObjectsBundle\CustomFieldType\TextType;
use MauticPlugin\CustomObjectsBundle\Entity\CustomField;
use MauticPlugin\CustomObjectsBundle\Entity\CustomFieldValueText;
use MauticPlugin\CustomObjectsBundle\Entity\CustomItem;
use MauticPlugin\CustomObjectsBundle\Entity\CustomItemXrefContact;
use MauticPlugin\CustomObjectsBundle\Entity\CustomObject;
use MauticPlugin\CustomObjectsBundle\Model\CustomItemModel;
use MauticPlugin\CustomObjectsBundle\Provider\ConfigProvider;
use Symfony\Component\Translation\TranslatorInterface;

class QueryFunctionalTest extends MauticMysqlTestCase
{
    private CustomField $customField;

    private Lead $leadABC;

    private Lead $leadXYZ;

    private Lead $leadEmpty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configParams[ConfigProvider::CONFIG_PARAM_ITEM_VALUE_TO_CONTACT_RELATION_LIMIT] = 0;

        $customItemModel       = self::$container->get('mautic.custom.model.item');
        $this->assertInstanceOf(CustomItemModel::class, $customItemModel);

        [$customObject, $this->customField] = $this->createBookObjectWithPublisherCustomField();

        $bookWithPublisherABC   = $this->createCustomItem($customObject, 'ABC', 'publisherABC');
        $bookWithPublisherXYZ   = $this->createCustomItem($customObject, 'XYZ', 'publisherXYZ');
        $bookWithPublisherEmpty = $this->createCustomItem($customObject, '', 'publisherEmpty');

        $this->leadABC = new Lead();
        $this->leadABC->setFirstname('LeadABC')->setEmail('leadABC@acquia.com')->setIsPublished(true);
        $this->em->persist($this->leadABC);

        $this->leadXYZ = new Lead();
        $this->leadXYZ->setFirstname('LeadXYZ')->setEmail('leadXYZ@acquia.com')->setIsPublished(true);
        $this->em->persist($this->leadXYZ);

        $this->leadEmpty = new Lead();
        $this->leadEmpty->setFirstname('LeadEmpty')->setEmail('leadEmpty@acquia.com')->setIsPublished(true);
        $this->em->persist($this->leadEmpty);

        $this->em->flush();

        $xrefABC = $customItemModel->linkEntity($bookWithPublisherABC, 'contact', (int) $this->leadABC->getId());
        $this->assertInstanceOf(CustomItemXrefContact::class, $xrefABC);

        $xrefXYZ = $customItemModel->linkEntity($bookWithPublisherXYZ, 'contact', (int) $this->leadXYZ->getId());
        $this->assertInstanceOf(CustomItemXrefContact::class, $xrefXYZ);

        $xrefEmpty = $customItemModel->linkEntity($bookWithPublisherEmpty, 'contact', (int) $this->leadEmpty->getId());
        $this->assertInstanceOf(CustomItemXrefContact::class, $xrefEmpty);

        $this->assertCount(3, $this->em->getRepository(CustomItem::class)->findAll());
        $this->assertCount(3, $this->em->getRepository(Lead::class)->findAll());
        $this->assertCount(1, $this->em->getRepository(CustomField::class)->findAll());
        $this->assertCount(1, $this->em->getRepository(CustomObject::class)->findAll());
        $this->assertCount(3, $this->em->getRepository(CustomItemXrefContact::class)->findAll());
    }

    public function testNotEqualsOperatorSegmentOnCustomField(): void
    {
        $filters = [[
            'glue'       => 'and',
            'field'      => 'cmf_'.$this->customField->getId(),
            'object'     => 'custom_object',
            'type'       => 'text',
            'operator'   => '!=',
            'properties' => [
                'filter' => 'ABC',
            ],
        ]];

        $segmentId = $this->createAndUpdateSegment($filters);

        $members = $this->em->getRepository(ListLead::class)->findBy(['list' => $segmentId]);
        $this->assertCount(2, $members);

        $actualMembers = array_map(fn (ListLead $segment) => $segment->getLead()->getId(), $members);
        sort($actualMembers);
        $expectedMembers = [$this->leadXYZ->getId(), $this->leadEmpty->getId()];
        sort($expectedMembers);

        $this->assertNotContains($this->leadABC->getId(), $actualMembers);

        $this->assertSame($actualMembers, $expectedMembers);
    }

    public function testEmptyOperatorSegmentOnCustomField(): void
    {
        $filters = [[
            'glue'       => 'and',
            'field'      => 'cmf_'.$this->customField->getId(),
            'object'     => 'custom_object',
            'type'       => 'text',
            'operator'   => 'empty',
            'properties' => [
                'filter'  => null,
                'display' => null,
            ],
        ]];

        $segmentId = $this->createAndUpdateSegment($filters);

        $members = $this->em->getRepository(ListLead::class)->findBy(['list' => $segmentId]);
        $this->assertCount(1, $members);

        $actualMembers = array_map(fn (ListLead $segment) => $segment->getLead()->getId(), $members);
        sort($actualMembers);
        $expectedMembers = [$this->leadEmpty->getId()];
        sort($expectedMembers);

        $this->assertNotContains($this->leadABC->getId(), $actualMembers);
        $this->assertNotContains($this->leadXYZ->getId(), $actualMembers);

        $this->assertSame($actualMembers, $expectedMembers);
    }

    private function createCustomItem(CustomObject $customObject, string $publisher, string $name): CustomItem
    {
        $customItem       = new CustomItem($customObject);
        $customFieldValue = new CustomFieldValueText($this->customField, $customItem, $publisher);
        $customItem->addCustomFieldValue($customFieldValue);
        $customItem->setName($name);
        $customItem->setIsPublished(true);
        $this->em->persist($customItem);
        $this->em->persist($customFieldValue);
        $this->em->flush();

        return $customItem;
    }

    /**
     * @return array<mixed>
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function createBookObjectWithPublisherCustomField(): array
    {
        $customField = new CustomField();
        $customField->setLabel('Publisher');
        $customField->setAlias('publisher');
        $customField->setType('text');
        $customField->setTypeObject(new TextType(
            $this->createMock(TranslatorInterface::class),
            $this->createMock(FilterOperatorProviderInterface::class)
        ));

        $customObject = new CustomObject();
        $customObject->setAlias('book');
        $customObject->setNameSingular('Book');
        $customObject->setNamePlural('Books');
        $customObject->setIsPublished(true);
        $customObject->addCustomField($customField);

        $this->em->persist($customObject);
        $this->em->flush();

        $customField->setCustomObject($customObject);
        $customField->setIsPublished(true);
        $this->em->persist($customField);
        $this->em->flush();

        return [$customObject, $customField];
    }

    /**
     * @param array<mixed> $filters
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function createAndUpdateSegment(array $filters): int
    {
        $segment = new LeadList();
        $segment->setName('Segment A');
        $segment->setAlias('segment-a');
        $segment->setFilters($filters);
        $segment->setIsPublished(true);
        $this->em->persist($segment);
        $this->em->flush($segment);

        $this->assertCount(1, $this->em->getRepository(LeadList::class)->findAll());

        $segmentId = $segment->getId();

        $commandTester = $this->testSymfonyCommand(UpdateLeadListsCommand::NAME, ['-i' => $segmentId]);
        $this->assertSame(0, $commandTester->getStatusCode(), 'Update lead lists command was not successful');

        return $segmentId;
    }
}
