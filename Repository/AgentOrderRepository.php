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

    public function findWithAgentSearch($data)
    {
        $year = isset($data['year']) ? $data['year']:'';
        $month = isset($data['month']) ? $data['month']:'';
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent','agent');
        $qb->leftJoin('e.district','d');
        $qb->select('agent.id as customerId','agent.agentId as agentId','agent.name as agentName');
        $qb->addSelect('d.id as districtId','d.name as districtName');
        $qb->addSelect('e.month as month','e.year as year');
        $qb->groupBy('agent.id','e.month','e.year');
        $qb->where('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->orderBy('agent.name','ASC');
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }

    public function findSalesItems($entities, $data)
    {
        $ids = array();
        foreach ($entities as $row){
            $ids[] = $row['customerId'];
        }
        //dd($ids);
        $year = isset($data['year']) ? $data['year']:'';
        $month = isset($data['month']) ? $data['month']:'';
        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.product','distribution');
        $qb->leftJoin('e.agent','agent');
        $qb->select('distribution.id as distributionId','distribution.name as distributionName','e.amount as amount','e.quantity as quantity');
        $qb->addSelect('agent.id as agentId','agent.name as agentName');
        $qb->where('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->andWhere('agent.id IN (:ids)')->setParameter('ids', $ids);
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $salesId = "{$row['agentId']}-{$row['distributionId']}";
            $data[$salesId] = $row['quantity'];
        }
      //  dd($data);
        return $data;
    }

    public function getLocationWiseTotalProductSales($locations)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.product','distribution');
        $qb->join('e.district','d');
        $qb->select('distribution.id as distributionId','SUM(e.quantity) as quantity');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->groupBy('distribution.id');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['distributionId']] = $row;
        }
        return $data;

    }



    public function getCompletedAmount($breedType)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('e.quantity AS completedAmount');
        $qb->select('product.name AS breedType');
        $qb->where('product.name = :breedType')->setParameter('breedType', $breedType);
        $qb->leftJoin('e.product', 'product');
        $results = $qb->getQuery()->getArrayResult();

        return $results;

    }


}
