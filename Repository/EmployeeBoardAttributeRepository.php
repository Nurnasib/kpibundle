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
use Terminalbd\CrmBundle\Entity\AntibioticFreeFarm;
use Terminalbd\CrmBundle\Entity\ChickLifeCycle;
use Terminalbd\CrmBundle\Entity\CostBenefitAnalysisForLessCostingFarm;
use Terminalbd\CrmBundle\Entity\DiseaseMapping;
use Terminalbd\CrmBundle\Entity\FarmerTrainingReport;
use Terminalbd\CrmBundle\Entity\FcrDetails;
use Terminalbd\CrmBundle\Entity\LayerLifeCycle;
use Terminalbd\CrmBundle\Entity\LayerPerformanceDetails;
use Terminalbd\CrmBundle\Entity\NewFarmerIntroduce\FarmerIntroduceDetails;
use Terminalbd\CrmBundle\Entity\NewFarmerTouch\FarmerTouchReport;
use Terminalbd\KpiBundle\Entity\AgentCategory;
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
    public function EmployeeBoardMarks(EmployeeBoard $board)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->join("e.parameter", 'p');
        $qb->join("e.activity", 'a');
        $qb->join("e.attribute", 'at');
        $qb->join("e.employeeBoard", 'eb');
        $qb->leftJoin("e.markDistribution", 'm');
        $qb->where("e.employeeBoard = :employeeBoard");
        $qb->setParameter("employeeBoard", $board);
        $qb->orderBy('p.ordering', 'ASC');
        $qb->addOrderBy('a.ordering', 'ASC');
        $qb->addOrderBy('at.ordering', 'ASC');
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function EmployeeBoardSummaryReport(EmployeeBoard $board)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->select('SUM(e.actualMark) as actualMark', 'SUM(e.mark) as mark', 'a.name as activity');
        $qb->join("e.activity", 'a');
        $qb->where("e.employeeBoard = {$board->getId()}");
        $qb->groupBy("a.id");
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }

    public function insertMarkDistribution(EmployeeBoard $board, $entities)
    {

        $em = $this->_em;
        foreach ($entities as $parameter):
            if (!empty($parameter->getChildren())) {
                foreach ($parameter->getChildren() as $activity):
                    if (!empty($activity->getChildren())) {
                        foreach ($activity->getChildren() as $attribute):
                            $exist = $this->findOneBy(array('employeeBoard' => $board, 'attribute' => $attribute));
                            if (empty($exist) and !empty($board->getEmployee()->getReportMode())) {
                                $markChartAttribute = $em->getRepository(MarkChart::class)->findUserMarkAttribute($board->getEmployee()->getReportMode()->getId(), $attribute->getId());
                                if ($markChartAttribute) {
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
            $exist = $this->findOneBy(array('employeeBoard' => $board, 'attribute' => $sub['parentId']));
            if (!empty($exist)) {
                $exist->setActualMark(5);
                $em->persist($exist);
                $em->flush();
            }
        endforeach;

        $this->updateIndividualSales($board);
        $this->updateOutStandingLimit($board);
        $this->updateDocSales($board);
        $this->updateCategoryUpgrade($board);

        $filterBy = [];
        $filterBy['employeeId'] = $board->getEmployee()->getId();
        $filterBy['monthStart'] = date("{$board->getYear()}-m-01", strtotime($board->getMonth()));
        $filterBy['monthEnd'] = date("{$board->getYear()}-m-t", strtotime($board->getMonth()));

        if ($board->getEmployee()->getReportMode()->getSlug() == 'poultry-service') {
            $this->updateEvaluationCriteriaPoultry($board, $filterBy);
        } elseif ($board->getEmployee()->getReportMode()->getSlug() == 'aqua-service') {
            $this->updateEvaluationCriteriaAqua($board, $filterBy);
        } elseif ($board->getEmployee()->getReportMode()->getSlug() == 'cattle-service') {
            $this->updateEvaluationCriteriaCattle($board, $filterBy);
        }
        
        $this->agentSalesGrowth($board);
    }

    public function agentSalesGrowth(EmployeeBoard $board)
    {
        $em = $this->_em;
        $prevYear = $board->getYear() - 1;
        $twentyPercentGrowthAgents = [];

        $locations = $board->getEmployee()->getDistrict();
        $locationsId = [];
        if (!empty($locations)) {
            foreach ($locations as $location) {
                $locationsId[] = $location->getId();
            }
        }
        $agentsWithSalesQuantity = $em->getRepository(AgentOrder::class)->getAgentWithSalesQuantity($board, $locationsId);

        $commonAgentBetweenYears = array_intersect_key($agentsWithSalesQuantity[$board->getYear()], $agentsWithSalesQuantity[$prevYear]);  //Common agents and SalesQuantity(Current Year)

        foreach ($commonAgentBetweenYears as $agentId => $currentYearAgentSalesQty) {
            if ($agentsWithSalesQuantity[$prevYear][$agentId]) {
                if ($currentYearAgentSalesQty > $agentsWithSalesQuantity[$prevYear][$agentId]) {
                    $growthPercentage = (($currentYearAgentSalesQty - $agentsWithSalesQuantity[$prevYear][$agentId]) * 100) / $agentsWithSalesQuantity[$prevYear][$agentId];
                    if ($growthPercentage >= 20) {
                        $twentyPercentGrowthAgents[] = $agentId;
                    }
                }
            }
        }
        $this->agentSalesGrowthCalculation($commonAgentBetweenYears, $twentyPercentGrowthAgents);
    }

    private function agentSalesGrowthCalculation($commonAgentBetweenYears, $twentyPercentGrowthAgents)
    {
        if ($board->getEmployee()->getReportMode()->getSlug() == 'aqua-service'){
            $agentSalesDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'aqua-develop-existing-customer-sales-volume'));
            $employeeBoardAttributeForAgentSalesGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $agentSalesDistribution]);

            if ($employeeBoardAttributeForAgentSalesGrowth) {
                $mark = $this->twentyPercentGrowthAgentNumberPercentageCalculationAqua($commonAgentBetweenYears, $twentyPercentGrowthAgents);
                $employeeBoardAttributeForAgentSalesGrowth->setMark($mark);
                $em->persist($employeeBoardAttributeForAgentSalesGrowth);
                $em->flush();
            }
        } elseif ($board->getEmployee()->getReportMode()->getSlug() == 'cattle-service'){
            $agentSalesDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'cattle-develop-existing-customer-sales-volume'));
            $employeeBoardAttributeForAgentSalesGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $agentSalesDistribution]);

            if ($employeeBoardAttributeForAgentSalesGrowth) {
                $mark = $this->twentyPercentGrowthAgentNumberPercentageCalculationCattle($commonAgentBetweenYears, $twentyPercentGrowthAgents);
                $employeeBoardAttributeForAgentSalesGrowth->setMark($mark);
                $em->persist($employeeBoardAttributeForAgentSalesGrowth);
                $em->flush();
            }
        } else{
            $agentSalesDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'develop-existing-customer-sales-volume'));
            $employeeBoardAttributeForAgentSalesGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $agentSalesDistribution]);

            if ($employeeBoardAttributeForAgentSalesGrowth) {
                $mark = $this->twentyPercentGrowthAgentNumberPercentageCalculation($commonAgentBetweenYears, $twentyPercentGrowthAgents);
                $employeeBoardAttributeForAgentSalesGrowth->setMark($mark);
                $em->persist($employeeBoardAttributeForAgentSalesGrowth);
                $em->flush();
            }
        }
    }

    private function twentyPercentGrowthAgentNumberPercentageCalculation($commonAgentBetweenYears, $twentyPercentGrowthAgents)
    {
        $agentNumberWithPercentage = (count($twentyPercentGrowthAgents) * 100) / count($commonAgentBetweenYears);

        if ($agentNumberWithPercentage >= 30) {
            return 3;
        } elseif ($agentNumberWithPercentage >= 10 && $agentNumberWithPercentage < 30) {
            return 2;
        } elseif ($agentNumberWithPercentage >= 1 && $agentNumberWithPercentage < 10) {
            return 1;
        } else {
            return 0;
        }
    }

    private function twentyPercentGrowthAgentNumberPercentageCalculationAqua($commonAgentBetweenYears, $twentyPercentGrowthAgents)
    {
        $agentNumberWithPercentage = (count($twentyPercentGrowthAgents) * 100) / count($commonAgentBetweenYears);

        if ($agentNumberWithPercentage >= 20) {
            return 2;
        } elseif ($agentNumberWithPercentage > 0 && $agentNumberWithPercentage < 20) {
            return 1;
        } else {
            return 0;
        }
    }

    private function twentyPercentGrowthAgentNumberPercentageCalculationCattle($commonAgentBetweenYears, $twentyPercentGrowthAgents)
    {
        $agentNumberWithPercentage = (count($twentyPercentGrowthAgents) * 100) / count($commonAgentBetweenYears);

        if ($agentNumberWithPercentage >= 50) {
            return 5;
        } elseif ($agentNumberWithPercentage >= 40 && $agentNumberWithPercentage < 50) {
            return 4;
        } elseif ($agentNumberWithPercentage >= 30 && $agentNumberWithPercentage < 40) {
            return 3;
        } elseif ($agentNumberWithPercentage >= 20 && $agentNumberWithPercentage < 30) {
            return 2;
        } elseif ($agentNumberWithPercentage > 0 && $agentNumberWithPercentage < 20) {
            return 1;
        } else {
            return 0;
        }
    }


    public function updateEvaluationCriteriaCattle(EmployeeBoard $board, $filterBy)
    {
//        dd(date("01-m-{$board->getYear()}",strtotime('February')));
        $em = $this->_em;
        $monthlyNewFarmerIntroduceReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-new-farm-introduce-report');
        if ($monthlyNewFarmerIntroduceReport) {
            $numberOfReports = 0;
            if ($numberOfReports >= 5){
                $monthlyNewFarmerIntroduceReport->setMark(5);
            }else{
                $monthlyNewFarmerIntroduceReport->setMark($numberOfReports);
            }
            $monthlyNewFarmerIntroduceReport->setTargetReport(5);
            $monthlyNewFarmerIntroduceReport->setAchieveReport($numberOfReports);
            $monthlyNewFarmerIntroduceReport->setTargetMark(5);
            $em->persist($monthlyNewFarmerIntroduceReport);
            $em->flush();
        }

        $monthlyLessCostingFarmReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-less-costing-farm-report');
        if ($monthlyLessCostingFarmReport) {
            $numberOfReports = 0;
            if ($numberOfReports >= 5){
                $monthlyLessCostingFarmReport->setMark(5);
            }else{
                $monthlyLessCostingFarmReport->setMark($numberOfReports);
            }
            $monthlyLessCostingFarmReport->setTargetReport(5);
            $monthlyLessCostingFarmReport->setAchieveReport($numberOfReports);
            $monthlyLessCostingFarmReport->setTargetMark(5);
            $em->persist($monthlyLessCostingFarmReport);
            $em->flush();
        }

        $monthlyAgentUpgradationReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-agent-upgradation-report');
        if ($monthlyAgentUpgradationReport) {
            $numberOfReports = 0;
            if ($numberOfReports >= 2){
                $monthlyAgentUpgradationReport->setMark(3);
            }else{
                $monthlyAgentUpgradationReport->setMark($numberOfReports);
            }
            $monthlyAgentUpgradationReport->setTargetReport(2);
            $monthlyAgentUpgradationReport->setAchieveReport($numberOfReports);
            $monthlyAgentUpgradationReport->setTargetMark(3);
            $em->persist($monthlyAgentUpgradationReport);
            $em->flush();
        }
    }

    public function updateEvaluationCriteriaAqua(EmployeeBoard $board, $filterBy)
    {
//        dd(date("01-m-{$board->getYear()}",strtotime('February')));
        $em = $this->_em;

        $monthlyLessCostingFarmReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-less-costing-farm-report');
        if ($monthlyLessCostingFarmReport) {
            $numberOfReports = 0;
            if ($numberOfReports >= 4){
                $monthlyLessCostingFarmReport->setMark(4);
            }else{
                $monthlyLessCostingFarmReport->setMark($numberOfReports);
            }
            $monthlyLessCostingFarmReport->setTargetReport(4);
            $monthlyLessCostingFarmReport->setAchieveReport($numberOfReports);
            $monthlyLessCostingFarmReport->setTargetMark(4);
            $em->persist($monthlyLessCostingFarmReport);
            $em->flush();
        }

        $monthlyNewFarmIntroduceReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-new-farm-introduce');
        if ($monthlyNewFarmIntroduceReport) {
//            (>=5) =5, 4=4, 3=3,2=2, 1=1,0=0
            $numberOfReports = 0;
            if ($numberOfReports >= 5){
                $monthlyNewFarmIntroduceReport->setMark(5);
            }else{
                $monthlyNewFarmIntroduceReport->setMark($numberOfReports);
            }
            $monthlyNewFarmIntroduceReport->setTargetReport(5);
            $monthlyNewFarmIntroduceReport->setAchieveReport($numberOfReports);
            $monthlyNewFarmIntroduceReport->setTargetMark(5);
            $em->persist($monthlyNewFarmIntroduceReport);
            $em->flush();
        }

        $monthlyNewAgentCreationReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-new-agent-creation-and-up-gradation-report');
        if ($monthlyNewAgentCreationReport) {
            $numberOfReports = 0;

            if ($numberOfReports >= 1){
                $monthlyNewAgentCreationReport->setMark(2);
            }else{
                $monthlyNewAgentCreationReport->setMark($numberOfReports);
            }
            $monthlyNewAgentCreationReport->setTargetReport(1);
            $monthlyNewAgentCreationReport->setAchieveReport($numberOfReports);
            $monthlyNewAgentCreationReport->setTargetMark(2);
            $em->persist($monthlyNewAgentCreationReport);
            $em->flush();
        }
    }

    public function updateEvaluationCriteriaPoultry(EmployeeBoard $board, $filterBy)
    {
//        dd(date("01-m-{$board->getYear()}",strtotime('February')));
        $em = $this->_em;

        $monthlyAntibioticFreeFarmReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-antibiotic-free-farm-report');
        if ($monthlyAntibioticFreeFarmReport) {
            $numberOfReports = (int)$em->getRepository(AntibioticFreeFarm::class)->getMonthlyAntibioticFreeFarmTotalReport($filterBy);

            if ($numberOfReports >= 4){
                $monthlyAntibioticFreeFarmReport->setMark(4);
            }else{
                $monthlyAntibioticFreeFarmReport->setMark($numberOfReports);
            }

            $monthlyAntibioticFreeFarmReport->setTargetReport(4);
            $monthlyAntibioticFreeFarmReport->setAchieveReport($numberOfReports);
            $monthlyAntibioticFreeFarmReport->setTargetMark(4);
            $em->persist($monthlyAntibioticFreeFarmReport);
            $em->flush();
        }

        $monthlyLessCostingFarmReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-less-costing-farm-report');
        if ($monthlyLessCostingFarmReport) {
            $numberOfReports = (int)$em->getRepository(CostBenefitAnalysisForLessCostingFarm::class)->getMonthlyLessCostingFarmOrSkillFarmDevelopTotalReport($filterBy);

            if ($numberOfReports >= 4){
                $monthlyLessCostingFarmReport->setMark(4);
            }else{
                $monthlyLessCostingFarmReport->setMark($numberOfReports);
            }

            $monthlyLessCostingFarmReport->setTargetReport(4);
            $monthlyLessCostingFarmReport->setAchieveReport($numberOfReports);
            $monthlyLessCostingFarmReport->setTargetMark(4);
            $em->persist($monthlyLessCostingFarmReport);
            $em->flush();
        }

        $monthlyNewFarmerIntroduceReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-new-farm-introduce-report');
        if ($monthlyNewFarmerIntroduceReport) {
            $numberOfReports = (int)$em->getRepository(FarmerIntroduceDetails::class)->getMonthlyNewFarmerIntroduceTotalReport($filterBy);
            if ($numberOfReports >= 5){
                $monthlyNewFarmerIntroduceReport->setMark(5);
            }else{
                $monthlyNewFarmerIntroduceReport->setMark($numberOfReports);
            }
            $monthlyNewFarmerIntroduceReport->setTargetReport(5);
            $monthlyNewFarmerIntroduceReport->setAchieveReport($numberOfReports);
            $monthlyNewFarmerIntroduceReport->setTargetMark(5);
            $em->persist($monthlyNewFarmerIntroduceReport);
            $em->flush();
        }
    }


    public function updateSalesProcess(EmployeeBoard $board)
    {
        $em = $this->_em;
        $entities = "";
        $locations = $board->getEmployee()->getDistrict();
        $arrs = array();
        if (!empty($locations)) {
            foreach ($locations as $location) {
                $arrs[] = $location->getId();
            }
        }

        $entities = $em->getRepository(DistrictOrder::class)->getLocationWiseTotalProductSalesTarget($arrs, $board->getYear(), $board->getMonth());
        if (!empty($entities)) {
            $totalAchivementMark = 0;
            $totalQuantity = 0;
            $totalTargetQuantity = 0;
            $totalActualMark = 0;
            foreach ($entities as $parameter):
                $totalAchivementMark = $totalAchivementMark + $parameter['salesMark'];
                $totalQuantity = $totalQuantity + $parameter['quantity'];
                $totalTargetQuantity = $totalTargetQuantity + $parameter['targetQuantity'];

                $entity = new EmployeeBoardSubAttribute();
                $distribution = $em->getRepository(MarkChart::class)->find($parameter['id']);
                /*                if ($board->getEmployee()->getReportMode()->getSlug() == 'poultry-service'){
                                    $distribution = $em->getRepository(MarkChart::class)->findOneBy(['salesMode' => 'feed', 'slug' => $distribution->getSlug() . '-poultry-service']);
                                    dump($distribution);
                                }*/
                $totalActualMark = $totalActualMark + $distribution->getMark();

                $exist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $parameter['id']));
                if ($exist) {
                    $entity = $exist;
                }
                $entity->setEmployeeBoard($board);
                $entity->setMarkDistribution($distribution);
                $entity->setTargetQuantity($parameter['targetQuantity']);
                $entity->setSalesQuantity($parameter['quantity']);

                /*if(isset($orders[$parameter['id']]) and !empty($orders[$parameter['id']])){
                    $entity->setSalesQuantity($orders[$parameter['id']]['quantity']);
                }*/

                if ($board->getEmployee()->getReportMode()->getSlug() == 'poultry-service') {
                    $distributionPoultry = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'feed', 'slug' => $distribution->getSlug() . '-poultry-service'));
                    $poultryFeedEntity = new EmployeeBoardAttribute();

                    $poultryFeedEntityExist = $em->getRepository(EmployeeBoardAttribute::class)->findOneBy(array('employeeBoard' => $board, 'attribute' => $distributionPoultry));
                    if ($poultryFeedEntityExist) {
                        $poultryFeedEntity = $poultryFeedEntityExist;
                    }

                    $mark = $this->salesTargetCalculationPoultryService($distribution->getSlug(), $entity->getTargetQuantity(), $entity->getSalesQuantity())[$distributionPoultry->getSlug()];

                    $entity->setMark($mark);
                    $em->persist($entity);
                    $poultryFeedEntity->setMark($mark);
                    $em->persist($poultryFeedEntity);
                    $em->flush();

