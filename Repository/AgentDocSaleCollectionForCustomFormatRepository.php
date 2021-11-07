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

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class AgentDocSaleCollectionForCustomFormatRepository extends EntityRepository
{
    public function getTotalDocSale(EmployeeBoard $board)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employeeBoard', 'employeeBoard');
        $qb->select('SUM(e.sales) AS totalSales', 'SUM(e.collection) AS totalCollection');
        $qb->where('employeeBoard.id =:boardId')->setParameter('boardId', $board->getId());

        return $qb->getQuery()->getOneOrNullResult();
    }
}
