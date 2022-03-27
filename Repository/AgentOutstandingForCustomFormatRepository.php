<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Terminalbd\KpiBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\MarkChart;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class AgentOutstandingForCustomFormatRepository extends EntityRepository
{
    public function getOutstanding(EmployeeBoard $board)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->select('e.actualAmount', 'e.limitAmount', 'e.outstanding');
        $qb->where('e.employeeBoard = :board')->setParameter('board', $board);

        $data = $qb->getQuery()->getSingleResult();

        $outstandingDistribution = $this->_em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'outstanding-limit-vs-actual-feed'));
        $employeeBoardAttributeForOutStandingLimit = $this->_em->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $outstandingDistribution]);
        $data['mark'] = $employeeBoardAttributeForOutStandingLimit ? (int)$employeeBoardAttributeForOutStandingLimit->getMark() : 0;
        return $data;
    }
}
