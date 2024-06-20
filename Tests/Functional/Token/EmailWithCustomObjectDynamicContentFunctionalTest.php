<?php

declare(strict_types=1);

namespace MauticPlugin\CustomObjectsBundle\Tests\Functional\Token;

use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\CustomObjectsBundle\Entity\CustomItem;
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
     * @var EmailModel
     */
    private $emailModel;

    /**
     * @var EntityRepository|ObjectRepository
     */
    private $emailStatRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customItemModel       = self::$container->get('mautic.custom.model.item');
        $this->customFieldValueModel = self::$container->get('mautic.custom.model.field.value');
        $this->emailModel            = self::$container->get('mautic.email.model.email');
        $this->emailStatRepository   = $this->em->getRepository(Stat::class);
    }

    public function testEmailWithCustomObjectDynamicContent(): void
    {
        $customObject = $this->createCustomObjectWithAllFields(self::$container, 'Car');
        $customItem   = new CustomItem($customObject);

        $customItem->setName('Nexon');
        $this->customFieldValueModel->createValuesForItem($customItem);

        $textValue       = $customItem->findCustomFieldValueForFieldAlias('text-test-field');
        $textValue->setValue('Tata');

        $customItem = $this->customItemModel->save($customItem);

        $lead1  = $this->createLead('nexon@tata.com');
        $lead2  = $this->createLead('noitem@tata.com');
        $email  = $this->createEmail();
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
                                    'filter'   => 'Tata',
                                    'display'  => $customObject->getAlias().': text-test-field',
                                    'operator' => '=',
                                ],
                                [
                                    'glue'     => 'and',
                                    'field'    => 'cmo_'.$customObject->getId(),
                                    'object'   => 'custom_object',
                                    'type'     => 'text',
                                    'filter'   => 'Nexon',
                                    'display'  => $customObject->getAlias(),
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

        $this->customItemModel->linkEntity($customItem, 'contact', (int) $lead1->getId());

        $this->sendAndAssetText($email, $lead1, 'Nexon Dynamic Content');
        $this->sendAndAssetText($email, $lead2, 'Default Dynamic Content');
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
        $this->emailModel->sendEmail(
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

        /** @var Stat|null $emailStat */
        $emailStat = $this->emailStatRepository->findOneBy(
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