//                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distribution]);
//                    if ($employeeBoardAttribute) {
//                        $employeeBoardAttribute->setMark($entity->getMark());
//                        $em->persist($employeeBoardAttribute);
//                        $em->flush();
//                    }

//                    Sales Growth
                    $growthDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'growth', 'slug' => 'growth-' . $distribution->getSlug() . '-poultry-service'));
                    $growthEntity = new EmployeeBoardSubAttribute();

                    $growthExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $growthDistribution));
                    if ($growthExist) {
                        $growthEntity = $growthExist;
                    }
                    $growthEntity->setEmployeeBoard($board);
                    $growthEntity->setMarkDistribution($growthDistribution);
                    $growthEntity->setTargetQuantity($parameter['salesGrouthPreviousQuantity']);
                    $growthEntity->setSalesQuantity($parameter['quantity']);

                    $growthEntity->setMark($this->salesGrowthCalculationPoultryService($distribution->getSlug(), $parameter['salesGrouthPreviousQuantity'], $parameter['salesGrouthCurrentQuantity'])[$growthDistribution->getSlug()]);
                    $em->persist($growthEntity);
                    $em->flush();

                    $employeeBoardAttributeForGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $growthDistribution]);
                    if ($employeeBoardAttributeForGrowth) {
                        $employeeBoardAttributeForGrowth->setMark($growthEntity->getMark());
                        $em->persist($employeeBoardAttributeForGrowth);
                        $em->flush();
                    }
