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
class EmployeeBoardSubAttributeRepository extends EntityRepository
{


    public function groupByAttributeMarks(EmployeeBoard $board)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.markDistribution','d');
        $qb->leftJoin('d.parent','p');
        $qb->select('p.id as parentId','SUM(e.mark) as mark');
        $qb->groupBy('parentId');
        $qb->where("e.employeeBoard = {$board->getId()}");
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }

    public function getKpiSummaryForFeedAndGrowth(EmployeeBoard $board)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('e.targetQuantity', 'e.salesQuantity', 'e.mark');
        $qb->addSelect('markDistribution.name AS attributeName', 'markDistribution.salesMode');

        $qb->join('e.markDistribution', 'markDistribution');
        $qb->where('e.employeeBoard = :id')->setParameter('id', $board);
//        $qb->andWhere("markDistribution.slug = 'doc-sales-vs-collection'");

        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            if ($result['targetQuantity'] > 0){
//                $percentage = ($result['salesQuantity']*100) / $result['targetQuantity'];

                if ($result['salesMode'] == 'growth'){
                    $percentage = (($result['salesQuantity'] - $result['targetQuantity'])*100) / $result['targetQuantity'];
                }else {
                    $percentage = ($result['salesQuantity']*100) / $result['targetQuantity'];
                }

            }
            $data[$result['salesMode']][] = [
                'markParameter' => $result['attributeName'],
                'targetQuantity' => $result['targetQuantity'],
                'salesQuantity' => $result['salesQuantity'],
                'percentage' => $percentage,
                'mark' => $result['mark'],
            ];
        }
        return $data;
    }
}
