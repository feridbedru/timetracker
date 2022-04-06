<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Command;

use App\Command\ResetTestCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @covers \App\Command\ResetTestCommand
 * @group integration
 */
class ResetTestCommandTest extends KernelTestCase
{
    public function testCommandName()
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->add(new ResetTestCommand('test', $this->createMock(EntityManagerInterface::class)));

        $command = $application->find('kimai:reset-test');
        self::assertInstanceOf(ResetTestCommand::class, $command);
    }

    public function testCommandNameIsNotEnabledInProd()
    {
        $command = new ResetTestCommand('prod', $this->createMock(EntityManagerInterface::class));
        self::assertFalse($command->isEnabled());
    }
}
