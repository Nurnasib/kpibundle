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
class AgentCategoryRepository extends EntityRepository
{
    public function getAllAgentWithGrade()
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.gradeStandard', 'gradeStandard');
        $qb->select('e.quantity', 'e.month', 'e.year');
        $qb->addSelect('agent.name AS agentName');
        $qb->addSelect('gradeStandard.grade AS gradeLetter');
        $qb->orderBy('agent.name', 'ASC');
        $results = $qb->getQuery()->getArrayResult();

        return $results;
    }
    public function getAgentGradeMonthWise($filterBy)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.gradeStandard', 'gradeStandard');
        $qb->join('agent.district', 'district');
        $qb->join('district.parent', 'region');
        $qb->join('region.parent', 'zone');
        $qb->select('e.average','e.month','e.year');
        $qb->addSelect('agent.agentId AS agentId', 'agent.name AS agentName');
        $qb->addSelect('region.name AS agentRegionName');
        $qb->addSelect('zone.name AS agentZoneName');
        $qb->addSelect('gradeStandard.grade');
        $qb->where('e.month = :prevMonth')->setParameter('prevMonth', $filterBy['month']);
        $qb->andWhere('e.year = :prevYear')->setParameter('prevYear', $filterBy['year']);
        $qb->groupBy('agent.agentId');
        $qb->orderBy('agent.agentId');
