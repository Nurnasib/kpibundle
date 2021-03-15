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

        $results = $qb->getQuery()->getSingleScalarResult();
        return $results;
    }

    public function getGrouthCurrentProductQty($district, $product, $year, $month)
    {
        $startDate = date('Y-m-01', strtotime("{$year}-01-01"));
        $endDate = date('Y-m-t', strtotime("{$year}-{$month}-01"));

        $qb = $this->createQueryBuilder('e');

        $qb->select('SUM(e.quantity) AS quantity');

        $qb->where('e.district = :district');
        $qb->andWhere('e.product = :product');
        $qb->andWhere('e.created >= :startDate');
        $qb->andWhere('e.created <= :endDate');
        $qb->setParameters(array('district'=>$district,'product'=>$product,'startDate'=>$startDate, 'endDate'=>$endDate));

        $results = $qb->getQuery()->getSingleScalarResult();

        return $results;
    }


    public function getLocationWiseTotalProductSalesTarget($locations, $year, $month)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.product','product');
        $qb->join('e.district','d');
        $qb->select('product.id as id','SUM(e.quantity) as quantity','SUM(e.targetQuantity) as targetQuantity','SUM(e.salesMark) as salesMark');
        $qb->addSelect('SUM(e.salesMarkPercentage) as salesMarkPercentage','SUM(e.salesGrouthPreviousQuantity) as salesGrouthPreviousQuantity','SUM(e.salesGrouthCurrentQuantity) as salesGrouthCurrentQuantity');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->groupBy('product.id');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['id']] = $row;
        }
        return $data;

    }

    public function getDistrictAchievement($locations, $year, $month)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->select('SUM(e.quantity) as achieveQuantity','SUM(e.targetQuantity) as targetQuantity');
        $qb->addSelect('d.name AS districtName');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->groupBy('d.id');
        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            $data[$result['districtName']] = [
                'achieveQuantity' => $result['achieveQuantity'],
                'targetQuantity' => $result['targetQuantity'],
                'percentage' => $result['targetQuantity']>0?($result['achieveQuantity']*100)/$result['targetQuantity']:0,
                'mark' => $this->salesDistrictAchivementCalculation($result['targetQuantity'], $result['achieveQuantity'])
            ];
        }
        return $data;

    }

    public function getRegionalAchievement($locations, $year, $month)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->join('d.parent','region');
        $qb->select('SUM(e.quantity) as achieveQuantity','SUM(e.targetQuantity) as targetQuantity');
        $qb->addSelect('d.name AS districtName');
        $qb->addSelect('region.name AS regionName');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->andWhere('region.level = 3');
        $qb->groupBy('region.id');
        $qb->addGroupBy('d.id');
        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            $data[$result['regionName']] = [
                'achieveQuantity' => $result['achieveQuantity'],
                'targetQuantity' => $result['targetQuantity'],
                'percentage' => $result['targetQuantity']>0?($result['achieveQuantity']*100)/$result['targetQuantity']:0,
                'mark' => $this->salesRegionalAchivementCalculation($result['targetQuantity'], $result['achieveQuantity'])
            ];
        }
        return $data;

    }

    public function salesDistrictAchivementCalculation($target, $achivement)
    {
        if($target > 0){
            $action = (($achivement * 100 )/$target);
            if($action >= 100) {
                return 4;
            }elseif ($action < 100 and $action >= 50) {
                return 3;
            }elseif ($action < 50 and $action >= 1) {
                return 1;
            }else {
                return 0;
            }

        }

    }


    public function salesRegionalAchivementCalculation($target, $achivement)
    {
        if($target > 0){
            $action = (($achivement * 100 )/$target);
            if($action >= 100) {
                return 5;
            }elseif ($action < 100 and $action >= 50) {
                return 3;
            }elseif ($action < 50 and $action >= 1) {
                return 2;
            }else {
                return 0;
            }

        }

    }

}
