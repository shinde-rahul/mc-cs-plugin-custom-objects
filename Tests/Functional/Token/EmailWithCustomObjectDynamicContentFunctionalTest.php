<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Functional\Token;

use DateTime;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Entity\StatRepository;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\CustomObjectsBundle\Entity\CustomFieldValueInterface;
use MauticPlugin\CustomObjectsBundle\Entity\CustomItem;
use MauticPlugin\CustomObjectsBundle\Entity\CustomObject;
use MauticPlugin\CustomObjectsBundle\Model\CustomFieldValueModel;
use MauticPlugin\CustomObjectsBundle\Model\CustomItemModel;
use MauticPlugin\CustomObjectsBundle\Tests\Functional\DataFixtures\Traits\CustomObjectsTrait;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

class EmailWithCustomObjectDynamicContentFunctionalTest extends MauticMysqlTestCase
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

    /**
     * @var CustomObject
     */
    private $customObject;

    /**
     * @var CustomItem
     */
    private $customItem;

    /**
     * @var CustomFieldValueInterface
     */
    private $textValue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customItemModel       = self::$container->get('mautic.custom.model.item');
        $this->customFieldValueModel = self::$container->get('mautic.custom.model.field.value');

        $this->customObject = $this->createCustomObjectWithAllFields(self::$container, 'Car');
        $this->customItem   = new CustomItem($this->customObject);

        $this->customItem->setName('Nexon');
        $this->customFieldValueModel->createValuesForItem($this->customItem);

        $this->textValue       = $this->customItem->findCustomFieldValueForFieldAlias('text-test-field');
        $this->textValue->setValue('Tata');

        $this->customItem = $this->customItemModel->save($this->customItem);
    }

    public function testDynamicContentEmail(): void
    {
        foreach ([
            [
                'nexonequal@acquia.com',
                $this->buildDynamicContentArray('Tata', '='),
                'Nexon Dynamic Content',
            ], [
                'nexonnotequal@acquia.com',
                $this->buildDynamicContentArray('Toyota', '!='),
                'Nexon Dynamic Content',
            ], [
                'nexonempty@acquia.com',
                $this->buildDynamicContentArray('', 'empty'),
                'Default Dynamic Content',
            ], [
                'nexonnotempty@acquia.com',
                $this->buildDynamicContentArray('', '!empty'),
                'Nexon Dynamic Content',
            ], [
                'nexonlike@acquia.com',
                $this->buildDynamicContentArray('at', 'like'),
                'Nexon Dynamic Content',
            ], [
                'nexonnotlike@acquia.com',
                $this->buildDynamicContentArray('Toyota', '!like'),
                'Nexon Dynamic Content',
            ], [
                'nexonstartsWith@acquia.com',
                $this->buildDynamicContentArray('Ta', 'startsWith'),
                'Nexon Dynamic Content',
            ], [
                'nexonendsWith@acquia.com',
                $this->buildDynamicContentArray('ta', 'endsWith'),
                'Nexon Dynamic Content',
            ], [
                 'nexonendsWith@acquia.com',
                 $this->buildDynamicContentArray('at', 'contains'),
                 'Nexon Dynamic Content',
             ],
        ] as $item) {
            $this->emailWithCustomObjectDynamicContent($item[0], $item[1], $item[2]);
        }
    }

    /**
     * @return array<mixed>
     */
    private function buildDynamicContentArray(string $filter, string $operator): array
    {
        return [
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
                                'field'    => 'cmf_'.$this->textValue->getCustomField()->getId(),
                                'object'   => 'custom_object',
                                'type'     => 'text',
                                'filter'   => $filter,
                                'display'  => $this->customObject->getAlias().': text-test-field',
                                'operator' => $operator,
                            ],
                            [
                                'glue'     => 'and',
                                'field'    => 'cmo_'.$this->customObject->getId(),
                                'object'   => 'custom_object',
                                'type'     => 'text',
                                'filter'   => 'Nexon',
                                'display'  => $this->customObject->getAlias(),
                                'operator' => '=',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $dynamicContent
     */
    private function emailWithCustomObjectDynamicContent(string $emailAddress, array $dynamicContent, string $assertText): void
    {
        $lead  = $this->createLead($emailAddress);
        $email = $this->createEmail();
        $email->setDynamicContent($dynamicContent);
        $this->em->persist($email);
        $this->em->flush();

        $this->customItemModel->linkEntity($this->customItem, 'contact', (int) $lead->getId());

        $this->sendAndAssetText($email, $lead, $assertText);
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

    private function createLead(string $email): Lead
    {
        $lead = new Lead();
        $lead->setEmail($email);
        $this->em->persist($lead);

        return $lead;
    }

    public function sendAndAssetText(Email $email, Lead $lead, string $matchText): void
    {
        /** @var EmailModel $emailModel */
        $emailModel = self::$container->get('mautic.email.model.email');
        $emailModel->sendEmail(
            $email,
            [
                [
                    'id'        => $lead->getId(),
                    'email'     => $lead->getEmail(),
                    'firstname' => $lead->getFirstname(),
                    'lastname'  => $lead->getLastname(),
                ],
            ]
        );

        /** @var StatRepository $emailStatRepository */
        $emailStatRepository = $this->em->getRepository(Stat::class);

        /** @var Stat|null $emailStat */
        $emailStat = $emailStatRepository->findOneBy(
            [
                'email' => $email->getId(),
                'lead'  => $lead->getId(),
            ]
        );

        Assert::assertNotNull($emailStat);

        $crawler = $this->client->request(Request::METHOD_GET, "/email/view/{$emailStat->getTrackingHash()}");

        $body = $crawler->filter('body');

        // Remove the tracking tags that are causing troubles with different Mautic configurations.
        $body->filter('a,img,div')->each(function (Crawler $crawler) {
            foreach ($crawler as $node) {
                $node->parentNode->removeChild($node);
            }
        });

        Assert::assertStringContainsString(
            $matchText,
            $body->html()
        );
    }
}