//                    Sales Growth END

                } elseif ($board->getEmployee()->getReportMode()->getSlug() == 'aqua-service') {
                    $distributionAqua = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'feed', 'slug' => $distribution->getSlug() . '-aqua-service'));

                    $aquaFeedEntity = new EmployeeBoardAttribute();

                    $aquaFeedEntityExist = $em->getRepository(EmployeeBoardAttribute::class)->findOneBy(array('employeeBoard' => $board, 'attribute' => $distributionAqua));
                    if ($aquaFeedEntityExist) {
                        $aquaFeedEntity = $aquaFeedEntityExist;
                    }

                    $mark = $this->salesTargetCalculationAquaService($distribution->getSlug(), $entity->getTargetQuantity(), $entity->getSalesQuantity())[$distributionAqua->getSlug()];
                    $entity->setMark($mark);
                    $em->persist($entity);
                    $aquaFeedEntity->setMark($mark);
                    $em->persist($aquaFeedEntity);
                    $em->flush();

//                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distribution]);
//                    if ($employeeBoardAttribute) {
//                        $employeeBoardAttribute->setMark($entity->getMark());
//                        $em->persist($employeeBoardAttribute);
//                        $em->flush();
//                    }

                    //                    Sales Growth
                    $growthDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'growth', 'slug' => 'growth-' . $distribution->getSlug() . '-aqua-service'));
                    $growthEntity = new EmployeeBoardSubAttribute();

                    $growthExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $growthDistribution));
                    if ($growthExist) {
                        $growthEntity = $growthExist;
                    }
                    $growthEntity->setEmployeeBoard($board);
                    $growthEntity->setMarkDistribution($growthDistribution);
                    $growthEntity->setTargetQuantity($parameter['salesGrouthPreviousQuantity']);
                    $growthEntity->setSalesQuantity($parameter['quantity']);

                    $growthEntity->setMark($this->salesGrowthCalculationAquaService($distribution->getSlug(), $parameter['salesGrouthPreviousQuantity'], $parameter['salesGrouthCurrentQuantity'])[$growthDistribution->getSlug()]);
                    $em->persist($growthEntity);
                    $em->flush();

                    $employeeBoardAttributeForGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $growthDistribution]);
                    if ($employeeBoardAttributeForGrowth) {
                        $employeeBoardAttributeForGrowth->setMark($growthEntity->getMark());
                        $em->persist($employeeBoardAttributeForGrowth);
                        $em->flush();
                    }
