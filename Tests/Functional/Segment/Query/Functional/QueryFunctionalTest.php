<?php

declare(strict_types =1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Functional\Segment\Query\Functional;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Command\UpdateLeadListsCommand;
use Mautic\LeadBundle\DataFixtures\ORM\LoadCategoryData;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Provider\FilterOperatorProviderInterface;
use MauticPlugin\CustomObjectsBundle\CustomFieldType\CustomFieldTypeInterface;
use MauticPlugin\CustomObjectsBundle\CustomFieldType\TextType;
use MauticPlugin\CustomObjectsBundle\Entity\CustomField;
use MauticPlugin\CustomObjectsBundle\Entity\CustomItem;
use MauticPlugin\CustomObjectsBundle\Entity\CustomItemXrefContact;
use MauticPlugin\CustomObjectsBundle\Entity\CustomObject;
use MauticPlugin\CustomObjectsBundle\Model\CustomItemModel;
use MauticPlugin\CustomObjectsBundle\Provider\ConfigProvider;
use MauticPlugin\CustomObjectsBundle\Tests\Unit\CustomObjectTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;
use function PHPUnit\Framework\assertCount;

class QueryFunctionalTest extends MauticMysqlTestCase
{

    private CustomObject $customObject;
    protected function setUp(): void
    {
        parent::setUp();

        $this->configParams[ConfigProvider::CONFIG_PARAM_ITEM_VALUE_TO_CONTACT_RELATION_LIMIT] = 0;

        $customItemModel       = self::$container->get('mautic.custom.model.item');
        $this->assertInstanceOf(CustomItemModel::class, $customItemModel);

        [$this->customObject, $this->customField] = $this->createBookObjectWithPublisherCustomField();

        $values =  ['bookWithPublisherABC' => 'ABC', 'bookWithPublisherXYZ' => 'XYZ', 'bookWithPublisherEmpty' => ''];

        foreach ($values as $name => $publisher) {
            ${$name} = $this->createCustomItem($this->customObject, ['publisher' => $publisher], 'publisher'.$publisher);
            $this->em->persist(${$name});
            $this->em->flush();
        }

        $leadABC = new Lead();
        $leadABC->setFirstname('LeadABC')->setEmail('leadABC@acquia.com')->setIsPublished(true);
        $this->em->persist($leadABC);

        $leadXYZ = new Lead();
        $leadXYZ->setFirstname('LeadXYZ')->setEmail('leadXYZ@acquia.com')->setIsPublished(true);
        $this->em->persist($leadXYZ);

        $leadEmpty = new Lead();
        $leadEmpty->setFirstname('LeadEmpty')->setEmail('leadEmpty@acquia.com')->setIsPublished(true);
        $this->em->persist($leadEmpty);

        $this->em->flush();

        $xrefABC = $customItemModel->linkEntity($bookWithPublisherABC, 'contact', (int) $leadABC->getId());
        $this->assertInstanceOf(CustomItemXrefContact::class, $xrefABC);

        $xrefXYZ = $customItemModel->linkEntity($bookWithPublisherXYZ, 'contact',(int) $leadXYZ->getId());
        $this->assertInstanceOf(CustomItemXrefContact::class, $xrefXYZ);

        $xrefEmpty = $customItemModel->linkEntity($bookWithPublisherEmpty, 'contact',(int) $leadEmpty->getId());
        $this->assertInstanceOf(CustomItemXrefContact::class, $xrefEmpty);

    }

    public function testNotEqualsOperatorSegmentOnCustomField()
    {
        $filters = [[
            'glue'       => 'and',
            'field'      => 'cmf_'.$this->customField->getId(),
            'object'     => 'custom_object',
            'type'       => 'text',
            'operator'   => '!=',
            'properties' => [
                'filter' => 'ABC'
            ],
        ]];

        $segment = new LeadList();
        $segment->setName('Segment A');
        $segment->setAlias('segment-a');
        $segment->setFilters($filters);
        $segment->setIsPublished(true);
        $this->em->persist($segment);
        $this->em->flush($segment);

        $segments = $this->em->getRepository(LeadList::class)->findAll();
        assertCount(1, $segments);
        $segmentId = $segments[0]->getId() ;

        //dd($segmentId);

        $this->assertCount(3, $this->em->getRepository(CustomItem::class)->findAll());
        $this->assertCount(3, $this->em->getRepository(Lead::class)->findAll());
        $this->assertCount(1, $this->em->getRepository(CustomField::class)->findAll());
        $this->assertCount(1, $this->em->getRepository(CustomObject::class)->findAll());
        $this->assertCount(3, $this->em->getRepository(CustomItemXrefContact::class)->findAll());

        $commandTester = $this->testSymfonyCommand(UpdateLeadListsCommand::NAME, ['-i' => $segmentId]);

        //dd($commandTester);

        $this->assertSame(0, $commandTester->getStatusCode(), 'Update lead lists command was not successful');

        //dd($segment->getLeads());

        $members = $this->em->getRepository(ListLead::class)->findAll();

//        dd($members);

        $this->assertCount(2, $members);


    }

    private function createCustomItem(CustomObject $customObject, array $data, string $name): CustomItem
    {
        $customItem = new CustomItem($customObject);
        $customItem->setCustomFieldValues($data);
        $customItem->setName($name);
        $customItem->setIsPublished(true);

        return $customItem;
    }

    /**
     * @return array<mixed>
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
}
