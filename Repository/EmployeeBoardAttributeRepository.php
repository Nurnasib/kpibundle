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

use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Terminalbd\KpiBundle\Entity\AgentDocSaleCollection;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\AgentOutstanding;
use Terminalbd\KpiBundle\Entity\DistrictOrder;
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
        $qb->join("e.parameter",'p');
        $qb->join("e.activity",'a');
        $qb->join("e.attribute",'at');
        $qb->join("e.employeeBoard",'eb');
        $qb->leftJoin("e.markDistribution",'m');
        $qb->where("e.employeeBoard = :employeeBoard");
        $qb->setParameter("employeeBoard", $board);
        $qb->orderBy('p.ordering','ASC');
        $qb->addOrderBy('a.ordering','ASC');
        $qb->addOrderBy('at.ordering','ASC');
        $result = $qb->getQuery()->getResult();

        return $result;
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

    public function insertMarkDistribution( EmployeeBoard $board, $entities)
    {

        $em = $this->_em;
        foreach ($entities as $parameter):
            if(!empty($parameter->getChildren())){
                foreach ($parameter->getChildren() as $activity):
                    if(!empty($activity->getChildren())){
                        foreach ($activity->getChildren() as $attribute):
                            $exist = $this->findOneBy(array('employeeBoard'=> $board,'attribute'=>$attribute));
                            if(empty($exist) and !empty($board->getEmployee()->getReportMode())){
                                $markChartAttribute = $em->getRepository(MarkChart::class)->findUserMarkAttribute($board->getEmployee()->getReportMode()->getId(),$attribute->getId());
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
        $this->updateSalesProcess($board);
        $subAttrs = $this->groupByAttributeMarks($board);
        foreach ($subAttrs as $sub):
            $exist = $this->findOneBy(array('employeeBoard'=> $board,'attribute' => $sub['parentId']));
            if(!empty($exist)){
                $exist->setActualMark(5);
                $em->persist($exist);
                $em->flush();
            }
        endforeach;

        $this->updateIndividualSales($board);
        $this->updateOutStandingLimit($board);
        $this->updateDocSales($board);
    }

    public function updateSalesProcess(EmployeeBoard $board)
    {
        $em = $this->_em;
        $entities = "";
        $locations = $board->getEmployee()->getDistrict();
        $arrs = array();
        if(!empty($locations)){
            foreach ($locations as $location){
                $arrs[] = $location->getId();
            }
        }

        $entities = $em->getRepository(DistrictOrder::class)->getLocationWiseTotalProductSalesTarget($arrs, $board->getYear(), $board->getMonth());

        if(!empty($entities)){
            $totalAchivementMark=0;
            $totalQuantity=0;
            $totalTargetQuantity=0;
            $totalActualMark=0;
            foreach ($entities as $parameter):

                $totalAchivementMark=$totalAchivementMark+$parameter['salesMark'];
                $totalQuantity=$totalQuantity+$parameter['quantity'];
                $totalTargetQuantity=$totalTargetQuantity+$parameter['targetQuantity'];

                $entity = new EmployeeBoardSubAttribute();
                $distribution = $em->getRepository(MarkChart::class)->find($parameter['id']);

                $totalActualMark=$totalActualMark+$distribution->getMark();

                $exist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard'=> $board,'markDistribution'=> $parameter['id']));
                if($exist){
                    $entity = $exist;
                }
                    $entity->setEmployeeBoard($board);
                    $entity->setMarkDistribution($distribution);
                    $entity->setTargetQuantity($parameter['targetQuantity']);
                    $entity->setSalesQuantity($parameter['quantity']);
                    /*if(isset($orders[$parameter['id']]) and !empty($orders[$parameter['id']])){
                        $entity->setSalesQuantity($orders[$parameter['id']]['quantity']);
                    }*/
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

                $growthDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode'=>'growth', 'slug'=>'growth-'.$distribution->getSlug()));

                $growthEntity = new EmployeeBoardSubAttribute();

                $growthExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard'=> $board,'markDistribution'=> $growthDistribution));
                if($growthExist){
                    $growthEntity = $growthExist;
                }
                $growthEntity->setEmployeeBoard($board);
                $growthEntity->setMarkDistribution($growthDistribution);
                $growthEntity->setTargetQuantity($parameter['salesGrouthPreviousQuantity']);
                $growthEntity->setSalesQuantity($parameter['salesGrouthCurrentQuantity']);

                $growthEntity->setMark($this->salesGrowthCalculation($distribution->getSlug(), $parameter['salesGrouthPreviousQuantity'], $parameter['salesGrouthCurrentQuantity'] )[$growthDistribution->getSlug()]);
                $em->persist($growthEntity);
                $em->flush();


//                $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(['employeBoard'=>$board,'markDistribution' => $distribution]);
                $employeeBoardAttributeForGrowth = $this->findOneBy(['employeeBoard'=>$board,'attribute'=>$growthDistribution]);
                if($employeeBoardAttributeForGrowth){
                    $employeeBoardAttributeForGrowth->setMark($growthEntity->getMark());
                    $em->persist($employeeBoardAttributeForGrowth);
                    $em->flush();
                }

            endforeach;

            $discritAchivementDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug'=>'district-achievement'));
                $employeeBoardAttributeForDistrictAchivement = $this->findOneBy(['employeeBoard'=>$board,'attribute'=>$discritAchivementDistribution]);
                if($employeeBoardAttributeForDistrictAchivement){
                    $employeeBoardAttributeForDistrictAchivement->setTargetAchievement($totalQuantity);
                    $employeeBoardAttributeForDistrictAchivement->setTargetAmount($totalTargetQuantity);
                    $employeeBoardAttributeForDistrictAchivement->setMark($this->salesDistrictAchivementCalculation($totalActualMark, $totalAchivementMark));
                    $em->persist($employeeBoardAttributeForDistrictAchivement);
                    $em->flush();
                }

                $regionalAchivementDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug'=>'regional-achievement'));
                $employeeBoardAttributeForRegionalAchivement = $this->findOneBy(['employeeBoard'=>$board,'attribute'=>$regionalAchivementDistribution]);
                if($employeeBoardAttributeForRegionalAchivement){
                    $employeeBoardAttributeForRegionalAchivement->setTargetAchievement($totalQuantity);
                    $employeeBoardAttributeForRegionalAchivement->setTargetAmount($totalTargetQuantity);
                    $employeeBoardAttributeForRegionalAchivement->setMark($this->salesRegionalAchivementCalculation($totalActualMark, $totalAchivementMark));
                    $em->persist($employeeBoardAttributeForRegionalAchivement);
                    $em->flush();
                }
        }

    }

    public function updateIndividualSales(EmployeeBoard $board)
    {
        $em = $this->_em;
        $employee = $board->getEmployee();

        $getEmployeesByLineManager = $this->_em->getRepository(User::class)->findBy(['lineManager'=>$employee, 'enabled'=>1]);
        $employeeArrs = array();
        foreach ($getEmployeesByLineManager as $childEmployee){
            if(!empty($childEmployee)){
                $employeeArrs[] = $childEmployee->getId();
            }
        }
        $salesDistribution = $em->getRepository(MarkChart::class)->findBy(array('salesMode'=>'feed','status'=>1));

        $disdributionArrs = array();
        foreach ($salesDistribution as $saleDistribution){
            $disdributionArrs[] = $saleDistribution->getId();
        }


        $entities = $this->individualTeamMemberMarks($employeeArrs, $disdributionArrs, $board->getYear(), $board->getMonth());

        $individualTeamDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug'=>'individual-team-members-achievement','status'=>1));

        $individualEntity = new EmployeeBoardSubAttribute();

        $individualTeamDistributionExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard'=> $board,'markDistribution'=> $individualTeamDistribution));

        if($individualTeamDistributionExist){
            $individualEntity = $individualTeamDistributionExist;
        }

        $individualEntity->setEmployeeBoard($board);
        $individualEntity->setMarkDistribution($individualTeamDistribution);

        $individualEntity->setMark($this->individualTeamMemberCalculation($entities['actualMark'], $entities['mark'] ));
        $em->persist($individualEntity);
        $em->flush();


        $employeeBoardAttributeForIndividualTeam = $this->findOneBy(['employeeBoard'=>$board,'attribute'=>$individualTeamDistribution]);
        if($employeeBoardAttributeForIndividualTeam){
            $employeeBoardAttributeForIndividualTeam->setMark($individualEntity->getMark());
            $em->persist($employeeBoardAttributeForIndividualTeam);
            $em->flush();
        }

    }

    public function updateOutStandingLimit(EmployeeBoard $board)
    {
        $em = $this->_em;

        $locations = $board->getEmployee()->getDistrict();
        $arrs = array();
        if(!empty($locations)){
            foreach ($locations as $location){
                $arrs[] = $location->getId();
            }
        }

        $outstandingAmount = $em->getRepository(AgentOutstanding::class)->getLocationWiseTotalOutstanding($arrs, $board->getYear(), $board->getMonth());
        $outstandingDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug'=>'outstanding-limit-actual-feed'));
        $employeeBoardAttributeForOutStandingLimit = $this->findOneBy(['employeeBoard'=>$board,'attribute'=>$outstandingDistribution]);
        if($employeeBoardAttributeForOutStandingLimit){
            $employeeBoardAttributeForOutStandingLimit->setMark($this->outstandingLimitCalculation($outstandingAmount['outstanding']));
            $em->persist($employeeBoardAttributeForOutStandingLimit);
            $em->flush();
        }

    }

    public function updateDocSales(EmployeeBoard $board)
    {
        $em = $this->_em;

        $locations = $board->getEmployee()->getDistrict();
        $arrs = array();
        if(!empty($locations)){
            foreach ($locations as $location){
                $arrs[] = $location->getId();
            }
        }

        $docSalesObj = $em->getRepository(AgentDocSaleCollection::class)->getLocationWiseTotalDocSales($arrs, $board->getYear(), $board->getMonth());

        $docSalesDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug'=>'doc-sales-vs-collection'));

        $employeeBoardAttributeForDocSales = $this->findOneBy(['employeeBoard'=>$board,'attribute'=>$docSalesDistribution]);

        if($employeeBoardAttributeForDocSales){
            $employeeBoardAttributeForDocSales->setMark($this->docSalesCollectionCalculation($docSalesObj['totalCollectionAmount'], $docSalesObj['totalSalesAmount']));
            $em->persist($employeeBoardAttributeForDocSales);
            $em->flush();
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

    public function individualTeamMemberMarks($employees, $attributes, $year, $month)
    {
        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employeeBoard','ed');
        $qb->join('ed.employee','em');
        $qb->join('e.attribute','att');
        $qb->select('SUM(e.mark) as mark', 'SUM(e.actualMark) as actualMark');
        $qb->where('em.id IN (:employee)')->setParameter('employee',$employees);
        $qb->andWhere('att.id IN (:attribute)')->setParameter('attribute',$attributes);
        $qb->andWhere('ed.year =:year')->setParameter('year',$year);
        $qb->andWhere('ed.month =:month')->setParameter('month',$month);
//        $qb->groupBy('att.id');
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result;
    }

    public function getIndividualTeamMemberMarks($employees, $attributes, $year, $month)
    {
        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employeeBoard','ed');
        $qb->join('ed.employee','em');
        $qb->join('e.attribute','att');
        $qb->select('SUM(e.mark) as mark', 'SUM(e.actualMark) as actualMark', 'SUM(e.targetAmount) AS targetAmount', 'SUM(e.targetAchievement) AS targetAchievement');
        $qb->addSelect('em.name AS employeeName');
        $qb->where('em.id IN (:employee)')->setParameter('employee',$employees);
        $qb->andWhere('att.id IN (:attribute)')->setParameter('attribute',$attributes);
        $qb->andWhere('ed.year =:year')->setParameter('year',$year);
        $qb->andWhere('ed.month =:month')->setParameter('month',$month);
        $qb->groupBy('em.id');
        $results = $qb->getQuery()->getArrayResult();

        $data = [];
        foreach ($results as $result){
            $data[$result['employeeName']] = [
                'mark' => $result['mark'],
                'actualMark' => $result['actualMark'],
                'targetAmount' => $result['targetAmount'],
                'targetAchievement' => $result['targetAchievement'],
            ];
        }
        return $data;
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

    public function individualTeamMemberCalculation($target,$sales)
    {
        if($target > 0){
            $action = (($sales * 100 )/$target);
            if($action >= 100) {
                return 4;
            }elseif ($action < 100 and $action >= 50) {
                return 2;
            }elseif ($action < 50 and $action >= 1) {
                return 1;
            }else {
                return 0;
            }

        }

    }

    private function salesGrowthCalculation($productSlug,$previousValue,$currentValue)
    {
        $returnValue=[];


        if($productSlug){

            if($productSlug == 'broiler') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue>0?(($increase/$previousValue)*100):0;
                $growthSlug = 'growth-'.$productSlug;
                if($action >= 15) {
                    $returnValue[$growthSlug] = 5;
                }elseif ($action < 15 and $action >= 10) {
                    $returnValue[$growthSlug] = 4;
                }elseif ($action < 10 and $action >= 5) {
                    $returnValue[$growthSlug] = 3;
                }elseif ($action < 5 and $action >= 3) {
                    $returnValue[$growthSlug] = 2;
                }elseif ($action < 3 and $action >= 1) {
                    $returnValue[$growthSlug] = 1;
                }else {
                    $returnValue[$growthSlug] = 0;
                }

            }elseif ($productSlug == 'sonali') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue>0?(($increase/$previousValue)*100):0;
                $growthSlug = 'growth-'.$productSlug;
                if($action >= 8) {
                    $returnValue[$growthSlug] = 5;
                }elseif ($action < 8 and $action >= 7) {
                    $returnValue[$growthSlug] = 4;
                }elseif ($action < 7 and $action >= 6) {
                    $returnValue[$growthSlug] = 3;
                }elseif ($action < 6 and $action >= 5) {
                    $returnValue[$growthSlug] = 2;
                }elseif ($action < 5 and $action >= 1) {
                    $returnValue[$growthSlug] = 1;
                }else {
                    $returnValue[$growthSlug] = 0;
                }
            }elseif ($productSlug == 'layer') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue>0?(($increase/$previousValue)*100):0;
                $growthSlug = 'growth-'.$productSlug;
                if($action >= 10) {
                    $returnValue[$growthSlug] = 5;
                }elseif ($action < 10 and $action >= 8) {
                    $returnValue[$growthSlug] = 4;
                }elseif ($action < 8 and $action >= 6) {
                    $returnValue[$growthSlug] = 3;
                }elseif ($action < 6 and $action >= 4) {
                    $returnValue[$growthSlug] = 2;
                }elseif ($action < 4 and $action >= 1) {
                    $returnValue[$growthSlug] = 1;
                }else {
                    $returnValue[$growthSlug] = 0;
                }
            }elseif ($productSlug == 'fish') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue>0?(($increase/$previousValue)*100):0;
                $growthSlug = 'growth-'.$productSlug;
                if($action >= 20) {
                    $returnValue[$growthSlug] = 5;
                }elseif ($action < 20 and $action >= 15) {
                    $returnValue[$growthSlug] = 4;
                }elseif ($action < 15 and $action >= 10) {
                    $returnValue[$growthSlug] = 3;
                }elseif ($action < 10 and $action >= 5) {
                    $returnValue[$growthSlug] = 2;
                }elseif ($action < 5 and $action >= 1) {
                    $returnValue[$growthSlug] = 1;
                }else {
                    $returnValue[$growthSlug] = 0;
                }
            }elseif ($productSlug == 'cattle') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue>0?(($increase/$previousValue)*100):0;
                $growthSlug = 'growth-'.$productSlug;
                if($action >= 25) {
                    $returnValue[$growthSlug] = 5;
                }elseif ($action < 25 and $action >= 20) {
                    $returnValue[$growthSlug] = 4;
                }elseif ($action < 20 and $action >= 15) {
                    $returnValue[$growthSlug] = 3;
                }elseif ($action < 15 and $action >= 10) {
                    $returnValue[$growthSlug] = 2;
                }elseif ($action < 10 and $action >= 1) {
                    $returnValue[$growthSlug] = 1;
                }else {
                    $returnValue[$growthSlug] = 0;
                }
            }else {
                return 0;
            }
           return $returnValue;
        }

    }



    public function salesDistrictAchivementCalculation($target, $achivement)
    {
        if($target > 0){
            $action = (($achivement * 100 )/$target);
            if($action >= 100) {
                return 4;
            }elseif ($action < 100 and $action >= 50) {
                return 3;
            }elseif ($action < 50 and $action >= 1) {
                return 1;
            }else {
                return 0;
            }

        }

    }

    public function salesRegionalAchivementCalculation($target, $achivement)
    {
        if($target > 0){
            $action = (($achivement * 100 )/$target);
            if($action >= 100) {
                return 5;
            }elseif ($action < 100 and $action >= 50) {
                return 3;
            }elseif ($action < 50 and $action >= 1) {
                return 2;
            }else {
                return 0;
            }

        }

    }

    public function outstandingLimitCalculation($outstandingValue)
    {

        if($outstandingValue){
            if($outstandingValue >= 500000){
                return 0;
            }elseif ($outstandingValue >= 400000 && $outstandingValue < 500000){
                return 1;
            }elseif ($outstandingValue >= 300000 && $outstandingValue < 400000){
                return 2;
            }elseif ($outstandingValue >= 200000 && $outstandingValue < 300000){
                return 3;
            }elseif ($outstandingValue < 200000){
                return 4;
            }
        }
        return 0;

    }

    public function docSalesCollectionCalculation($collectionAmount, $salesAmount)
    {

        if($salesAmount>0){
            $action = (($collectionAmount * 100 )/$salesAmount);
            if($action >= 100) {
                return 5;
            }elseif ($action < 100 and $action >= 90) {
                return 4;
            }elseif ($action < 90 and $action >= 85) {
                return 3;
            }elseif ($action < 85 and $action >= 75) {
                return 2;
            }elseif ($action < 75) {
                return 1;
            }
        }
        return 0;

    }




}
