<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Export\Spreadsheet;

use App\Entity\Customer;
use App\Entity\Project;
use App\Export\Spreadsheet\AnnotatedObjectExporter;
use App\Export\Spreadsheet\Extractor\AnnotationExtractor;
use App\Export\Spreadsheet\SpreadsheetExporter;
use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @covers \App\Export\Spreadsheet\AnnotatedObjectExporter
 */
class AnnotatedObjectExporterTest extends TestCase
{
    public function testExport()
    {
        $spreadsheetExporter = new SpreadsheetExporter($this->createMock(TranslatorInterface::class));
        $annotationExtractor = new AnnotationExtractor(new AnnotationReader());

        $project = new Project();
        $project->setName('test project');
        $project->setCustomer((new Customer())->setName('A customer'));
        $project->setComment('Lorem Ipsum');
        $project->setOrderNumber('1234567890');
        $project->setBudget(123456.7890);
        $project->setTimeBudget(1234567890);
        $project->setBudgetType();
        $project->setIsMonthlyBudget();
        $project->setColor('#ababab');
        $project->setVisible(false);

        $sut = new AnnotatedObjectExporter($spreadsheetExporter, $annotationExtractor);
        $spreadsheet = $sut->export(Project::class, [$project]);
        $worksheet = $spreadsheet->getActiveSheet();

        $i = 0;
        self::assertNull($worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals('test project', $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals('A customer', $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals(1234567890, $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals('', $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals('', $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals('', $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals(123456.7890, $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals('=1234567890/86400', $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals('month', $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals('#ababab', $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertFalse($worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
        self::assertEquals('Lorem Ipsum', $worksheet->getCellByColumnAndRow(++$i, 2)->getValue());
    }
}