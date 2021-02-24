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

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class AgentOrderRepository extends EntityRepository
{

    public function getLocationWiseTotalProductSales($locations)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.product','distribution');
        $qb->join('e.district','u');
        $qb->select('distribution.id as distributionId','SUM(e.quantity) as quantity');
        $qb->where('u.id IN (:districts)')->setParameter('districts',$locations);
        $qb->groupBy('distribution.id');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['distributionId']] = $row;
        }
        return $data;

    }

    public function getDistrictWiseTotalProductSales($month, $year)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.product','p');
        $qb->join('e.district','d');
        $qb->select('e.year as oYear','e.month as oMonth', 'SUM(e.quantity) as totalQty');
        $qb->addSelect('p.id as pId');
        $qb->addSelect('d.id as dId');
        $qb->where('e.month =:month')->setParameter('month',$month);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->groupBy('d.id','p.id');
        $result = $qb->getQuery()->getArrayResult();
        
        return $result;

    }


}