//                    Sales Growth END

                } elseif ($board->getEmployee()->getReportMode()->getSlug() == 'cattle-service') {
                    $distributionCattle = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'feed', 'slug' => $distribution->getSlug() . '-cattle-service'));

                    $cattleFeedEntity = new EmployeeBoardAttribute();

                    $cattleFeedEntityExist = $em->getRepository(EmployeeBoardAttribute::class)->findOneBy(array('employeeBoard' => $board, 'attribute' => $distributionCattle));
                    if ($cattleFeedEntityExist) {
                        $cattleFeedEntity = $cattleFeedEntityExist;
                    }

                    $mark = $this->salesTargetCalculationCattleService($distribution->getSlug(), $entity->getTargetQuantity(), $entity->getSalesQuantity())[$distributionCattle->getSlug()];
                    $entity->setMark($mark);
                    $em->persist($entity);
                    $cattleFeedEntity->setMark($mark);
                    $em->persist($cattleFeedEntity);
                    $em->flush();

//                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distribution]);
//                    if ($employeeBoardAttribute) {
//                        $employeeBoardAttribute->setMark($entity->getMark());
//                        $em->persist($employeeBoardAttribute);
//                        $em->flush();
//                    }

                    //                    Sales Growth
                    $growthDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'growth', 'slug' => 'growth-' . $distribution->getSlug() . '-cattle-service'));
                    $growthEntity = new EmployeeBoardSubAttribute();

                    $growthExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $growthDistribution));
                    if ($growthExist) {
                        $growthEntity = $growthExist;
                    }
                    $growthEntity->setEmployeeBoard($board);
                    $growthEntity->setMarkDistribution($growthDistribution);
                    $growthEntity->setTargetQuantity($parameter['salesGrouthPreviousQuantity']);
                    $growthEntity->setSalesQuantity($parameter['quantity']);

                    $growthEntity->setMark($this->salesGrowthCalculationCattleService($distribution->getSlug(), $parameter['salesGrouthPreviousQuantity'], $parameter['salesGrouthCurrentQuantity'])[$growthDistribution->getSlug()]);
                    $em->persist($growthEntity);
                    $em->flush();

                    $employeeBoardAttributeForGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $growthDistribution]);
                    if ($employeeBoardAttributeForGrowth) {
                        $employeeBoardAttributeForGrowth->setMark($growthEntity->getMark());
                        $em->persist($employeeBoardAttributeForGrowth);
                        $em->flush();
                    }
