<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Configuration\LanguageFormattings;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\InvoiceDocument;
use App\Entity\InvoiceTemplate;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Invoice\Calculator\DefaultCalculator;
use App\Invoice\InvoiceItemRepositoryInterface;
use App\Invoice\NumberGenerator\DateNumberGenerator;
use App\Invoice\Renderer\TwigRenderer;
use App\Invoice\ServiceInvoice;
use App\Repository\InvoiceDocumentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\Query\InvoiceQuery;
use App\Tests\Mocks\InvoiceModelFactoryFactory;
use App\Utils\FileHelper;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

/**
 * @covers \App\Invoice\ServiceInvoice
 */
class ServiceInvoiceTest extends TestCase
{
    private function getSut(array $paths): ServiceInvoice
    {
        $languages = [
            'en' => [
                'date' => 'Y.m.d',
                'duration' => '%h:%m h',
                'time' => 'H:i',
            ]
        ];

        $formattings = new LanguageFormattings($languages);

        $repo = new InvoiceDocumentRepository($paths);
        $invoiceRepo = $this->createMock(InvoiceRepository::class);

        return new ServiceInvoice($repo, new FileHelper(realpath(__DIR__ . '/../../var/data/')), $invoiceRepo, $formattings, (new InvoiceModelFactoryFactory($this))->create());
    }

