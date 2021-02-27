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
class DistrictOrderRepository extends EntityRepository
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
        dd($locations);
        $data = array();
        foreach ($result as $row){
            $data[$row['distributionId']] = $row;
        }
        return $data;

    }

    public function findDistrictSearch($data)
    {
        $year = isset($data['year']) ? $data['year']:'';
        $month = isset($data['month']) ? $data['month']:'';
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->join('e.product','p');
        $qb->select('e.month as month','e.year as year');
        $qb->addSelect('d.id as districtId','d.name as districtName');
        $qb->addSelect('p.id as productId','p.name as productName');
        $qb->groupBy('d.id','e.month','e.year');
//        $qb->where('e.year =:year')->setParameter('year',$year);
//        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->orderBy('d.name','ASC');
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }

    public function findDistrictOrderOty($data)
    {
        $year = isset($data['year']) ? $data['year']:'';
        $month = isset($data['month']) ? $data['month']:'';
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->join('e.product','p');
        $qb->select('e.month as month','e.year as year');
        $qb->addSelect('d.id as districtId');
        $qb->addSelect('p.id as productId');
        $qb->addSelect('SUM(e.quantity) as quantity');
        $qb->groupBy('d.id','p.id','e.month','e.year');
//        $qb->where('e.year =:year')->setParameter('year',$year);
//        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->orderBy('d.name','ASC');
        $results = $qb->getQuery()->getArrayResult();

        $arrayReturn=[];

        foreach ($results as $result){
            $arrayReturn[$result['year']][$result['month']][$result['districtId']][$result['productId']]=$result;
        }

        return $arrayReturn;
    }


}