//                    Sales Growth END

                } else {

                    $mark = $this->salesTargetCalculation($entity->getTargetQuantity(), $entity->getSalesQuantity());
                    $entity->setMark($mark);
                    $em->persist($entity);
                    $em->flush();

                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distribution]);
                    if ($employeeBoardAttribute) {
                        $employeeBoardAttribute->setMark($entity->getMark());
                        $em->persist($employeeBoardAttribute);
                        $em->flush();
                    }

                    $growthDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'growth', 'slug' => 'growth-' . $distribution->getSlug()));

                    $growthEntity = new EmployeeBoardSubAttribute();

                    $growthExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $growthDistribution));
                    if ($growthExist) {
                        $growthEntity = $growthExist;
                    }
                    $growthEntity->setEmployeeBoard($board);
                    $growthEntity->setMarkDistribution($growthDistribution);
                    $growthEntity->setTargetQuantity($parameter['salesGrouthPreviousQuantity']);
//                $growthEntity->setSalesQuantity($parameter['salesGrouthCurrentQuantity']);
                    $growthEntity->setSalesQuantity($parameter['quantity']);

                    $growthEntity->setMark($this->salesGrowthCalculation($distribution->getSlug(), $parameter['salesGrouthPreviousQuantity'], $parameter['salesGrouthCurrentQuantity'])[$growthDistribution->getSlug()]);
                    $em->persist($growthEntity);
                    $em->flush();


                    $employeeBoardAttributeForGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $growthDistribution]);
                    if ($employeeBoardAttributeForGrowth) {
                        $employeeBoardAttributeForGrowth->setMark($growthEntity->getMark());
                        $em->persist($employeeBoardAttributeForGrowth);
                        $em->flush();
                    }
                }

            endforeach;

            $discritAchivementDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'district-achievement'));
            $employeeBoardAttributeForDistrictAchivement = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $discritAchivementDistribution]);
            if ($employeeBoardAttributeForDistrictAchivement) {
                $employeeBoardAttributeForDistrictAchivement->setTargetAchievement($totalQuantity);
                $employeeBoardAttributeForDistrictAchivement->setTargetAmount($totalTargetQuantity);
                $employeeBoardAttributeForDistrictAchivement->setMark($this->salesDistrictAchivementCalculation($totalActualMark, $totalAchivementMark));
                $em->persist($employeeBoardAttributeForDistrictAchivement);
                $em->flush();
            }

            $regionalAchivementDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'regional-achievement'));
            $employeeBoardAttributeForRegionalAchivement = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $regionalAchivementDistribution]);
            if ($employeeBoardAttributeForRegionalAchivement) {
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

        $getEmployeesByLineManager = $this->_em->getRepository(User::class)->findBy(['lineManager' => $employee, 'enabled' => 1]);
        $employeeArrs = array();
        foreach ($getEmployeesByLineManager as $childEmployee) {
            if (!empty($childEmployee)) {
                $employeeArrs[] = $childEmployee->getId();
            }
        }
        $parameter = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'core-responsibilities', 'status' => 1));

        $entities = $this->individualTeamMemberMarks($employeeArrs, $parameter, $board->getYear(), $board->getMonth());
//        $individualTeamDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug'=>'individual-team-members-achievement','status'=>1));
        $individualTeamDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'team-members-mark-on-core-activities', 'status' => 1));

        $individualEntity = new EmployeeBoardSubAttribute();

        $individualTeamDistributionExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $individualTeamDistribution));

        if ($individualTeamDistributionExist) {
            $individualEntity = $individualTeamDistributionExist;
        }

        $individualEntity->setEmployeeBoard($board);
        $individualEntity->setMarkDistribution($individualTeamDistribution);

