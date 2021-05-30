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
use Terminalbd\KpiBundle\Entity\AgentDocSaleCollection;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class AgentDocSaleCollectionRepository extends EntityRepository
{
    public function insertDocSalesCollection($file, $keys, $allData, $month, $year)
    {
        $data = [];
        $addedId = [];
        $em = $this->_em;
        $keysLength = count($keys);
        foreach ($allData as $value){
            $data[] = array_combine($keys,array_slice($value, null, $keysLength));
        }

        foreach ($data as $record){

            $district = $em->getRepository(Location::class)->findOneBy(['level'=>4,'code' => $record['DistrictId']]);
            //Find agent
            $findAgent = $em->getRepository(Agent::class)->findOneBy(['agentId' =>$record['AgentId']]);
            if ($findAgent) {
                $agentDocSale = new AgentDocSaleCollection();
                $agentDocSale->setAgent($findAgent);
                $agentDocSale->setSales((double)str_replace(',', '', $record['Sales']));
                $agentDocSale->setCollection((double)str_replace(',', '', $record['Collection']));
                $agentDocSale->setDistrict($district?$district:null);
                $agentDocSale->setMonth($month);
                $agentDocSale->setYear($year);
                $agentDocSale->setCreatedAt(new \DateTime());
                $em->persist($agentDocSale);
                $em->flush();

                $addedId = $agentDocSale->getId();
            }
        }
        $file->setStatus(1);

        $em->persist($file);
        $em->flush();
        return $addedId;
    }


    public function getLocationWiseTotalDocSales($locations, $year, $month)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->select('SUM(e.sales) as totalSalesAmount', 'SUM(e.collection) as totalCollectionAmount');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result;
    }
    public function getLocationWiseDocSales($locations, $year, $month)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
//        $qb->join('e.agent','a');
        $qb->select('SUM(e.sales) as totalSalesAmount', 'SUM(e.collection) as totalCollectionAmount');
//        $qb->addSelect('a.name as agentName');
        $qb->addSelect('d.name AS districtName');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->groupBy('d.id');
        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            $data[$result['districtName']] = array(
                'totalSalesAmount'=>$result['totalSalesAmount'],
                'totalCollectionAmount'=>$result['totalCollectionAmount'],
                'percentage'=>$result['totalSalesAmount']>0?($result['totalCollectionAmount']*100)/$result['totalSalesAmount']:0,
                'mark'=>$this->docSalesCollectionCalculationMark($result['totalCollectionAmount'], $result['totalSalesAmount']),
            );
        }
        return $data;
    }



    private function docSalesCollectionCalculationMark($collectionAmount, $salesAmount)
    {

        if ($salesAmount > 0) {
            $action = (($collectionAmount * 100) / $salesAmount);
            if ($action >= 100) {
                return 5;
            } elseif ($action < 100 and $action >= 90) {
                return 4;
            } elseif ($action < 90 and $action >= 85) {
                return 3;
            } elseif ($action < 85 and $action >= 75) {
                return 2;
            } elseif ($action < 75) {
                return 1;
            }
        }
        return 0;

    }


    public function getMonthYearSalesCollection($data)
    {
        $year = isset($data['year']) ? $data['year']:'';
        $month = isset($data['month']) ? $data['month']:'';
        $agent = isset($data['agent']) ? $data['agent']:'';

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.district', 'district');
        $qb->select('e.collection', 'e.sales', 'e.month', 'e.year');
        $qb->addSelect('agent.name AS agentName', 'district.name AS districtName');
        $qb->where('e.month = :month')->setParameter('month', $month);
        $qb->andWhere('e.year = :year')->setParameter('year', $year);
        if ($agent){
            $qb->andWhere('agent.id =:agent')->setParameter('agent',$data['agent']);
        }
        $qb->groupBy('agent.id');

        $results = $qb->getQuery()->getArrayResult();
        return $results;
    }
}
