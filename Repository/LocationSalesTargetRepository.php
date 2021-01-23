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
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class LocationSalesTargetRepository extends EntityRepository
{

    public function processLocationPrice($locations , $charts )
    {
        foreach ($charts as $chart){

            foreach ($locations as $location ){

                $exist = $this->findOneBy(array('markDistribution' => $chart, 'upozila' => $location));
                if(empty($exist)){
                    $entity = new LocationSalesTarget();
                    $entity->setMarkDistribution($chart);
                    $entity->setUpozila($location);
                    $entity->setDistrict($location->getParent());
                    $entity->setRegional($location->getParent()->getParent());
                    $entity->setZone($location->getParent()->getParent()->getParent());
                    $this->_em->persist($entity);
                    $this->_em->flush();
                }
            }
        }

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.upozila','l');
        $qb->join('e.markDistribution','m');
        $qb->select('e.id as matrixId','e.amount as amount','l.id as upozilaId','l.name as upozila','m.id as markId','m.name as martDistribution');
        $result = $qb->getQuery()->getArrayResult();
        $array = array();
        foreach ($result as $item):
            $id = "{$item['upozilaId']}-{$item['markId']}";
            $array[$id] = $item;
        endforeach;
        return $array;

    }

    public function apiInsert($data)
    {
        $em = $this->_em;
        foreach ($data as $key => $value){

            $date = new \DateTime($data[$key]['created']);
            $entity = new AgentOrder();
            $entity->setCreated($date);
            $entity->setUpdated($date);
            $entity->setAmount($data[$key]['amount']);
            $entity->setQuantity($data[$key]['quantity']);
            $entity->setMonth($data[$key]['month']);
            $entity->setYear($data[$key]['year']);
            if($data[$key]['itemName']){
                $slug = strtolower($data[$key]['itemName']);
                $product = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => $slug));
                $entity->setProduct($product);
            }
            if($data[$key]['agentId']){
                $agentId = $data[$key]['agentId'];
                $agent = $em->getRepository(Agent::class)->findOneBy(array('oldId' => $agentId));
                if($agent){
                    $entity->setAgent($agent);
                }
            }
            if($data[$key]['upozila']){
                $upozila = trim($data[$key]['upozila']);
                $location = $em->getRepository(Location::class)->findOneBy(array('oldId' => $upozila));
                $entity->setUpozila($location);
            }
            $em->persist($entity);
            $em->flush();
        }
    }

}
