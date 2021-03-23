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
}
