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
        $qb->join('e.agent','agent');
        $qb->leftJoin('e.district','d');
        $qb->select('agent.id as customerId','agent.agentId as agentId','agent.name as agentName');
        $qb->addSelect('d.id as districtId','d.name as districtName');
        $qb->addSelect('e.month as month','e.year as year');
        $qb->groupBy('agent.id','e.month','e.year');
        $qb->where('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);

        if(isset($data['agent'])){
            $qb->andWhere('e.agent =:agent')->setParameter('agent',$data['agent']);
        }

        $qb->orderBy('agent.name','ASC');
        $result = $qb->getQuery();
        return $result;
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
        //dd($ids);
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
        //  dd($data);
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
        $result = $qb->getQuery()->getArrayResult();
        
        return $result;

    }

    public function insertAgentSales($file, $keys, $allData, $month, $year)
    {
        $em =$this->_em;
        $addedId = [];
        $existingId = [];
        $flashArray = [];

        $breedTypes = [$keys[4], $keys[5], $keys[6], $keys[7], $keys[8]];
        foreach ($allData as $data) {
            //Marge Excel heading and value in one array as key and value
            $details = array_combine($keys, $data);
            list($agentIdValue, $agentNameValue, $upozilaValue, $districtValue, $broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue, $monthValue, $yearValue) = $data;

            $breedValues = [$broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue];

            $breedArrays = array_combine($breedTypes, $breedValues);

            $district = $em->getRepository(Location::class)->findOneBy(['level'=>4,'name' => $districtValue]);
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
                        $agentOrder = new AgentOrder();
                        $agentOrder->setAgent($findAgent);
                        $agentOrder->setDistrict($district?$district:null);
                        $agentOrder->setUpozila($upozila?$upozila:null);
                        $agentOrder->setProduct($product);
                        $agentOrder->setQuantity($value);
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

}
