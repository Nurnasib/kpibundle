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
class EmployeeBoardAttributeRepository extends EntityRepository
{

    public function EmployeeBoardMarks(EmployeeBoard $board )
    {

        $qb = $this->createQueryBuilder('e');
        $qb->select('e.id as id','a.id as attribute','m.id as markDistributionId','e.mark as mark','e.targetAmount as targetAmount','e.targetAchievement as targetAchievement');
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
                            if(empty($exist) and !empty($board->getEmployeeSetup()->getEmployee()->getReportMode())){
                                $markChartAttribute = $em->getRepository(MarkChart::class)->findUserMarkAttribute($board->getEmployeeSetup()->getEmployee()->getReportMode()->getId(),$attribute->getId());
                                if($markChartAttribute){
                                    $entity = new EmployeeBoardAttribute();
                                    $entity->setEmployeeBoard($board);
                                    $entity->setParameter($parameter);
                                    $entity->setActivity($activity);
                                    $entity->setAttribute($attribute);
                                    $entity->setActualMark($attribute->getMark());
                                    $em->persist($entity);
                                    $em->flush();
                                }
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
                $exist->setActualMark(5);
                $em->persist($exist);
                $em->flush();
            }
        endforeach;

    }

    public function updateSalesProcess(EmployeeSetup $setup,EmployeeBoard $board)
    {
        $em = $this->_em;
        $entities = "";
        $locations = $board->getEmployeeSetup()->getEmployee()->getDistrict();
        $arrs = array();
        if(!empty($locations)){
            foreach ($locations as $location){
                $arrs[] = $location->getId();
            }
        }

        $entities = $em->getRepository(LocationSalesTarget::class)->getLocationWiseTotalProductSalesTarget($arrs);
        $orders = $em->getRepository(AgentOrder::class)->getLocationWiseTotalProductSales($arrs);

        if(!empty($entities)){

            foreach ($entities as $parameter):
                $entity = new EmployeeBoardSubAttribute();
                $distribution = $em->getRepository(MarkChart::class)->find($parameter['id']);
                $exist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard'=> $board,'markDistribution'=> $parameter['id']));
                if($exist){
                    $entity = $exist;
                }
                    $entity->setEmployeeBoard($board);
                    $entity->setMarkDistribution($distribution);
                    $entity->setTargetQuantity($parameter['quantity']);
                    if(isset($orders[$parameter['id']]) and !empty($orders[$parameter['id']])){
                        $entity->setSalesQuantity($orders[$parameter['id']]['quantity']);
                    }
                    $mark = $this->salesTargetCalculation($entity->getTargetQuantity(),$entity->getSalesQuantity());
                    $entity->setMark($mark);
                    $em->persist($entity);
                    $em->flush();

//                $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(['employeBoard'=>$board,'markDistribution' => $distribution]);
                $employeeBoardAttribute = $this->findOneBy(['employeeBoard'=>$board,'attribute'=>$distribution]);
                if($employeeBoardAttribute){
                    $employeeBoardAttribute->setMark($entity->getMark());
                    $em->persist($employeeBoardAttribute);
                    $em->flush();
                }

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

    public function salesTargetCalculation($target,$sales)
    {
        if($target > 0){
            $action = (($sales * 100 )/$target);
            if($action >= 100) {
                return 5;
            }elseif ($action < 100 and $action >= 90) {
                return 4;
            }elseif ($action < 90 and $action >= 80) {
                return 3;
            }elseif ($action < 80 and $action >= 70) {
                return 2;
            }elseif ($action < 70 and $action >= 60) {
                return 1;
            }else {
                return 0;
            }

        }

    }



}