//        $qb->andWhere('agent.id = :agentId')->setParameter('agentId', 1271);
//        $qb->where('e.year', ':prevYear')->setParameter('prevYear', 2020);
        $array = [];
        $results = $qb->getQuery()->getArrayResult();
        foreach ($results as $row){
            $array[$row['agentId']]= $row;
        }

        return $array;
    }

    public function getPreviousYearCategoryAndAverage($prevYear)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.gradeStandard', 'gradeStandard');
        $qb->select('e.average');
        $qb->addSelect('gradeStandard.grade');
        $qb->addSelect('agent.agentId AS agentId');
        $qb->where("e.month = 'December'");
        $qb->andWhere('e.year = :prevYear')->setParameter('prevYear', $prevYear);
        $qb->groupBy('agent.agentId');
        $qb->orderBy('agent.agentId');
        $results = $qb->getQuery()->getArrayResult();
        $array = [];
        foreach ($results as $row){
            $array[$row['agentId']]= $row;
        }

        return $array;
    }

    public function insertAgentOrderInAgentCategory($monthName, $year, $file)
    {
//        $createdMonth = new \DateTime($year . '-' .date('m', strtotime($monthName)) . '-01')->format('Y-m-d');
        $createdMonth = $year . '-' .date('m', strtotime($monthName)) . '-01';

        $query = "INSERT INTO kpi_agent_category(agent_id, quantity, month, year, created_at, document_upload_id, created_month)
                    SELECT agent_id, SUM(quantity) AS totalQuantity, month, year, CURRENT_TIMESTAMP, document_upload_id, :createdMonth 
                    FROM kpi_agent_order
                    WHERE month = :month AND year = :year
                    GROUP BY agent_id, document_upload_id";

        $em = $this->_em;
        $stmt = $em->getConnection()->prepare($query);
        $stmt->bindValue('month', $monthName);
        $stmt->bindValue('year', $year);
        $stmt->bindValue('createdMonth', $createdMonth);
        $insert = $stmt->execute();

        if($insert){
            $updateGradeAndAvg = "UPDATE  kpi_agent_category AS kac
                                INNER JOIN
                                (
                                SELECT agent_id, AVG(quantity) AS avg_val
                                FROM kpi_agent_category
                                WHERE year = :year
                                GROUP BY agent_id
                                ) b ON kac.agent_id = b.agent_id
                                SET kac.average = b.avg_val,
                                kac.grade_standard_id = (
                                CASE 
                                WHEN b.avg_val >= 200 THEN 1
                                WHEN b.avg_val >= 180 AND b.avg_val < 200 THEN 2
                                WHEN b.avg_val >= 160 AND b.avg_val < 180 THEN 3
                                WHEN b.avg_val >= 140 AND b.avg_val < 160 THEN 4
                                WHEN b.avg_val >= 120 AND b.avg_val < 140 THEN 5
                                WHEN b.avg_val >= 100 AND b.avg_val < 120 THEN 6
                                ELSE 
                                6
                                END
                                )   
                            WHERE kac.document_upload_id = :fileId";

            $stmtUpdate = $em->getConnection()->prepare($updateGradeAndAvg);
            $stmtUpdate->bindValue('year', $year);
            $stmtUpdate->bindValue('fileId', $file->getId());
            $update = $stmtUpdate->execute();

            if($update){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getPreviousYearCategory($districsId)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->join('e.agent', 'agent');
        $qb->join('agent.district', 'agentDistrict');
        $qb->select();
        $qb->where('agentDistrict.id IN (:agentDistrictId)')->setParameter('agentDistrictId', $districsId);
        $qb->andWhere("e.month = 'December'");
        $qb->andWhere('e.year = :year')->setParameter('year', 2020);
        $results = $qb->getQuery()->getArrayResult();
        return $results;
    }

    public function getCategoryUpgradationMarks(EmployeeBoard $employeeBoard, $gradeLetters)
    {
        $prevYear = date('Y',strtotime('-1 year'));
        $locations = $employeeBoard->getEmployee()->getDistrict();
        $locationsId = [];
        if(!empty($locations)){
            foreach ($locations as $location){
                $locationsId[] = $location->getId();
            }
        }
//        $gradeLetters = ['C','D'];
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent','agent');
        $qb->join('agent.district','district');
        $qb->join('e.gradeStandard','gradeStandard');

        $qb->select('gradeStandard.grade');
        $qb->addSelect('agent.id AS agentId');

        $qb->where('e.year = :prevYear')->setParameter('prevYear', $prevYear);
        $qb->andWhere('district.id IN (:districtsId)')->setParameter('districtsId', $locationsId);
        $qb->andWhere("e.month = 'December'");
        $qb->andWhere('gradeStandard.grade IN (:gradeLetters)')->setParameter('gradeLetters', $gradeLetters);

        $results = $qb->getQuery()->getArrayResult();

        $agentsIdWithCategory =[];
        foreach ($results as $result){
            $agentsIdWithCategory[$result['grade']][]= $result['agentId'];
        }

        $categoryUpgradationPercentages = $this->currentMonthCategoryUpgradationPercentage($agentsIdWithCategory,$employeeBoard);
        $marks = $this->categoryUpgradationMarks($categoryUpgradationPercentages);
        return $marks;
    }
    private function currentMonthCategoryUpgradationPercentage($agentsIdWithCategory, EmployeeBoard $employeeBoard)
    {
//        $lastMonth = Date('F', strtotime(date('F') . " last month"));
        $month = $employeeBoard->getMonth();
        $currentYear = date('Y');
        $categoryUpgradationPercentages = [];

        foreach ($agentsIdWithCategory as $category => $agentsId){

            $omittedGradeLetters = range($category, 'F');

            $qb = $this->createQueryBuilder('e');
            $qb->join('e.agent','agent');
            $qb->join('e.gradeStandard','gradeStandard');

            $qb->select('e.id','gradeStandard.grade');

            $qb->where('e.year = :currentYear')->setParameter('currentYear', $currentYear);
            $qb->andWhere('e.month = :month')->setParameter('month', $month);
            $qb->andWhere('gradeStandard.grade NOT IN (:omittedGradeLetter)')->setParameter('omittedGradeLetter', $omittedGradeLetters);
            $qb->andWhere('agent.id IN (:agentId)')->setParameter('agentId', $agentsId);
            $results = $qb->getQuery()->getArrayResult();

            $pervYearCategoryNumber = count($agentsId);
            $currentCategoryNumber = count($results);

//            $prevGrade = chr(ord($category)-1);

            $categoryUpgradationPercentages[$category . 'to' . 'UpperGrade'] = round(($currentCategoryNumber * 100) / ($pervYearCategoryNumber / 2));
        }
        return $categoryUpgradationPercentages;
    }

    private function categoryUpgradationMarks($categoryUpgradationPercentages)
    {
        $marks = [];
        foreach ($categoryUpgradationPercentages as $grade => $percentage){
            if($percentage >= 100){
                $marks[$grade] = 5;
            }elseif ($percentage < 100 && $percentage >= 80){
                $marks[$grade] = 4;
            }elseif ($percentage < 80 && $percentage >= 70){
                $marks[$grade] = 3;
            }elseif ($percentage < 70 && $percentage >= 60){
                $marks[$grade] = 2;
            }elseif ($percentage < 60){
                $marks[$grade] = 1;
            }
        }
        return $marks;
    }

    public function getPrevYearAndCurrentMonthCategory(EmployeeBoard $employeeBoard,$gradeLetters )
    {
        $prevYear = date('Y',strtotime('-1 year'));
        $locations = $employeeBoard->getEmployee()->getDistrict();
        $locationsId = [];
        if(!empty($locations)){
            foreach ($locations as $location){
                $locationsId[] = $location->getId();
            }
        }
//        $gradeLetters = ['C','D'];
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent','agent');
        $qb->join('agent.district','district');
        $qb->join('e.gradeStandard','gradeStandard');

        $qb->select('gradeStandard.grade');
        $qb->addSelect('agent.id AS agentId');

        $qb->where('e.year = :prevYear')->setParameter('prevYear', $prevYear);
//        $qb->andWhere('district.id IN (:districtsId)')->setParameter('districtsId', $locationsId);
        $qb->andWhere("e.month = 'December'");
        $qb->andWhere('gradeStandard.grade IN (:gradeLetters)')->setParameter('gradeLetters', $gradeLetters);

        $results = $qb->getQuery()->getArrayResult();

        $agentsIdWithCategory =[];
        foreach ($results as $result){
            $agentsIdWithCategory[$result['grade']][]= $result['agentId'];
        }
        return $agentsIdWithCategory;

    }
}
