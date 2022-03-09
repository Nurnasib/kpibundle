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
use App\Entity\Core\Setting;
use Doctrine\ORM\EntityRepository;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\MarkChart;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class AgentOrderRepository extends EntityRepository
{

    public function findWithAgentSearch($data)
    {
        $year = isset($data['year']) ? $data['year']:'';
        $month = isset($data['month']) ? $data['month']:'';
        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.agent','agent');
        $qb->leftJoin('e.district','d');
        $qb->select('agent.id as customerId','agent.agentId as agentId','agent.name as agentName');
        $qb->addSelect('d.id as districtId','d.name as districtName');
        $qb->addSelect('e.month as month','e.year as year');
//        $qb->groupBy('agent.id','e.month','e.year');
        $qb->groupBy('agent.id');
        $qb->where('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);

        if(isset($data['agent'])){
            $qb->andWhere('agent.id =:agent')->setParameter('agent',$data['agent']);
        }
        if(isset($data['district'])){
            $qb->andWhere('d.id =:district')->setParameter('district', $data['district']);
        }

        $qb->orderBy('agent.name','ASC');
        return $qb->getQuery()->getArrayResult();
    }

    public function findWithAgentOrderOty($data)
    {
        $year = isset($data['year']) ? $data['year']:'';
        $month = isset($data['month']) ? $data['month']:'';
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent','agent');
        $qb->join('e.product','p');
        $qb->leftJoin('e.district','d');
        $qb->select('agent.id as customerId');
        $qb->addSelect('p.id as productId');
        $qb->addSelect('SUM(e.quantity) as quantity');
        $qb->addSelect('e.month as month','e.year as year');
        $qb->groupBy('agent.id','p.id','e.month','e.year');
        $qb->where('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        if(isset($data['agent'])){
            $qb->andWhere('e.agent =:agent')->setParameter('agent',$data['agent']);
        }
        $qb->orderBy('agent.name','ASC');
        $results = $qb->getQuery()->getArrayResult();
        
        $arrayReturn=[];
        
        foreach ($results as $result){
            $arrayReturn[$result['year']][$result['month']][$result['customerId']][$result['productId']]=$result;
        }
        
        return $arrayReturn;
    }

    public function findSalesItems($entities, $data)
    {
        $ids = array();
        foreach ($entities as $row){
            $ids[] = $row['customerId'];
        }
        $year = isset($data['year']) ? $data['year']:'';
        $month = isset($data['month']) ? $data['month']:'';
        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.product','distribution');
        $qb->leftJoin('e.agent','agent');
        $qb->select('distribution.id as distributionId','distribution.name as distributionName','e.amount as amount','e.quantity as quantity');
        $qb->addSelect('agent.id as agentId','agent.name as agentName');
        $qb->where('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $qb->andWhere('agent.id IN (:ids)')->setParameter('ids', $ids);
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $salesId = "{$row['agentId']}-{$row['distributionId']}";
            $data[$salesId] = $row['quantity'];
        }
        return $data;
    }

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

    public function getDistrictWiseTotalProductSales($month, $year)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.product','p');
        $qb->join('e.district','d');
        $qb->select('e.year as oYear','e.month as oMonth', 'SUM(e.quantity) as totalQty');
        $qb->addSelect('p.id as pId');
        $qb->addSelect('d.id as dId');
        $qb->where('e.month =:month')->setParameter('month',$month);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->groupBy('d.id','p.id');
        return $qb->getQuery()->getArrayResult();

    }

    public function insertAgentSales($file, $keys, $allData, $month, $year)
    {
        set_time_limit(0);
        $em =$this->_em;
        $addedId = [];
        $existingId = [];
        $flashArray = [];
        $keysLength = count($keys);

        $breedTypes = [$keys[5], $keys[6], $keys[7], $keys[8], $keys[9]]; //Broiler, Sonali, Layer, Fish, Cattle

        foreach ($allData as $data) {

            //Marge Excel heading and value in one array as key and value
            $details = array_combine($keys, array_slice($data,null,$keysLength));

            list($agentIdValue, $agentNameValue, $upozilaValue, $districtCodeValue, $districtValue, $broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue, $monthValue, $yearValue) = $data;

            $breedValues = [$broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue];

            $breedArrays = array_combine($breedTypes, $breedValues);

            $district = $em->getRepository(Location::class)->findOneBy(['level'=>4,'code' => $districtCodeValue]);
            if(!$district){
                $district = $em->getRepository(Location::class)->findOneBy(['level'=>4,'name' => $districtValue]);
            }
            $upozila = $em->getRepository(Location::class)->findOneBy(['level'=>5,'name' => $upozilaValue]);

            //Find agent
            $feedAgentGroup = $em->getRepository(Setting::class)->findOneBy(array('slug' => 'feed'));

            $findAgent = $em->getRepository(Agent::class)->findOneBy(['agentGroup'=>$feedAgentGroup,'agentId' =>$agentIdValue]);
            if (!$findAgent) {
                $agent = new Agent();
                $agent->setAgentId($agentIdValue);
                $agent->setUpozila($upozila?$upozila:null);
                $agent->setDistrict($district?$district:null);
                $agent->setName($agentNameValue);
                $agent->setAgentGroup($feedAgentGroup);
                $agent->setCreated(new \DateTime());
                $em->persist($agent);
                $em->flush();
                $findAgent = $agent;
            }
            foreach ($breedArrays as $breedType => $value) {

                $product = $em->getRepository(MarkChart::class)->findOneBy(['salesMode'=>'feed','name' => $breedType]);
                if ($product) {
                    $exitAgentOrder = $em->getRepository(AgentOrder::class)->findOneBy(array('agent'=>$findAgent,'product'=>$product,'month'=>$month,'year'=>$year));

                    if(!$exitAgentOrder){

                        $agentDistrict = $findAgent->getDistrict()?$findAgent->getDistrict():null;
                        $agentUpozila = $findAgent->getUpozila()?$findAgent->getUpozila():null;

                        $agentOrder = new AgentOrder();
                        $agentOrder->setAgent($findAgent);
                        $agentOrder->setDistrict($district?$district:$agentDistrict);
                        $agentOrder->setUpozila($upozila?$upozila:$agentUpozila);
                        $agentOrder->setProduct($product);
                        $agentOrder->setQuantity((double)$value);
                        $agentOrder->setCreated(new \DateTime());
                        $agentOrder->setUpdated(new \DateTime());
                        $agentOrder->setMonth($month);
                        $agentOrder->setYear($year);
                        $agentOrder->setDocumentUpload($file);
                        $em->persist($agentOrder);
                        $em->flush();

//                        $addedId[] = $agentOrder->getId();
                        $flashArray['new'][] = $agentOrder->getId();
                    }else{
//                        $existingId[]=$exitAgentOrder->getId();
                        $flashArray['update'][] = $exitAgentOrder->getId();
                    }
                }
            }
        }
        $file->setStatus(1);

        $em->persist($file);
        $em->flush();

        return $flashArray;
    }

    public function getOutstanding()
    {
        $qb = $this->createQueryBuilder('e');

        $qb->select('agent.id AS agentId', 'e.month', 'e.year');

        $qb->addSelect('SUM(e.quantity)*10 AS acheiveQuantity');
        $qb->addSelect('SUM(e.quantity)*8 AS actualQuantity');

        $qb->leftJoin('e.agent', 'agent');
        $qb->where("agent.id = 1216");
        $qb->andWhere("e.month = 'March'");
        $qb->andWhere("e.year = 2021");

        $results = $qb->getQuery()->getArrayResult();
        return $results;

    }

    public function getAgentWithSalesQuantity(EmployeeBoard $board, $locationsId)
    {

//        $prevYear = date('Y',strtotime("-1 year", strtotime($board->getYear())));
        $years = [$board->getYear()-1, $board->getYear()];

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.district', 'district');

        $qb->select('SUM(e.quantity) AS totalQuantity', 'e.year');
        $qb->addSelect('agent.id AS agentId');

        $qb->where('e.month = :month')->setParameter('month', $board->getMonth());
        $qb->andWhere('e.year IN (:years)')->setParameter('years', $years);
        $qb->andWhere('district.id IN (:districtId)')->setParameter('districtId', $locationsId);
        $qb->andWhere('agent.status = 1');

        $qb->groupBy('agent.id');
        $qb->addGroupBy('e.year');

        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            $data[$result['year']][$result['agentId']] = $result['totalQuantity'];
        }

        if (! array_key_exists($board->getYear()-1, $data)){
            $data[$board->getYear()-1] = [];
        }
        if (! array_key_exists($board->getYear(), $data)){
            $data[$board->getYear()] = [];
        }
        return $data;
    }

    public function getGrowthAgentSalesDetails(EmployeeBoard $board, $districtsId)
    {
        $growthAgents = [];
        $prevYear = $board->getYear()-1;
        $years = [$board->getYear()-1, $board->getYear()];

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.district', 'district');

        $qb->select('SUM(e.quantity) AS totalQuantity', 'e.year');
        $qb->addSelect('agent.id AS agentId');

        $qb->where('e.month = :month')->setParameter('month', $board->getMonth());
        $qb->andWhere('e.year IN (:years)')->setParameter('years', $years);
        $qb->andWhere('district.id IN (:districtId)')->setParameter('districtId', $districtsId);
        $qb->andWhere('agent.status = 1');

        $qb->groupBy('agent.id');
        $qb->addGroupBy('e.year');

        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            $data[$result['year']][$result['agentId']] = $result['totalQuantity'];
        }

        if (! array_key_exists($board->getYear(), $data)){
            $data[$board->getYear()] = [];
        }

        if (! array_key_exists($board->getYear()-1, $data)){
            $data[$board->getYear()-1] = [];
        }
        $commonAgentBetweenYears = array_intersect_key($data[$board->getYear()],$data[$prevYear]);  //Common agents and SalesQuantity(Current Year)