//        dd($this->individualTeamMemberCalculation($entities['actualMark'], $entities['mark'] ));
        $individualEntity->setMark($this->individualTeamMemberCalculation($entities['actualMark'], $entities['mark']));
        $em->persist($individualEntity);
        $em->flush();


        $employeeBoardAttributeForIndividualTeam = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $individualTeamDistribution]);
        if ($employeeBoardAttributeForIndividualTeam) {
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
        if (!empty($locations)) {
            foreach ($locations as $location) {
                $arrs[] = $location->getId();
            }
        }

        $outstandingAmount = $em->getRepository(AgentOutstanding::class)->getLocationWiseTotalOutstanding($arrs, $board->getYear(), $board->getMonth());
        $outstandingDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'outstanding-limit-actual-feed'));
        $employeeBoardAttributeForOutStandingLimit = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $outstandingDistribution]);
        if ($employeeBoardAttributeForOutStandingLimit) {
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
        if (!empty($locations)) {
            foreach ($locations as $location) {
                $arrs[] = $location->getId();
            }
        }

        $docSalesObj = $em->getRepository(AgentDocSaleCollection::class)->getLocationWiseTotalDocSales($arrs, $board->getYear(), $board->getMonth());

        $docSalesDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'doc-sales-vs-collection'));

        $employeeBoardAttributeForDocSales = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $docSalesDistribution]);

        if ($employeeBoardAttributeForDocSales) {
            $employeeBoardAttributeForDocSales->setMark($this->docSalesCollectionCalculation($docSalesObj['totalCollectionAmount'], $docSalesObj['totalSalesAmount']));
            $em->persist($employeeBoardAttributeForDocSales);
            $em->flush();
        }

    }

    public function updateCategoryUpgrade(EmployeeBoard $board)
    {
        $em = $this->_em;

        $gradeLetters = ['C', 'D'];
        $categoryUpgradationMark = $em->getRepository(AgentCategory::class)->getCategoryUpgradationMarks($board, $gradeLetters);
        $agentCategoryDistributions = $em->getRepository(MarkChart::class)->findBy(['slug' => ['minimum-50-d-category-agents-converts-to-c', 'minimum-50-c-category-agents-converts-to-b']]);

        foreach ($agentCategoryDistributions as $agentCategoryDistribution) {
            if ($agentCategoryDistribution->getSlug() == 'minimum-50-d-category-agents-converts-to-c') {
                $employeeBoardAttributeForCategoryUpgrade = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $agentCategoryDistribution]);
                if ($employeeBoardAttributeForCategoryUpgrade) {
                    $employeeBoardAttributeForCategoryUpgrade->setMark($categoryUpgradationMark['DtoUpperGrade']);
                    $em->persist($employeeBoardAttributeForCategoryUpgrade);
                    $em->flush();
                }
            } elseif ($agentCategoryDistribution->getSlug() == 'minimum-50-c-category-agents-converts-to-b') {
                $employeeBoardAttributeForCategoryUpgrade = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $agentCategoryDistribution]);
                if ($employeeBoardAttributeForCategoryUpgrade) {
                    $employeeBoardAttributeForCategoryUpgrade->setMark($categoryUpgradationMark['CtoUpperGrade']);
                    $em->persist($employeeBoardAttributeForCategoryUpgrade);
                    $em->flush();
                }
            }
        }
    }

    public function groupByAttributeMarks(EmployeeBoard $board)
    {
        $em = $this->_em;
        $qb = $em->createQueryBuilder();
        $qb->from(EmployeeBoardSubAttribute::class, 'e');
        $qb->leftJoin('e.markDistribution', 'd');
        $qb->leftJoin('d.parent', 'p');
        $qb->select('p.id as parentId', 'SUM(e.mark) as mark');
        $qb->groupBy('parentId');
        $qb->where("e.employeeBoard = {$board->getId()}");
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }

    public function individualTeamMemberMarks($employees, $parameter, $year, $month)
    {
        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employeeBoard', 'ed');
        $qb->join('ed.employee', 'em');
        $qb->join('e.parameter', 'parameter');
        $qb->select('SUM(e.mark) as mark', 'SUM(e.actualMark) as actualMark');
        $qb->where('em.id IN (:employee)')->setParameter('employee', $employees);
        $qb->andWhere('parameter.id = :parameter')->setParameter('parameter', $parameter);
        $qb->andWhere('ed.year =:year')->setParameter('year', $year);
        $qb->andWhere('ed.month =:month')->setParameter('month', $month);
//        $qb->groupBy('att.id');
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result;
    }

    public function getIndividualTeamMemberMarks($employees, $parameter, $year, $month)
    {
        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employeeBoard', 'ed');
        $qb->join('ed.employee', 'em');
        $qb->join('e.parameter', 'parameter');
        $qb->select('SUM(e.mark) as mark', 'SUM(e.actualMark) as actualMark', 'SUM(e.targetAmount) AS targetAmount', 'SUM(e.targetAchievement) AS targetAchievement');
        $qb->addSelect('em.name AS employeeName');
        $qb->where('em.id IN (:employee)')->setParameter('employee', $employees);
        $qb->andWhere('parameter.id = :parameter')->setParameter('parameter', $parameter);
        $qb->andWhere('ed.year =:year')->setParameter('year', $year);
        $qb->andWhere('ed.month =:month')->setParameter('month', $month);
        $qb->groupBy('em.id');
        $results = $qb->getQuery()->getArrayResult();

        $data = [];
        foreach ($results as $result) {
            $data[$result['employeeName']] = [
                'mark' => $result['mark'],
                'actualMark' => $result['actualMark'],
                'targetAmount' => $result['targetAmount'],
                'targetAchievement' => $result['targetAchievement'],
            ];
        }
        return $data;
    }

    public function salesTargetCalculation($target, $sales)
    {
        if ($target > 0) {
            $action = (($sales * 100) / $target);
            if ($action >= 100) {
                return 5;
            } elseif ($action < 100 and $action >= 90) {
                return 4;
            } elseif ($action < 90 and $action >= 80) {
                return 3;
            } elseif ($action < 80 and $action >= 70) {
                return 2;
            } else {
                return 1;
            }

        }
    }

    public function salesTargetCalculationPoultryService($productSlug, $target, $sales)
    {
        $returnValue = [];
        if ($target > 0) {
            $action = (($sales * 100) / $target);
            $slug = $productSlug . '-poultry-service';
            if ($productSlug == 'broiler') {
                if ($action >= 100) {
                    $returnValue[$slug] = 7;
                } elseif ($action < 100 and $action >= 90) {
                    $returnValue[$slug] = 6;
                } elseif ($action < 90 and $action >= 80) {
                    $returnValue[$slug] = 5;
                } elseif ($action < 80 and $action >= 70) {
                    $returnValue[$slug] = 4;
                } elseif ($action < 70 and $action >= 60) {
                    $returnValue[$slug] = 3;
                } elseif ($action < 60 and $action >= 50) {
                    $returnValue[$slug] = 2;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'sonali') {
                if ($action >= 100) {
                    $returnValue[$slug] = 6;
                } elseif ($action < 100 and $action >= 90) {
                    $returnValue[$slug] = 5;
                } elseif ($action < 90 and $action >= 80) {
                    $returnValue[$slug] = 4;
                } elseif ($action < 80 and $action >= 70) {
                    $returnValue[$slug] = 3;
                } elseif ($action < 70 and $action >= 60) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 60 and $action >= 50) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'layer') {
                if ($action >= 100) {
                    $returnValue[$slug] = 7;
                } elseif ($action < 100 and $action >= 90) {
                    $returnValue[$slug] = 6;
                } elseif ($action < 90 and $action >= 80) {
                    $returnValue[$slug] = 5;
                } elseif ($action < 80 and $action >= 70) {
                    $returnValue[$slug] = 4;
                } elseif ($action < 70 and $action >= 60) {
                    $returnValue[$slug] = 3;
                } elseif ($action < 60 and $action >= 50) {
                    $returnValue[$slug] = 2;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'fish') {
                if ($action >= 100) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'cattle') {
                if ($action >= 100) {
                    $returnValue[$slug] = 3;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 80 and $action >= 70) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            }
        }
        return $returnValue;
    }

    public function salesTargetCalculationAquaService($productSlug, $target, $sales)
    {
        $returnValue = [];
        if ($target > 0) {
            $action = (($sales * 100) / $target);
            $slug = $productSlug . '-aqua-service';
            if ($productSlug == 'broiler') {
                if ($action >= 100) {
                    $returnValue[$slug] = 4;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 3;
                } elseif ($action < 80 and $action >= 70) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 70 and $action >= 60) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'sonali') {
                if ($action >= 100) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'layer') {
                if ($action >= 100) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'fish') {
                if ($action >= 100) {
                    $returnValue[$slug] = 15;
                } elseif ($action < 100 and $action >= 90) {
                    $returnValue[$slug] = 14;
                } elseif ($action < 90 and $action >= 80) {
                    $returnValue[$slug] = 13;
                } elseif ($action < 80 and $action >= 70) {
                    $returnValue[$slug] = 12;
                } elseif ($action < 70 and $action >= 60) {
                    $returnValue[$slug] = 6;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'cattle') {
                if ($action >= 100) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            }
        }
        return $returnValue;
    }

    public function salesTargetCalculationCattleService($productSlug, $target, $sales)
    {
        $returnValue = [];
        if ($target > 0) {
            $action = (($sales * 100) / $target);
            $slug = $productSlug . '-cattle-service';

            if ($productSlug == 'broiler') {
                if ($action >= 100) {
                    $returnValue[$slug] = 3;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 80 and $action >= 70) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'sonali') {
                if ($action >= 100) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'layer') {
                if ($action >= 100) {
                    $returnValue[$slug] = 3;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 80 and $action >= 70) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'fish') {
                if ($action >= 100) {
                    $returnValue[$slug] = 2;
                } elseif ($action < 100 and $action >= 80) {
                    $returnValue[$slug] = 1;
                } else {
                    $returnValue[$slug] = 0;
                }
            } elseif ($productSlug == 'cattle') {
                if ($action >= 100) {
                    $returnValue[$slug] = 15;
                } elseif ($action < 100 and $action >= 90) {
                    $returnValue[$slug] = 14;
                } elseif ($action < 90 and $action >= 80) {
                    $returnValue[$slug] = 13;
                } elseif ($action < 80 and $action >= 70) {
                    $returnValue[$slug] = 12;
                } elseif ($action < 70 and $action >= 60) {
                    $returnValue[$slug] = 6;
                } else {
                    $returnValue[$slug] = 0;
                }
            }
        }
        return $returnValue;
    }

    public function individualTeamMemberCalculation($target, $sales)
    {
        if ($target > 0) {
            $action = (($sales * 100) / $target);
            if ($action >= 100) {
                return 10;
            } elseif ($action < 100 and $action >= 90) {
                return 9;
            } elseif ($action < 90 and $action >= 80) {
                return 8;
            } elseif ($action < 80 and $action >= 70) {
                return 7;
            } elseif ($action < 70 and $action >= 60) {
                return 6;
            } elseif ($action < 60 and $action >= 50) {
                return 5;
            } elseif ($action < 50 and $action >= 40) {
                return 4;
            } elseif ($action < 40 and $action >= 30) {
                return 3;
            } elseif ($action < 30 and $action >= 20) {
                return 2;
            } elseif ($action < 20 and $action >= 10) {
                return 1;
            } else {
                return 0;
            }

        }

    }

    private function salesGrowthCalculation($productSlug, $previousValue, $currentValue)
    {
        $returnValue = [];


        if ($productSlug) {

            if ($productSlug == 'broiler') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug;
                if ($action >= 15) {
                    $returnValue[$growthSlug] = 5;
                } elseif ($action < 15 and $action >= 10) {
                    $returnValue[$growthSlug] = 4;
                } elseif ($action < 10 and $action >= 5) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 5 and $action >= 3) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }

            } elseif ($productSlug == 'sonali') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug;
                if ($action >= 8) {
                    $returnValue[$growthSlug] = 5;
                } elseif ($action < 8 and $action >= 7) {
                    $returnValue[$growthSlug] = 4;
                } elseif ($action < 7 and $action >= 6) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 6 and $action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } elseif ($productSlug == 'layer') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug;
                if ($action >= 10) {
                    $returnValue[$growthSlug] = 5;
                } elseif ($action < 10 and $action >= 8) {
                    $returnValue[$growthSlug] = 4;
                } elseif ($action < 8 and $action >= 6) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 6 and $action >= 4) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } elseif ($productSlug == 'fish') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug;
                if ($action >= 20) {
                    $returnValue[$growthSlug] = 5;
                } elseif ($action < 20 and $action >= 15) {
                    $returnValue[$growthSlug] = 4;
                } elseif ($action < 15 and $action >= 10) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 10 and $action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } elseif ($productSlug == 'cattle') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug;
                if ($action >= 25) {
                    $returnValue[$growthSlug] = 5;
                } elseif ($action < 25 and $action >= 20) {
                    $returnValue[$growthSlug] = 4;
                } elseif ($action < 20 and $action >= 15) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 15 and $action >= 10) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } else {
                return 0;
            }
            return $returnValue;
        }

    }
    private function salesGrowthCalculationPoultryService($productSlug, $previousValue, $currentValue)
    {
        $returnValue = [];
        if ($productSlug) {
            if ($productSlug == 'broiler') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-poultry-service';
                if ($action >= 15) {
                    $returnValue[$growthSlug] = 7;
                } elseif ($action < 15 and $action >= 10) {
                    $returnValue[$growthSlug] = 6;
                } elseif ($action < 10 and $action >= 5) {
                    $returnValue[$growthSlug] = 5;
                } elseif ($action < 5 and $action >= 3) {
                    $returnValue[$growthSlug] = 4;
                } elseif ($action < 3 and $action >= 1) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }

            } elseif ($productSlug == 'sonali') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-poultry-service';
                if ($action >= 8) {
                    $returnValue[$growthSlug] = 6;
                } elseif ($action < 8 and $action >= 7) {
                    $returnValue[$growthSlug] = 5;
                } elseif ($action < 7 and $action >= 6) {
                    $returnValue[$growthSlug] = 4;
                } elseif ($action < 6 and $action >= 5) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 5 and $action >= 1) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }
            } elseif ($productSlug == 'layer') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-poultry-service';
                if ($action >= 15) {
                    $returnValue[$growthSlug] = 7;
                } elseif ($action < 15 and $action >= 10) {
                    $returnValue[$growthSlug] = 6;
                } elseif ($action < 10 and $action >= 5) {
                    $returnValue[$growthSlug] = 5;
                } elseif ($action < 5 and $action >= 3) {
                    $returnValue[$growthSlug] = 4;
                } elseif ($action < 3 and $action >= 1) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }
            } elseif ($productSlug == 'fish') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-poultry-service';
                if ($action >= 10) {
                    $returnValue[$growthSlug] = 2;
                } elseif ($action < 10 and $action >= 1) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }
            } elseif ($productSlug == 'cattle') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-poultry-service';
                if ($action >= 10) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 10 and $action >= 7) {
                    $returnValue[$growthSlug] = 2;
                } elseif ($action < 7 and $action >= 5) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }
            } else {
                return 0;
            }
            return $returnValue;
        }

    }

    private function salesGrowthCalculationAquaService($productSlug, $previousValue, $currentValue)
    {
        $returnValue = [];
        if ($productSlug) {
            if ($productSlug == 'broiler') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-aqua-service';
                if ($action >= 10) {
                    $returnValue[$growthSlug] = 4;
                } elseif ($action < 10 and $action >= 7) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 7 and $action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 0;
                }

            } elseif ($productSlug == 'sonali') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-aqua-service';
                if ($action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } elseif ($productSlug == 'layer') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-aqua-service';
                if ($action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } elseif ($productSlug == 'fish') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-aqua-service';
                if ($action >= 20) {
                    $returnValue[$growthSlug] = 15;
                } elseif ($action < 20 and $action >= 15) {
                    $returnValue[$growthSlug] = 14;
                } elseif ($action < 15 and $action >= 10) {
                    $returnValue[$growthSlug] = 13;
                } elseif ($action < 10 and $action >= 8) {
                    $returnValue[$growthSlug] = 12;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } elseif ($productSlug == 'cattle') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-aqua-service';
                if ($action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } else {
                return 0;
            }
            return $returnValue;
        }
    }
    private function salesGrowthCalculationCattleService($productSlug, $previousValue, $currentValue)
    {
        $returnValue = [];
        if ($productSlug) {
            if ($productSlug == 'broiler') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-cattle-service';
                if ($action >= 10) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 10 and $action >= 7) {
                    $returnValue[$growthSlug] = 2;
                } elseif ($action < 7 and $action >= 5) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }

            } elseif ($productSlug == 'sonali') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-cattle-service';
                if ($action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } elseif ($productSlug == 'layer') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-cattle-service';
                if ($action >= 10) {
                    $returnValue[$growthSlug] = 3;
                } elseif ($action < 10 and $action >= 7){
                    $returnValue[$growthSlug] = 2;
                } elseif ($action < 7 and $action >= 5){
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }
            } elseif ($productSlug == 'fish') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-cattle-service';
                if ($action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } else {
                    $returnValue[$growthSlug] = 1;
                }
            } elseif ($productSlug == 'cattle') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-cattle-service';
                if ($action >= 25) {
                    $returnValue[$growthSlug] = 15;
                } elseif ($action < 25 and $action >= 20){
                    $returnValue[$growthSlug] = 14;
                } elseif ($action < 20 and $action >= 15){
                    $returnValue[$growthSlug] = 13;
                } elseif ($action < 15 and $action >= 10){
                    $returnValue[$growthSlug] = 12;
                } elseif ($action < 10 and $action >= 6){
                    $returnValue[$growthSlug] = 6;
                } elseif ($action < 6 and $action >= 1){
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }
            } else {
                return 0;
            }
            return $returnValue;
        }
    }


    public function salesDistrictAchivementCalculation($target, $achivement)
    {
        if ($target > 0) {
            $action = (($achivement * 100) / $target);
            if ($action >= 100) {
                return 4;
            } elseif ($action < 100 and $action >= 50) {
                return 3;
            } elseif ($action < 50 and $action >= 1) {
                return 1;
            } else {
                return 0;
            }

        }

    }

    public function salesRegionalAchivementCalculation($target, $achivement)
    {
        if ($target > 0) {
            $action = (($achivement * 100) / $target);
            if ($action >= 100) {
                return 5;
            } elseif ($action < 100 and $action >= 50) {
                return 3;
            } elseif ($action < 50 and $action >= 1) {
                return 2;
            } else {
                return 0;
            }

        }

    }

    public function outstandingLimitCalculation($outstandingValue)
    {

        if ($outstandingValue) {
            if ($outstandingValue >= 500000) {
                return 0;
            } elseif ($outstandingValue >= 400000 && $outstandingValue < 500000) {
                return 1;
            } elseif ($outstandingValue >= 300000 && $outstandingValue < 400000) {
                return 2;
            } elseif ($outstandingValue >= 200000 && $outstandingValue < 300000) {
                return 3;
            } elseif ($outstandingValue < 200000) {
                return 4;
            }
        }
        return 0;

    }

    public function docSalesCollectionCalculation($collectionAmount, $salesAmount)
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

    private function getAttrbuteForMonthlyReport($board, $slug)
    {

        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.attribute', 'attribute');
        $qb->where('e.employeeBoard =:employeeBoardId')->setParameter('employeeBoardId', $board->getId());
        $qb->andWhere('attribute.slug = :slug')->setParameter('slug', $slug);
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result;
    }
}
