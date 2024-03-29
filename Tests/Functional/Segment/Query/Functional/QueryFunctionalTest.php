<?php

declare(strict_types =1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Functional\Segment\Query\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Provider\FilterOperatorProviderInterface;
use MauticPlugin\CustomObjectsBundle\CustomFieldType\CustomFieldTypeInterface;
use MauticPlugin\CustomObjectsBundle\CustomFieldType\TextType;
use MauticPlugin\CustomObjectsBundle\Entity\CustomField;
use MauticPlugin\CustomObjectsBundle\Entity\CustomItem;
use MauticPlugin\CustomObjectsBundle\Entity\CustomObject;
use MauticPlugin\CustomObjectsBundle\Tests\Unit\CustomObjectTestCase;
use Symfony\Component\Translation\TranslatorInterface;

class QueryFunctionalTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        $customItemModel       = self::$container->get('mautic.custom.model.item');
        $customObject = $this->createBookObjectWithPublisherCustomField();

        $publishers = ['ABC', 'XYZ', ''];

        $books = [$bookWithPublisherABC, $bookWithPublisherXYZ, $bookWithPublisherEmpty] = array_map(
            fn ($publisher) =>
            $this->createCustomItem($customObject, ['publisher' => $publisher], 'publisher'.$publisher),
            $publishers
        );

        array_map(fn ($book) => $customItemModel->save($book), $books);

        $this->em->flush();

        $leadABC = new Lead();
        $leadABC->setFirstname('LeadABC')->setEmail('leadABC@acquia.com');
        $this->em->persist($leadABC);

        $leadXYZ = new Lead();
        $leadABC->setFirstname('LeadXYZ')->setEmail('leadXYZ@acquia.com');
        $this->em->persist($leadXYZ);

        $leadEmpty = new Lead();
        $leadEmpty->setFirstname('LeadEmpty')->setEmail('leadEmpty@acquia.com');
        $this->em->persist($leadEmpty);

        $this->em->flush();

        $customItemModel->linkEntity($bookWithPublisherABC, $leadABC);
        $customItemModel->linkEntity($bookWithPublisherXYZ, $leadXYZ);
        $customItemModel->linkEntity($bookWithPublisherEmpty, $leadEmpty);

    }

    public function testNotEqualsOperatorSegmentOnCustomField()
    {
        $filters = [[
            'glue'       => 'and',
            'field'      => '',
            'object'     => 'custom_object',
            'type'       => 'text',
            'operator'   => 'neq'
        ]];
        $segment = new LeadList();
        $segment->setName('Segment A');
        $segment->setAlias('segment-a');
        $segment->setFilters($filters);
        $this->em->persist($segment);

    }

    private function createCustomItem(CustomObject $customObject, array $data, string $name): CustomItem
    {
        $customItem = new CustomItem($customObject);
        $customItem->setCustomFieldValues($data);
        $customItem->setName($name);

        return $customItem;
    }

    /**
     * @return CustomObject
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    private function createBookObjectWithPublisherCustomField(): CustomObject
    {
        $customObject = new CustomObject();
        $customObject->setAlias('book');
        $customObject->setNameSingular('Book');
        $customObject->setNamePlural('Books');
        $this->em->persist($customObject);

        $customField = new CustomField();
        $customField->setLabel('Publisher');
        $customField->setAlias('publisher');
        $customField->setType('text');
        $customField->setTypeObject(new TextType(
            $this->createMock(TranslatorInterface::class),
            $this->createMock(FilterOperatorProviderInterface::class)
        ));
        $this->em->persist($customField);

        $customField->setCustomObject($customObject);
        $this->em->persist($customField);
        return $customObject;
    }
}
