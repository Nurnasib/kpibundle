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
use Terminalbd\KpiBundle\Entity\AgentOutstanding;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;

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

        $data = [];
        $addedId = [];
        $em = $this->_em;
        $keysLength = count($keys);

        foreach ($allData as $value){

            $data[] = array_combine($keys,array_slice($value, null, $keysLength));
        }
        foreach ($data as $record){
            $record['ActualAmount'] = (double)str_replace(',', '',$record['ActualAmount']);
            $record['LimitAmount'] = (double)str_replace(',', '',$record['LimitAmount']);

            $district = $em->getRepository(Location::class)->findOneBy(['level'=>4,'code' => $record['DistrictId']]);
            //Find agent
            $findAgent = $em->getRepository(Agent::class)->findOneBy(['agentId' =>$record['AgentId']]);
            if ($findAgent) {
                $agentOutstanding = new AgentOutstanding();
                $agentOutstanding->setAgent($findAgent);
                $agentOutstanding->setDistrict($district);
                $agentOutstanding->setActualAmount((double)str_replace(' ', '',$record['ActualAmount']));
                $agentOutstanding->setLimitAmount((double)str_replace(',', '',$record['LimitAmount']));
                $agentOutstanding->setOutstanding($record['ActualAmount']-$record['LimitAmount']);
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
        $qb->select('SUM(e.outstanding) as outstanding');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result;
    }

    public function getLocationWiseOutstanding($locations, EmployeeBoard $board)
    {
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
                'actualAmount' => $result['actualAmount'],
                'limitAmount' => $result['limitAmount'],
                'percentage' => $result['limitAmount']>0?($result['actualAmount']*100)/$result['limitAmount']:0,
                'mark' => $this->outstandingLimitCalculation($result['outstanding'], $board),
            ];
        }

        return $data;
    }

    private function outstandingLimitCalculation($outstandingValue, EmployeeBoard $board)
    {
        if($outstandingValue){
            if ($board->getEmployee()->getReportMode()->getSlug() == 'agm-kpi'){
                if ($outstandingValue >= 1500000) {
                    return 0;
                } elseif ($outstandingValue >= 1400000 && $outstandingValue < 1500000) {
                    return 1;
                } elseif ($outstandingValue >= 1200000 && $outstandingValue < 1400000) {
                    return 2;
                } elseif ($outstandingValue >= 1000000 && $outstandingValue < 1200000) {
                    return 3;
                } elseif ($outstandingValue > 0 && $outstandingValue < 1000000) {
                    return 4;
                } else{
                    return 5;
                }
            }elseif ($board->getEmployee()->getReportMode()->getSlug() == 'rsm-arsm-kpi'){
                if ($outstandingValue >= 1000000) {
                    return 0;
                } elseif ($outstandingValue >= 900000 && $outstandingValue < 1000000) {
                    return 1;
                } elseif ($outstandingValue >= 700000 && $outstandingValue < 900000) {
                    return 2;
                } elseif ($outstandingValue >= 500000 && $outstandingValue < 700000) {
                    return 3;
                } elseif ($outstandingValue > 0 && $outstandingValue < 500000) {
                    return 4;
                } else{
                    return 5;
                }
            }else{
                if ($outstandingValue >= 500000) {
                    return 0;
                } elseif ($outstandingValue >= 400000 && $outstandingValue < 500000) {
                    return 1;
                } elseif ($outstandingValue >= 300000 && $outstandingValue < 400000) {
                    return 2;
                } elseif ($outstandingValue >= 200000 && $outstandingValue < 300000) {
                    return 3;
                } elseif ($outstandingValue > 0 && $outstandingValue < 200000) {
                    return 4;
                } else{
                    return 5;
                }
            }
        }else{
            return 0;
        }
    }

    public function getMonthYearOutstanding($monthYear)
    {
        $year = isset($monthYear['year']) ? $monthYear['year']:'';
        $month = isset($monthYear['month']) ? $monthYear['month']:'';


        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.district', 'district');
        $qb->select('e.actualAmount', 'e.outstanding', 'e.limitAmount', 'e.month', 'e.year');
        $qb->addSelect('agent.name AS agentName', 'district.name AS districtName');
        $qb->where('e.month = :month')->setParameter('month', $month);
        $qb->andWhere('e.year = :year')->setParameter('year', $year);
        $qb->groupBy('agent.id');

        $results = $qb->getQuery()->getArrayResult();
        return $results;

    }
}
