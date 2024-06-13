<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Functional;

use DateTime;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\CustomObjectsBundle\Entity\CustomItem;
use MauticPlugin\CustomObjectsBundle\Model\CustomFieldValueModel;
use MauticPlugin\CustomObjectsBundle\Model\CustomItemModel;
use MauticPlugin\CustomObjectsBundle\Tests\Functional\DataFixtures\Traits\CustomObjectsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PreviewFunctionalTest extends MauticMysqlTestCase
{
    use CustomObjectsTrait;

    /**
     * @var CustomItemModel
     */
    private $customItemModel;

    /**
     * @var CustomFieldValueModel
     */
    private $customFieldValueModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customItemModel       = self::$container->get('mautic.custom.model.item');
        $this->customFieldValueModel = self::$container->get('mautic.custom.model.field.value');
    }

    public function testPreviewPageCODynamicContent(): void
    {
        $customObject = $this->createCustomObjectWithAllFields(self::$container, 'Car');
        $customItem   = new CustomItem($customObject);

        $customItem->setName('Nexon');
        $this->customFieldValueModel->createValuesForItem($customItem);

        $textValue       = $customItem->findCustomFieldValueForFieldAlias('text-test-field');
        $textValue->setValue('abracadabra');

        $customItem = $this->customItemModel->save($customItem);

        $lead  = $this->createLead();
        $email = $this->createEmail();
        $email->setDynamicContent(
            [
                [
                    'tokenName' => 'Dynamic Content 1',
                    'content'   => 'Default Dynamic Content',
                    'filters'   => [
                        [
                            'content' => null,
                            'filters' => [
                            ],
                        ],
                    ],
                ],
                [
                    'tokenName' => 'Dynamic Content 2',
                    'content'   => 'Default Dynamic Content',
                    'filters'   => [
                        [
                            'content' => 'Nexon Dynamic Content',
                            'filters' => [
                                [
                                    'glue'     => 'and',
                                    'field'    => 'cmf_'.$textValue->getCustomField()->getId(),
                                    'object'   => 'custom_object',
                                    'type'     => 'text',
                                    'filter'   => 'abracadabra',
                                    'display'  => $customObject->getAlias().':text-test-field',
                                    'operator' => '=',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
        $this->em->persist($email);
        $this->em->flush();

        $this->customItemModel->linkEntity($customItem, 'contact', (int) $lead->getId());

        $url                    = "/email/preview/{$email->getId()}";
        $urlWithContact         = "{$url}?contactId={$lead->getId()}";
        $contentNoContactInfo   = 'Default Dynamic Content';
        $contentWithContactInfo = 'Nexon Dynamic Content';

        // Anonymous visitor
        $this->assertPageContent($url, $contentNoContactInfo);
        $this->assertPageContent($urlWithContact, $contentNoContactInfo);

        $this->loginUser('admin');

        // Admin user
        $this->assertPageContent($url, $contentNoContactInfo);
        $this->assertPageContent($urlWithContact, $contentWithContactInfo);
    }

    private function assertPageContent(string $url, string ...$expectedContents): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, $url);
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        foreach ($expectedContents as $expectedContent) {
            self::assertStringContainsString($expectedContent, $crawler->text());
        }
    }

    private function createEmail(bool $publicPreview = true): Email
    {
        $email = new Email();
        $email->setDateAdded(new DateTime());
        $email->setName('Email name');
        $email->setSubject('Email subject');
        $email->setTemplate('Blank');
        $email->setPublicPreview($publicPreview);
        $email->setCustomHtml('<html><body>{dynamiccontent="Dynamic Content 2"}</body></html>');
        $this->em->persist($email);

        return $email;
    }

    private function createLead(): Lead
    {
        $lead = new Lead();
        $lead->setEmail('test@domain.tld');
        $this->em->persist($lead);

        return $lead;
    }
}
