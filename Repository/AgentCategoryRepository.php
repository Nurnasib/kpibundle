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
    public function getAllAgentWithCategory()
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
    public function getAgentGradeMonthWise()
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->select('SUM(e.quantity) AS totalQuantity');
        $qb->addSelect('agent.id AS agentId', 'agent.name AS agentName');
        $qb->where('e.year = :prevYear')->setParameter('prevYear', 2020);
        $qb->groupBy('agent.id');
//        $qb->andWhere('agent.id = :agentId')->setParameter('agentId', 1271);
//        $qb->where('e.year', ':prevYear')->setParameter('prevYear', 2020);
        $results = $qb->getQuery()->getArrayResult();

        return $results;
    }

    public function getPreviousAllMonthTotalQuantity()
    {
        $em = $this->_em;
        $query = "SELECT COUNT(id), SUM(quantity) AS totalQty
FROM kpi_agent_category 
WHERE agent_id = 1838 AND MONTH(month) < MONTH(CURRENT_DATE)";

        $stmt = $em->getConnection()->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();


        return $query->getResult();
    }
}
