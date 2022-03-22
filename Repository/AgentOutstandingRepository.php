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

use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Terminalbd\KpiBundle\Entity\AgentOutstanding;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\MarkChart;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class AgentOutstandingRepository extends EntityRepository
{
    public function insertAgentOutstanding($file, $keys,$allData, $month, $year)
    {
        set_time_limit(0);
        $data = [];
        $addedId = [];
        $em = $this->_em;
        $keysLength = count($keys);

        foreach ($allData as $value){

            $data[] = array_combine($keys,array_slice($value, null, $keysLength));
        }
        foreach ($data as $record){
            $record['ActualAmount'] = (double)str_replace(',', '',$record['Net Outstanding']);
            $record['LimitAmount'] = (double)str_replace(',', '',$record['Limit']);

            $district = $em->getRepository(Location::class)->findOneBy(['level' => 4,'code' => $record['DistrictId']]);
            //Find agent
            $findAgent = $em->getRepository(Agent::class)->findOneBy(['agentId' => $record['AgentId']]);
            if ($findAgent) {
                $agentOutstanding = new AgentOutstanding();
                $agentOutstanding->setAgent($findAgent);
                $agentOutstanding->setDistrict($district);
                $agentOutstanding->setActualAmount($record['ActualAmount']?:0);
                $agentOutstanding->setLimitAmount($record['LimitAmount']?:0);
                $agentOutstanding->setOutstanding($record['ActualAmount'] - $record['LimitAmount']);
                $agentOutstanding->setCreatedAt(new \DateTime());
                $agentOutstanding->setMonth($month);
                $agentOutstanding->setYear($year);
                $em->persist($agentOutstanding);
                $em->flush();
                $addedId = $agentOutstanding->getId();
            }
        }
        $file->setStatus(1);

        $em->persist($file);
        $em->flush();
        return $addedId;
    }

    public function getLocationWiseTotalOutstanding($locations, $year, $month)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->select('SUM(e.outstanding)');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getLocationWiseOutstanding($locations, EmployeeBoard $board)
    {
        $em = $this->_em;

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->select('SUM(e.actualAmount) AS actualAmount', 'SUM(e.limitAmount) AS limitAmount', 'SUM(e.outstanding) AS outstanding');
        $qb->addSelect('d.name AS districtName');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$board->getYear());
        $qb->andWhere('e.month =:month')->setParameter('month',$board->getMonth());
        $qb->groupBy('d.id');
        $results = $qb->getQuery()->getArrayResult();

        $data = [];
        foreach ($results as $result){
            $data[$result['districtName']] = [
                'actualAmount' => (double)$result['actualAmount'],
                'limitAmount' => (double)$result['limitAmount'],
                'outstanding' => (double)$result['outstanding'],
            ];
        }
        if ($board->getEmployee()->getReportMode()->getSlug() == 'agm-kpi'){
            $outstandingSlug = 'agm-outstanding-limit-vs-actual-feed';
        }elseif ($board->getEmployee()->getReportMode()->getSlug() == 'rsm-arsm-kpi'){
            $outstandingSlug = 'rsm-outstanding-limit-vs-actual-feed';
        }else{
            $outstandingSlug = 'outstanding-limit-vs-actual-feed';
        }
        $outstandingDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => $outstandingSlug));
        $employeeBoardAttributeForOutStandingLimit = $em->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $outstandingDistribution]);
        $data['mark'] = $employeeBoardAttributeForOutStandingLimit ? (int)$employeeBoardAttributeForOutStandingLimit->getMark() : 0;
        return $data;
    }

    public function getMonthYearOutstanding($monthYear, $agentId, $districtId)
    {
        $year = isset($monthYear['year']) ? $monthYear['year']:'';
        $month = isset($monthYear['month']) ? $monthYear['month']:'';

        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.agent', 'agent');
        $qb->leftJoin('e.district', 'district');
        $qb->select('e.actualAmount', 'e.outstanding', 'e.limitAmount', 'e.month', 'e.year');
        $qb->addSelect('agent.name AS agentName', 'district.name AS districtName');
        $qb->where('e.month = :month')->setParameter('month', $month);
        $qb->andWhere('e.year = :year')->setParameter('year', $year);
        if(!empty($agentId)){
            $qb->andWhere('agent.id =:agent')->setParameter('agent',$agentId);
        }
        if(!empty($districtId)){
            $qb->andWhere('district.id =:district')->setParameter('district', $districtId);
        }

        $qb->groupBy('agent.id');

        $results = $qb->getQuery()->getArrayResult();
        return $results;

    }
}
