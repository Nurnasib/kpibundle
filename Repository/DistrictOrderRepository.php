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
        $qb->where('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        if (isset($data['district'])){
            $qb->andWhere('d.id = :district')->setParameter('district', $data['district']);
        }
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
        $qb->where('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->orderBy('d.name','ASC');
        $results = $qb->getQuery()->getArrayResult();

        $arrayReturn=[];

        foreach ($results as $result){
            $arrayReturn[$result['year']][$result['month']][$result['districtId']][$result['productId']]=$result;
        }

        return $arrayReturn;
    }

    public function getGrouthPreviousProductQty($district, $product, $year, $month)
    {
        $previousYear = $year-1;
        $startDate = date('Y-m-01', strtotime("{$previousYear}-01-01"));
        $endDate = date('Y-m-t', strtotime("{$previousYear}-{$month}-01"));

        $qb = $this->createQueryBuilder('e');

        $qb->select('SUM(e.quantity) AS quantity');

        $qb->where('e.district = :district');
        $qb->andWhere('e.product = :product');
        $qb->andWhere('e.created >= :startDate');
        $qb->andWhere('e.created <= :endDate');
        $qb->setParameters(array('district'=>$district,'product'=>$product,'startDate'=>$startDate, 'endDate'=>$endDate));

        try {
            return $qb->getQuery()->getSingleScalarResult();
        }catch (\Exception $e){
            return 0;
        }
    }

    public function getGrouthCurrentProductQty($district, $product, $year, $months)
    {
//        $startDate = date('Y-m-01', strtotime("{$year}-01-01"));
//        $endDate = date('Y-m-t', strtotime("{$year}-{$month}-01"));

        $qb = $this->createQueryBuilder('e');

        $qb->select('SUM(e.quantity) AS quantity');

        $qb->where('e.district = :district')->setParameter('district', $district);
        $qb->andWhere('e.product = :product')->setParameter('product', $product);
        $qb->andWhere('e.month IN (:months)')->setParameter('months', $months);
        $qb->andWhere('e.year = :year')->setParameter('year', $year);

        $qb->groupBy('e.year');
        $qb->groupBy('e.product');
        $qb->groupBy('e.district');

        try {
            return $qb->getQuery()->getSingleScalarResult();
        }catch (\Exception $e){
            return 0;
        }
    }

    public function getCumulativeTargetQty($district, $product, $year, $months)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->select('SUM(e.targetQuantity) AS cumulativeTargetQuantity');

        $qb->where('e.district = :district')->setParameter('district', $district);
        $qb->andWhere('e.product = :product')->setParameter('product', $product);
        $qb->andWhere('e.year = :year')->setParameter('year', $year);
        $qb->andWhere('e.month IN (:months)')->setParameter('months', $months);

        $qb->groupBy('e.year');
        $qb->groupBy('e.product');
        $qb->groupBy('e.district');

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getCumulativeQty($district, $product, $year, $months)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->select('SUM(e.quantity) AS cumulativeQuantity');

        $qb->where('e.district = :district')->setParameter('district', $district);
        $qb->andWhere('e.product = :product')->setParameter('product', $product);
        $qb->andWhere('e.year = :year')->setParameter('year', $year);
        $qb->andWhere('e.month IN (:months)')->setParameter('months', $months);

        $qb->groupBy('e.year');
        $qb->groupBy('e.product');
        $qb->groupBy('e.district');

        return $qb->getQuery()->getSingleScalarResult();
    }


    public function getLocationWiseTotalProductSalesTarget($locations, EmployeeBoard $board)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.product','product');
        $qb->join('e.district','d');
        $qb->select('product.id as id','SUM(e.quantity) as quantity','SUM(e.targetQuantity) as targetQuantity','SUM(e.salesMark) as salesMark');
        $qb->addSelect('SUM(e.salesMarkPercentage) as salesMarkPercentage','SUM(e.salesGrouthPreviousQuantity) as salesGrouthPreviousQuantity','SUM(e.salesGrouthCurrentQuantity) as salesGrouthCurrentQuantity', 'SUM(e.cumulativeQuantity) AS cumulativeQuantity', 'SUM(e.cumulativeTargetQuantity) AS cumulativeTargetQuantity');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$board->getYear());
        $qb->andWhere('e.month = :month')->setParameter('month',$board->getMonth());
        $qb->groupBy('product.id');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['id']] = $row;
        }
        return $data;

    }

    public function getSalesTargetAndAchievementForMonthlyReport($locations, $filterBy, $month, $prviousYear=false)
    {
        $year = $prviousYear==true?$filterBy['year']-1:$filterBy['year'];

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.product','product');
        $qb->join('e.district','d');
        $qb->select('product.id as id','SUM(e.quantity) as totalSalesQuantity','SUM(e.targetQuantity) as totalTargetQuantity');
        $qb->addSelect('product.name as productName', 'e.month as monthName', 'e.year');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);

//        $qb->andWhere('e.month IN (:months)')->setParameter('months', $filterBy['months']);
        $qb->andWhere('e.month =:month')->setParameter('month', $month);


        $qb->andWhere('e.year = :year')->setParameter('year', $year);
        $qb->groupBy('product.id');
        $qb->addGroupBy('e.month');
        $qb->addGroupBy('e.year');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['productName']][$row['monthName']] = $row;
        }
        return $data;

    }

    public function getSalesTargetAndAchievementForSalePercentageReport($locations, $year, $month, $prviousYear)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.product','product');
        $qb->join('e.district','d');
        $qb->select('product.id as id','SUM(e.quantity) as totalSalesQuantity','SUM(e.targetQuantity) as totalTargetQuantity');
        $qb->addSelect('product.name as productName', 'e.month as monthName', 'e.year');

        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.month =:month')->setParameter('month', $month);

        $years = $prviousYear==true?[(string)($year-1), (string)$year]:[$year];

        $qb->andWhere('e.year IN (:year)')->setParameter('year', $years);

        $qb->groupBy('product.id');
        $qb->addGroupBy('e.month');
        $qb->addGroupBy('e.year');
        $results = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($results as $row){
            $data[$row['monthName']][$row['year']][$row['productName']] = $row;
        }
        return $data;

    }

}
