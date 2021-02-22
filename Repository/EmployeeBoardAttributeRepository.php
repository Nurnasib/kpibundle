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
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeBoardSubAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\MarkChart;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class EmployeeBoardAttributeRepository extends EntityRepository
{

    public function EmployeeBoardMarks(EmployeeBoard $board )
    {

        $qb = $this->createQueryBuilder('e');
        $qb->select('e.id as id','a.id as attribute','m.id as markDistributionId','e.actualMark as mark','e.targetAmount as targetAmount','e.targetAchievement as targetAchievement');
        $qb->join("e.attribute",'a');
        $qb->leftJoin("e.markDistribution",'m');
        $qb->where("e.employeeBoard = {$board->getId()}");
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['attribute']] = $row;
        }
        return $data;
    }

    public function EmployeeBoardSummaryReport(EmployeeBoard $board )
    {

        $qb = $this->createQueryBuilder('e');
        $qb->select('SUM(e.actualMark) as actualMark','SUM(e.mark) as mark','a.name as activity');
        $qb->join("e.activity",'a');
        $qb->where("e.employeeBoard = {$board->getId()}");
        $qb->groupBy("a.id");
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }

    public function insertMarkDistribution(EmployeeSetup $setup , EmployeeBoard $board , $entities)
    {

        $em = $this->_em;
        foreach ($entities as $parameter):
            if(!empty($parameter->getChildren())){
                foreach ($parameter->getChildren() as $activity):
                    if(!empty($activity->getChildren())){
                        foreach ($activity->getChildren() as $attribute):
                            $exist = $this->findOneBy(array('employeeBoard'=> $board,'attribute'=>$attribute));
                            if(empty($exist)){
                                $entity = new EmployeeBoardAttribute();
                                $entity->setEmployeeBoard($board);
                                $entity->setParameter($parameter);
                                $entity->setActivity($activity);
                                $entity->setAttribute($attribute);
                                $entity->setActualMark(0);
                                $entity->setMark($attribute->getMark());
                                $em->persist($entity);
                                $em->flush();
                            }

                        endforeach;
                    }
                endforeach;
            }
        endforeach;
        $this->updateSalesProcess($setup,$board);
        $subAttrs = $this->groupByAttributeMarks($board);
        foreach ($subAttrs as $sub):
            $exist = $this->findOneBy(array('employeeBoard'=> $board,'attribute' => $sub['parentId']));
            if(!empty($exist)){
                $exist->setActualMark($sub['mark']);
                $em->persist($exist);
                $em->flush();
            }
        endforeach;

    }

    public function updateSalesProcess(EmployeeSetup $setup,EmployeeBoard $board)
    {
        $em = $this->_em;
        $entities = "";
        $arae = $setup->getEmployee()->getArea();
        if($setup->getEmployee()->getArea() == "Upozila"){
            $entities = $em->getRepository(EmployeeSetup::class)->getItemWiseSalesAmount($setup->getId());
        }else if($arae == "Regional"){
            $entities = $em->getRepository(EmployeeSetup::class)->getRegionalItemWiseSalesAmount($setup);
        }else if($arae == "Zonal"){
            $entities = $em->getRepository(EmployeeSetup::class)->getZonalItemWiseSalesAmount($setup);
        }
        $locations = $em->getRepository(EmployeeSetup::class)->processSetup($setup->getId());
        $arrs = array();
        if(!empty($locations)){
            foreach ($locations as $location){
                $arrs[] = $location['upozila'];
            }
        }


        $orders = $em->getRepository(AgentOrder::class)->getLocationWiseTotalProductSales($arrs);


        if(!empty($entities)){

            foreach ($entities as $parameter):


                $distribution = $em->getRepository(MarkChart::class)->find($parameter['id']);
                $exist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard'=> $board,'markDistribution'=> $parameter['id']));
                if(empty($exist)){
                    $entity = new EmployeeBoardSubAttribute();
                    $entity->setEmployeeBoard($board);
                    $entity->setMarkDistribution($distribution);
                    $entity->setSalesTargetAmount($parameter['amount']);
                    if(isset($orders[$parameter['id']]) and !empty($orders[$parameter['id']])){
                        $entity->setSalesAmount($orders[$parameter['id']]['amount']);
                    }
                    $mark = $this->salesTargetCalculation($entity->getSalesTargetAmount(),$entity->getSalesAmount());
                    $entity->setMark($mark);
                    $em->persist($entity);

                }else{

                    $exist->setSalesTargetAmount($parameter['amount']);
                    if(isset($orders[$parameter['id']]) and !empty($orders[$parameter['id']])){
                        $exist->setSalesAmount($orders[$parameter['id']]['amount']);
                    }
                    $mark = $this->salesTargetCalculation($exist->getSalesTargetAmount(),$exist->getSalesAmount());
                    $exist->setMark($mark);
                }
                $em->flush();

            endforeach;
        }


    }

    public function groupByAttributeMarks(EmployeeBoard $board)
    {
        $em = $this->_em;
        $qb = $em->createQueryBuilder();
        $qb->from(EmployeeBoardSubAttribute::class,'e');
        $qb->leftJoin('e.markDistribution','d');
        $qb->leftJoin('d.parent','p');
        $qb->select('p.id as parentId','SUM(e.mark) as mark');
        $qb->groupBy('parentId');
        $qb->where("e.employeeBoard = {$board->getId()}");
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }

    public function salesTargetCalculation($targetAmount,$salesAmount)
    {
        $action = (($salesAmount * 100 )/$targetAmount);

        if($action >= 100) {
            return 5;
        }elseif ($action < 100 and $action >= 90) {
            return 4;
        }elseif ($action < 90 and $action >= 70) {
            return 3;
        }else {
            return 0;
        }
    }



}
