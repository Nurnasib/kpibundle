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
        $qb->addSelect('markDistribution.id AS attributeId','markDistribution.name AS attributeName', 'markDistribution.salesMode');

        $qb->join('e.markDistribution', 'markDistribution');
        $qb->join('markDistribution.parent', 'markDistributionParent');
        $qb->where('e.employeeBoard = :id')->setParameter('id', $board);
        $qb->andWhere('markDistribution.salesMode IN (:salesMode)')->setParameter('salesMode', ['feed','growth']);
        $qb->orderBy('markDistribution.ordering', 'ASC');
        $qb->addOrderBy('markDistributionParent.ordering', 'ASC');
//        $qb->andWhere("markDistribution.slug = 'doc-sales-collection'");

        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            $percentage = '';
            if ($result['targetQuantity'] > 0){

                if ($result['salesMode'] == 'growth'){
                    $percentage = (($result['salesQuantity'] - $result['targetQuantity'])*100) / $result['targetQuantity'];
                }else {
                    $percentage = ($result['salesQuantity']*100) / $result['targetQuantity'];
                }

            }
            $data[$result['salesMode']][] = [
                'attributeId' => $result['attributeId'],
                'markParameter' => $result['attributeName'],
                'targetQuantity' => $result['targetQuantity'],
                'salesQuantity' => $result['salesQuantity'],
                'percentage' => $percentage,
                'mark' => $result['mark'],
            ];
        }
        return $data;
    }


    public function getSalesTargetAchievementReport($filterBy)
    {

        $qb = $this->createQueryBuilder('e');

        $qb->select('SUM(e.targetQuantity) AS totalTargetQuantity', 'SUM(e.salesQuantity) AS totalSalesQuantity');
        $qb->addSelect('employee.id', 'employee.name', 'employee.userId');
        $qb->addSelect('designation.name AS designationName');
        $qb->addSelect('mark_distribution.name AS markDistributionName');

        $qb->join('e.employeeBoard', 'employee_board');
        $qb->join('employee_board.employee', 'employee');
        $qb->join('e.markDistribution', 'mark_distribution');
        $qb->leftJoin('employee.designation', 'designation');

        $qb->where('mark_distribution.salesMode = :salesMode')->setParameter('salesMode', $filterBy['salesMode']);

        if (! is_null($filterBy['employee'])){
            $qb->andWhere('employee = :employee')->setParameter('employee', $filterBy['employee']);
        }

        if (! in_array('ROLE_KPI_ADMIN', $filterBy['loggedUser']->getRoles())){
            $qb->andWhere('employee.lineManager = :lineManager')->setParameter('lineManager', $filterBy['loggedUser']);
        }
//        $qb->andWhere('employee_board.month IN (:months)')->setParameter('months', $filterBy['months']);
        $qb->andWhere('employee_board.month =:month')->setParameter('month', $filterBy['endMonth']);
        $qb->andWhere('employee_board.year =:year')->setParameter('year', $filterBy['year']);

        $qb->groupBy('employee.id');
        $qb->addGroupBy('mark_distribution.id');

        $results = $qb->getQuery()->getArrayResult();

        $data = [];

        foreach ($results as $result) {
            $data[$result['userId']]['userInfo'] = [
                'userId' => $result['userId'],
                'name' => $result['name'],
                'designation' => $result['designationName'],

            ];
            if ($result['markDistributionName'] === 'Broiler Feed:  Sales Achievement:' ||
                $result['markDistributionName'] === 'Broiler Feed:  Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Broiler'

            ){
                $result['markDistributionName'] = 'Broiler';

            }elseif ($result['markDistributionName'] === 'Sonali Feed: Sales Achievement:' ||
                $result['markDistributionName'] === 'Sonali Feed: Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Sonali'
            ){
                $result['markDistributionName'] = 'Sonali';

            }elseif ($result['markDistributionName'] === 'Layer Feed: Sales Achievement:' ||
                $result['markDistributionName'] === 'Layer Feed: Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Layer'
            ){
                $result['markDistributionName'] = 'Layer';

            }elseif ($result['markDistributionName'] === 'Fish Feed: Sales Achievement:' ||
                $result['markDistributionName'] === 'Fish Feed: Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Fish'
            ){
                $result['markDistributionName'] = 'Fish';

            }elseif ($result['markDistributionName'] === 'Cattle Feed: Sales Achievement:' ||
                $result['markDistributionName'] === 'Cattle Feed: Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Cattle'
            ){
                $result['markDistributionName'] = 'Cattle';

            }
            $data[$result['userId']]['data'][$result['markDistributionName']] = [
                'totalTargetQuantity' => $result['totalTargetQuantity'],
                'totalSalesQuantity' => $result['totalSalesQuantity'],
            ];
        }

        foreach ($data as $key => $item) {
            $data[$key]['sum']['sumTargetQuantity'] = array_sum(array_column($data[$key]['data'], 'totalTargetQuantity'));
            $data[$key]['sum']['sumSalesQuantity'] = array_sum(array_column($data[$key]['data'], 'totalSalesQuantity'));
        }

        return $data;
    }



    public function getSalesTargetAchievementMarksReport($filterBy)
    {

        $qb = $this->createQueryBuilder('e');

        $qb->select('SUM(e.mark) AS totalAchieveMark');
        $qb->addSelect('employee.id', 'employee.name', 'employee.userId');
        $qb->addSelect('designation.name AS designationName');
        $qb->addSelect('mark_distribution.name AS markDistributionName', 'SUM(mark_distribution.mark) AS totalTargetMark');

        $qb->join('e.employeeBoard', 'employee_board');
        $qb->join('employee_board.employee', 'employee');
        $qb->join('e.markDistribution', 'mark_distribution');
        $qb->leftJoin('employee.designation', 'designation');

        $qb->where('mark_distribution.salesMode = :salesMode')->setParameter('salesMode', $filterBy['salesMode']);

        if (! is_null($filterBy['employee'])){
            $qb->andWhere('employee = :employee')->setParameter('employee', $filterBy['employee']);
        }

        if (! in_array('ROLE_KPI_ADMIN', $filterBy['loggedUser']->getRoles())){
            $qb->andWhere('employee.lineManager = :lineManager')->setParameter('lineManager', $filterBy['loggedUser']);
        }
        $qb->andWhere('employee_board.month IN (:months)')->setParameter('months', $filterBy['months']);
        $qb->andWhere('employee_board.year =:year')->setParameter('year', $filterBy['year']);

        $qb->groupBy('employee.id');
        $qb->addGroupBy('mark_distribution.id');
        $qb->orderBy('employee.name', 'ASC');

        $results = $qb->getQuery()->getArrayResult();

        $data = [];

        foreach ($results as $result) {
            $data[$result['userId']]['userInfo'] = [
                'userId' => $result['userId'],
                'name' => $result['name'],
                'designation' => $result['designationName'],

            ];
            if ($result['markDistributionName'] === 'Broiler Feed:  Sales Achievement:' ||
                $result['markDistributionName'] === 'Broiler Feed:  Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Broiler'

            ){
                $result['markDistributionName'] = 'Broiler';

            }elseif ($result['markDistributionName'] === 'Sonali Feed: Sales Achievement:' ||
                $result['markDistributionName'] === 'Sonali Feed: Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Sonali'
            ){
                $result['markDistributionName'] = 'Sonali';

            }elseif ($result['markDistributionName'] === 'Layer Feed: Sales Achievement:' ||
                $result['markDistributionName'] === 'Layer Feed: Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Layer'
            ){
                $result['markDistributionName'] = 'Layer';

            }elseif ($result['markDistributionName'] === 'Fish Feed: Sales Achievement:' ||
                $result['markDistributionName'] === 'Fish Feed: Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Fish'
            ){
                $result['markDistributionName'] = 'Fish';

            }elseif ($result['markDistributionName'] === 'Cattle Feed: Sales Achievement:' ||
                $result['markDistributionName'] === 'Cattle Feed: Sales Achievement' ||
                $result['markDistributionName'] === 'Product Sales Growth: Cattle'
            ){
                $result['markDistributionName'] = 'Cattle';

            }
            $data[$result['userId']]['data'][$result['markDistributionName']] = [
                'totalTargetMark' => $result['totalTargetMark'],
                'totalAchieveMark' => $result['totalAchieveMark'],
            ];
        }

        foreach ($data as $key => $item) {
            $data[$key]['sum']['sumTotalTargetMark'] = array_sum(array_column($data[$key]['data'], 'totalTargetMark'));
            $data[$key]['sum']['sumTotalAchieveMark'] = array_sum(array_column($data[$key]['data'], 'totalAchieveMark'));
        }

        return $data;
    }

}