    public function testInvalidExceptionOnChangeState()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown invoice status');
        $sut = $this->getSut([]);
        $sut->changeInvoiceStatus(new Invoice(), 'foo');
    }

    public function testEmptyObject()
    {
        $sut = $this->getSut([]);

        $this->assertEmpty($sut->getCalculator());
        $this->assertIsArray($sut->getCalculator());
        $this->assertEmpty($sut->getRenderer());
        $this->assertIsArray($sut->getRenderer());
        $this->assertEmpty($sut->getNumberGenerator());
        $this->assertIsArray($sut->getNumberGenerator());
        $this->assertEmpty($sut->getDocuments());
        $this->assertIsArray($sut->getDocuments());

        $this->assertNull($sut->getCalculatorByName('default'));
        $this->assertNull($sut->getDocumentByName('default'));
        $this->assertNull($sut->getNumberGeneratorByName('default'));
    }

    public function testWithDocumentDirectory()
    {
        $sut = $this->getSut(['templates/invoice/renderer/']);

        $actual = $sut->getDocuments();
        $this->assertNotEmpty($actual);
        foreach ($actual as $document) {
            $this->assertInstanceOf(InvoiceDocument::class, $document);
        }

        $actual = $sut->getDocumentByName('default');
        $this->assertInstanceOf(InvoiceDocument::class, $actual);
    }

    public function testAdd()
    {
        $sut = $this->getSut([]);

        $sut->addCalculator(new DefaultCalculator());
        $sut->addNumberGenerator($this->getNumberGeneratorSut());
        $twig = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();
        $sut->addRenderer(new TwigRenderer($twig));

        $this->assertEquals(1, \count($sut->getCalculator()));
        $this->assertInstanceOf(DefaultCalculator::class, $sut->getCalculatorByName('default'));

        $this->assertEquals(1, \count($sut->getNumberGenerator()));
        $this->assertInstanceOf(DateNumberGenerator::class, $sut->getNumberGeneratorByName('date'));

        $this->assertEquals(1, \count($sut->getRenderer()));
    }

    public function testCreateModelThrowsOnMissingTemplate()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot create invoice model without template');

        $query = new InvoiceQuery();
        $query->setCustomers([new Customer()]);

        $sut = $this->getSut([]);
        $sut->createModel($query);
    }

    /**
     * @group legacy
     */
    public function testCreateModelSetsFallbackLanguage()
    {
        $template = new InvoiceTemplate();
        $template->setNumberGenerator('date');
        self::assertNull($template->getLanguage());

        $query = new InvoiceQuery();
        $query->setCustomers([new Customer()]);
        $query->setTemplate($template);

        $sut = $this->getSut([]);
        $sut->addCalculator(new DefaultCalculator());
        $sut->addNumberGenerator($this->getNumberGeneratorSut());

        $model = $sut->createModel($query);

        self::assertEquals('en', $model->getTemplate()->getLanguage());
    }

    /**
     * @group legacy
     */
    public function testFindInvoiceItemsWithoutCustomer()
    {
        $sut = $this->getSut([]);

        $query = new InvoiceQuery();

        $items = $sut->findInvoiceItems($query);
        self::assertEquals([], $items);
    }

    /**
     * @group legacy
     */
    public function testFindInvoiceItemsWithCustomer()
    {
        $sut = $this->getSut([]);

        $query = new InvoiceQuery();
        $query->setCustomers([new Customer(), new Customer()]);

        $items = $sut->findInvoiceItems($query);
        self::assertEquals([], $items);
    }

    public function testCreateModelUsesTemplateLanguage()
    {
        $template = new InvoiceTemplate();
        $template->setNumberGenerator('date');
        $template->setLanguage('de');

        self::assertEquals('de', $template->getLanguage());

        $query = new InvoiceQuery();
        $query->setCustomers([new Customer()]);
        $query->setTemplate($template);

        $sut = $this->getSut([]);
        $sut->addCalculator(new DefaultCalculator());
        $sut->addNumberGenerator($this->getNumberGeneratorSut());

        $model = $sut->createModel($query);

        self::assertEquals('de', $model->getTemplate()->getLanguage());
    }

    public function testBeginAndEndDateFallback()
    {
        $timezone = new \DateTimeZone('Europe/Vienna');
        $customer = new Customer();
        $project = new Project();
        $project->setCustomer($customer);

        $timesheet1 = new Timesheet();
        $timesheet1->setProject($project);
        $timesheet1->setBegin(new \DateTime('2011-01-27 12:12:12', $timezone));
        $timesheet1->setEnd(new \DateTime('2020-01-27 12:12:12', $timezone));

        $timesheet2 = new Timesheet();
        $timesheet2->setProject($project);
        $timesheet2->setBegin(new \DateTime('2010-01-27 08:24:33', $timezone));
        $timesheet2->setEnd(new \DateTime('2019-01-27 12:12:12', $timezone));

        $timesheet3 = new Timesheet();
        $timesheet3->setProject($project);
        $timesheet3->setBegin(new \DateTime('2019-01-27 12:12:12', $timezone));
        $timesheet3->setEnd(new \DateTime('2020-01-07 12:12:12', $timezone));

        $timesheet4 = new Timesheet();
        $timesheet4->setProject($project);
        $timesheet4->setBegin(new \DateTime('2020-01-27 10:12:12', $timezone));
        $timesheet4->setEnd(new \DateTime('2020-11-27 11:12:12', $timezone));

        $timesheet5 = new Timesheet();
        $timesheet5->setProject($project);
        $timesheet5->setBegin(new \DateTime('2012-01-27 12:12:12', $timezone));
        $timesheet5->setEnd(new \DateTime('2018-01-27 12:12:12', $timezone));

        $repo = $this->createMock(InvoiceItemRepositoryInterface::class);
        $repo->method('getInvoiceItemsForQuery')->willReturn([
            $timesheet1,
            $timesheet2,
            $timesheet3,
            $timesheet4,
            $timesheet5,
        ]);

        $template = new InvoiceTemplate();
        $template->setNumberGenerator('date');
        $template->setLanguage('de');

        self::assertEquals('de', $template->getLanguage());

        $query = new InvoiceQuery();
        $query->setCustomers([new Customer(), $customer]);
        $query->setTemplate($template);
        self::assertNull($query->getBegin());
        self::assertNull($query->getEnd());

        $sut = $this->getSut([]);
        $sut->addCalculator(new DefaultCalculator());
        $sut->addNumberGenerator($this->getNumberGeneratorSut());

        $sut->addInvoiceItemRepository($repo);

        $sut->createModels($query);

        self::assertNotNull($query->getBegin());
        self::assertNotNull($query->getEnd());

        self::assertEquals('2010-01-27T00:00:00+0100', $query->getBegin()->format(DATE_ISO8601));
        self::assertEquals('2020-11-27T23:59:59+0100', $query->getEnd()->format(DATE_ISO8601));
    }

    private function getNumberGeneratorSut()
    {
        $repository = $this->createMock(InvoiceRepository::class);
        $repository
            ->expects($this->any())
            ->method('hasInvoice')
            ->willReturn(false);

        return new DateNumberGenerator($repository);
    }
}