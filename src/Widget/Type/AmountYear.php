<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Widget\Type;

use App\Configuration\SystemConfiguration;
use App\Repository\TimesheetRepository;

final class AmountYear extends CounterYear
{
    public function __construct(TimesheetRepository $repository, SystemConfiguration $systemConfiguration)
    {
        parent::__construct($repository, $systemConfiguration);
        $this->setId('amountYear');
        $this->setOption('dataType', 'money');
        $this->setOption('icon', 'money');
        $this->setOption('color', 'yellow');
        $this->setTitle('stats.amountYear');
    }

    public function getData(array $options = [])
    {
        $this->titleYear = 'stats.amountFinancialYear';
        $this->setQuery(TimesheetRepository::STATS_QUERY_RATE);
        $this->setQueryWithUser(false);

        return parent::getData($options);
    }
}