//        $growthAgentNumber = 0;
        foreach ($commonAgentBetweenYears as $agentId => $currentYearAgentSalesQty) {
            if ($data[$prevYear][$agentId]){
                if ($currentYearAgentSalesQty > $data[$prevYear][$agentId]){
//                    $growthAgentNumber++;
                    $growthAgents[] = $agentId;
/*                    $growthPercentage = (($currentYearAgentSalesQty - $data[$prevYear][$agentId]) * 100) / $data[$prevYear][$agentId];
                    if ($growthPercentage >= 20){
                        $growthAgents[] = $agentId;
                    }*/
                }
            }
        }
        $returnData = $this->getSalesDetails($growthAgents, $years, $board);
        $returnData['totalAgent'] = count($commonAgentBetweenYears);
        $returnData['growthAgent'] = count($growthAgents);
//        $returnData['twentyPercentGrowthAgents'] = count($growthAgents);
        return $returnData;
    }

    private function getSalesDetails($growthAgents, $years, $board)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('agent.upozila', 'upozila');
        $qb->join('agent.district', 'district');

        $qb->select('SUM(e.quantity) AS totalQuantity', 'e.year');
        $qb->addSelect('agent.agentId AS agentId', 'agent.name AS agentName', 'upozila.name AS agentThana', 'district.name AS agentDistrict');

        $qb->where('e.year IN (:years)')->setParameter('years', $years);
        $qb->andWhere('agent.id IN (:agentId)')->setParameter('agentId', $growthAgents);
        $qb->andWhere('e.month = :month')->setParameter('month', $board->getMonth());
        $qb->groupBy('e.year');
        $qb->addGroupBy('agent.id');
        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            $data[(int)$result['agentId']][$result['year']] = $result['totalQuantity'];
            $data[(int)$result['agentId']]['agentName'] = $result['agentName'];
            $data[(int)$result['agentId']]['agentAddress'] = $result['agentThana'] . ' , ' . $result['agentDistrict'];
        }
        return $data;

    }
}
