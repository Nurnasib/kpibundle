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
use Terminalbd\CrmBundle\Entity\CattleLifeCycle;
use Terminalbd\CrmBundle\Entity\CattlePerformanceDetails;
use Terminalbd\CrmBundle\Entity\ChickLifeCycle;
use Terminalbd\CrmBundle\Entity\ChickLifeCycleDetails;
use Terminalbd\CrmBundle\Entity\CompanyWiseFeedSale;
use Terminalbd\CrmBundle\Entity\CostBenefitAnalysisForLessCostingFarm;
use Terminalbd\CrmBundle\Entity\CrmVisit;
use Terminalbd\CrmBundle\Entity\DiseaseMapping;
use Terminalbd\CrmBundle\Entity\FarmerTrainingReport;
use Terminalbd\CrmBundle\Entity\FcrDetails;
use Terminalbd\CrmBundle\Entity\FishCompanyAndSpeciesWiseAverageFcr;
use Terminalbd\CrmBundle\Entity\FishLifeCycle;
use Terminalbd\CrmBundle\Entity\LayerLifeCycle;
use Terminalbd\CrmBundle\Entity\LayerLifeCycleDetails;
use Terminalbd\CrmBundle\Entity\LayerPerformanceDetails;
use Terminalbd\CrmBundle\Entity\NewFarmerIntroduce\FarmerIntroduceDetails;
use Terminalbd\CrmBundle\Entity\NewFarmerTouch\FarmerTouchReport;
use Terminalbd\CrmBundle\Repository\FcrDetailsRepository;
use Terminalbd\KpiBundle\Entity\AgentCategory;
use Terminalbd\KpiBundle\Entity\AgentDocSaleCollection;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\AgentOutstanding;
use Terminalbd\KpiBundle\Entity\DistrictOrder;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeBoardSubAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeDistrictHistory;
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

    public function employeeBoardSummaryReport(EmployeeBoard $board)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->join("e.parameter", 'parameter');
        $qb->join("e.activity", 'activity');

        $qb->select('parameter.name AS parameterName');
        $qb->addSelect('SUM(e.actualMark) AS actualMark', 'SUM(e.mark) AS mark', 'SUM(e.selfMark) AS selfMark');
        $qb->addSelect('activity.name AS activityName');

        $qb->where("e.employeeBoard = {$board->getId()}");
        $qb->groupBy("activity.id");
        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            $data[$result['parameterName']][] = $result;
        }
        return $data;
    }

    public function insertMarkDistribution(EmployeeBoard $board, $parameters)
    {
        $em = $this->_em;

        $employeeDistrictHistory = $this->_em->getRepository(EmployeeDistrictHistory::class)->findOneBy(['employee' => $board->getEmployee(), 'year' => $board->getYear(), 'month' => $board->getMonth()]);
        $districts = $employeeDistrictHistory ? $employeeDistrictHistory->getDistrict() : '';
        $districtsId = $districts ? array_keys(json_decode($districts, true)) : [];

        foreach ($parameters as $parameter){
            if (!empty($parameter->getChildren())) {
                foreach ($parameter->getChildren() as $activity){
                    if (!empty($activity->getChildren())) {
                        foreach ($activity->getChildren() as $attribute){
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
                        }
                    }
                }
            }
        }
        $this->updateSalesProcess($board, $districtsId);
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
        $this->updateOutStandingLimit($board, $districtsId);
        $this->updateDocSales($board, $districtsId);
        $this->updateCategoryUpgrade($board, $districtsId);

        // Customer Development Report Marks Calculation **Stay for Mr. Murad permission
        
//        if ($board->getEmployee()->getReportMode()->getSlug() == 'poultry-service') {
//            $this->updateCustomerDevelopmentPoultry($board);
//        } elseif ($board->getEmployee()->getReportMode()->getSlug() == 'aqua-service') {
//            $this->updateCustomerDevelopmentAqua($board);
//        } elseif ($board->getEmployee()->getReportMode()->getSlug() == 'cattle-service') {
//            $this->updateCustomerDevelopmentCattle($board);
//        }

        $this->agentSalesGrowth($board, $districtsId);
        $this->gradeUpdate($board);

    }

    public function insertMarkDistributionForCustomFormat(EmployeeBoard $board, $parameters)
    {
        $em = $this->_em;
        foreach ($parameters as $parameter){
            if (!empty($parameter->getChildren())) {
                foreach ($parameter->getChildren() as $activity){
                    if (!empty($activity->getChildren())) {
                        foreach ($activity->getChildren() as $attribute){
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
                        }
                    }
                }
            }
        }
//        $this->updateSalesProcess($board);
        $subAttrs = $this->groupByAttributeMarks($board);
        foreach ($subAttrs as $sub){
            $exist = $this->findOneBy(array('employeeBoard' => $board, 'attribute' => $sub['parentId']));
            if (!empty($exist)) {
                $exist->setActualMark(5);
                $em->persist($exist);
                $em->flush();
            }
        }


//        $this->updateIndividualSales($board);
//        $this->updateOutStandingLimit($board);
//        $this->updateDocSales($board);
//        $this->updateCategoryUpgrade($board);
//        $this->agentSalesGrowth($board);
        $this->gradeUpdate($board);

    }

    public function gradeUpdate(EmployeeBoard $board)
    {
        $em = $this->_em;
        $totalObtainMark = 0;
        $totalSelfMark = 0;
        $totalActualMark = 0;

        $marks = $this->employeeBoardSummaryReport($board);
        foreach ($marks as $parameter => $mark) {
            foreach ($mark as $item) {
                if ($parameter === 'Core Responsibilities'){
                    $totalSelfMark += $item['mark'];
                }
                $totalObtainMark += $item['mark'];
                $totalActualMark += $item['actualMark'];

                $totalSelfMark += $item['selfMark'];
            }
        }
        $totalMarkPercentage = ($totalObtainMark * 100) / $totalActualMark;
        $totalSelfMarkPercentage = ($totalSelfMark * 100) / $totalActualMark;

        $grade = $this->gradeCalculation($totalMarkPercentage);
        $selfGrade = $this->gradeCalculation($totalSelfMarkPercentage);

        $board->setObtainMark($totalObtainMark ?: 0);
        $board->setActualMark($totalActualMark ?: 0);
        $board->setSelfMark($totalSelfMark ?: 0);
        $board->setSelfGrade($selfGrade);
        $board->setGrade($grade);
//        $em->persist($board);
        $em->flush();
    }

    public function gradeCalculation($percentage)
    {
        if ($percentage >= 80){
            return 'A';
        } elseif ($percentage >= 75 and $percentage < 80){
            return 'B+';
        } elseif ($percentage >= 70 and $percentage < 75){
            return 'B';
        } elseif ($percentage >= 65 and $percentage < 70){
            return 'C';
        } else{
            return 'D';
        }
    }

    public function agentSalesGrowth(EmployeeBoard $board, $districtsId)
    {
        $em = $this->_em;

        $agentUpgrade = $em->getRepository(AgentCategory::class)->getAgentUpgradation($board, $districtsId);

        $agentSalesDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'agent-upgradation'));
        $employeeBoardAttributeForAgentSalesGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $agentSalesDistribution]);

        if ($employeeBoardAttributeForAgentSalesGrowth) {
            $mark = $this->growthAgentMarkCalculation($agentUpgrade['totalAgentsCountPrevYear'], $agentUpgrade['upgradeAgentsCount']);
            $employeeBoardAttributeForAgentSalesGrowth->setMark($mark);
            $em->persist($employeeBoardAttributeForAgentSalesGrowth);
            $em->flush();
        }
    }

    private function growthAgentMarkCalculation($totalAgents, $upgradeAgents)
    {
        if ($totalAgents > 0 && $upgradeAgents > 0){
            $agentNumberWithPercentage = ($upgradeAgents * 100) / $totalAgents;

            if ($agentNumberWithPercentage >= 20) {
                return 3;
            } elseif ($agentNumberWithPercentage >= 10 && $agentNumberWithPercentage < 20) {
                return 2;
            } elseif ($agentNumberWithPercentage >= 1 && $agentNumberWithPercentage < 10) {
                return 1;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    private function getCustomerDevelopmentAttribute(EmployeeBoard $board){
        $qb = $this->createQueryBuilder('e');

        $qb->join('e.activity', 'activity');

        $qb->select('e');

        $qb->where('e.employeeBoard = :board')->setParameter('board', $board);
        $qb->andWhere('activity.slug = :slug')->setParameter('slug', 'customer-development');
        return $qb->getQuery()->getResult();
    }
    public function updateCustomerDevelopmentPoultry(EmployeeBoard $board)
    {
        $em = $this->_em;

        $boardAttributes = $this->getCustomerDevelopmentAttribute($board);

        foreach ($boardAttributes as $boardAttribute) {
            /**
             * @var EmployeeBoardAttribute $boardAttribute
             */
            switch ($boardAttribute->getAttribute()->getSlug()){
                case 'monthly-broiler-sonali-fcr-report-after-sale':
                    $numberOfReports = $em->getRepository(FcrDetails::class)->getNumberOfReportsForKpi($board, 'AFTER');
                    $mark = $numberOfReports * 0.1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
//                    dd($mark, $numberOfReports, $boardAttribute->getAttribute()->getSlug(),$boardAttribute);
                    break;
                case 'monthly-broiler-sonali-before-sale-report-layer-performance-report':
                    $numberOfReportsBroilerSonali = $em->getRepository(FcrDetails::class)->getNumberOfReportsForKpi($board, 'BEFORE');
                    $numberOfLayerPerformance = $em->getRepository(LayerPerformanceDetails::class)->getNumberOfReportsForKpi($board);
                    $numberOfReports = $numberOfReportsBroilerSonali + $numberOfLayerPerformance;

                    $mark = $numberOfReports * 0.05;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;

                case 'monthly-broiler-sonali-life-cycle-report-layer-life-cycle-report':
                    $numberOfReportsBroilerSonali = $em->getRepository(ChickLifeCycleDetails::class)->getNumberOfReportsForKpi($board);
                    $numberOfReportsLayer = $em->getRepository(LayerLifeCycleDetails::class)->getNumberOfReportsForKpi($board);

                    $numberOfReports = $numberOfReportsBroilerSonali + $numberOfReportsLayer;
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-less-costing-model-farm-develop':
                    $numberOfReports = $em->getRepository(CostBenefitAnalysisForLessCostingFarm::class)->getNumberOfReportsForKpi($board);
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-antibiotic-free-farm-develop':
                    $numberOfReports = $em->getRepository(AntibioticFreeFarm::class)->getNumberOfReportsForKpi($board);
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-farmers-training-program-10-15-farmers':
                    $numberOfReports = $em->getRepository(FarmerTrainingReport::class)->getNumberOfReportsForKpi($board, 'poultry-breed');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-new-poultry-farm-introduce-to-nourish-feed':
                    $numberOfReports = $em->getRepository(FarmerIntroduceDetails::class)->getNumberOfReportsForKpi($board, 'poultry-breed');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-3-cattle-introduce-to-nourish-feed':
                    $numberOfReports = $em->getRepository(FarmerIntroduceDetails::class)->getNumberOfReportsForKpi($board, 'cattle-breed');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
            }
        }
    }
    public function updateCustomerDevelopmentAqua(EmployeeBoard $board)
    {
        $em = $this->_em;

        $boardAttributes = $this->getCustomerDevelopmentAttribute($board);
        foreach ($boardAttributes as $boardAttribute) {
            /**
             * @var EmployeeBoardAttribute $boardAttribute
             */
            switch ($boardAttribute->getAttribute()->getSlug()){
                case 'life-cycle-report-culture-after-sale':
                    $numberOfReports = $em->getRepository(FishLifeCycle::class)->getNumberOfReportsForKpi($board);
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'company-species-wise-avg-fcr-report-culture-after-sale':
                    $numberOfReports = $em->getRepository(FishCompanyAndSpeciesWiseAverageFcr::class)->getNumberOfReportsForKpi($board);
                    $mark = $numberOfReports * 0.4;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-fish-farm-information-survey-report':
                    $numberOfReports = $em->getRepository(FarmerIntroduceDetails::class)->getNumberOfReportsNewFarmerForKpi($board, 'fish-breed');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-new-fish-farmer-introduce-to-nourish-family':
                    $numberOfReports = $em->getRepository(FarmerIntroduceDetails::class)->getNumberOfReportsForKpi($board, 'fish-breed');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-model-farm-develop':
                    break;
                case 'monthly-farmers-training-program-10-15-farmers':
                    $numberOfReports = $em->getRepository(FarmerTrainingReport::class)->getNumberOfReportsForKpi($board, 'fish-breed');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-new-agent-or-sub-agent-introduce-to-nourish-family':
                    break;
                case 'monthly-3-cattle-introduce-to-nourish-feed':
                    $numberOfReports = $em->getRepository(FarmerIntroduceDetails::class)->getNumberOfReportsForKpi($board, 'cattle-breed');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
            }
        }
    }
    public function updateCustomerDevelopmentCattle(EmployeeBoard $board)
    {
        $em = $this->_em;
        $boardAttributes = $this->getCustomerDevelopmentAttribute($board);
        foreach ($boardAttributes as $boardAttribute) {
            /**
             * @var EmployeeBoardAttribute $boardAttribute
             */
            switch ($boardAttribute->getAttribute()->getSlug()){
                case 'monthly-farm-visit-report':

                    break;
                case 'monthly-dairy-fattening-feed-performance-report':
                    $numberOfReports = $em->getRepository(CattlePerformanceDetails::class)->getNumberOfReportsForKpi($board);
                    $mark = $numberOfReports * 0.4;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-dairy-fattening-life-cycle-report':
                    $numberOfReports = $em->getRepository(CattleLifeCycle::class)->getNumberOfReportsForKpi($board, 'COMPLETE');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-new-cattle-farm-introduce-to-nourish-feed':
                    $numberOfReports = $em->getRepository(FarmerIntroduceDetails::class)->getNumberOfReportsForKpi($board, 'cattle-breed');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-new-agent-or-sub-agent-creation':
                    break;
                case 'monthly-farmers-training-program-10-15-farmers':
                    $numberOfReports = $em->getRepository(FarmerTrainingReport::class)->getNumberOfReportsForKpi($board, 'cattle-breed');
                    $mark = $numberOfReports * 1;
                    $boardAttribute->setNumberOfReport($numberOfReports);
                    $boardAttribute->setMark($boardAttribute->getActualMark() < $mark ?: $mark);
                    $em->flush();
                    break;
                case 'monthly-model-cattle-farm-develop':
                    break;
            }
        }
    }


    public function updateSalesProcess(EmployeeBoard $board, $districtsId)
    {
        $em = $this->_em;
        $entities = "";
/*        $locations = $board->getEmployee()->getDistrict();
        $arrs = array();
        if (!empty($locations)) {
            foreach ($locations as $location) {
                $arrs[] = $location->getId();
            }
        }*/


        $entities = $em->getRepository(DistrictOrder::class)->getLocationWiseTotalProductSalesTarget($districtsId, $board);
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

                if ($board->getEmployee()->getReportMode()->getSlug() == 'poultry-service' || $board->getEmployee()->getReportMode()->getSlug() == 'lab-service') {
                    $distributionPoultry = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'feed', 'slug' => $distribution->getSlug() . '-poultry-service'));

                    $exist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $distributionPoultry));
                    if ($exist) {
                        $entity = $exist;
                    }
                    $entity->setEmployeeBoard($board);
                    $entity->setTargetQuantity($parameter['cumulativeTargetQuantity'] ?: 0);
//                    $entity->setTargetQuantity($parameter['targetQuantity'] ?: 0);
//                    $entity->setSalesQuantity($parameter['quantity'] ?: 0);
                    $entity->setSalesQuantity($parameter['cumulativeQuantity'] ?: 0);
                    $entity->setMarkDistribution($distributionPoultry);

                    $mark = $this->salesTargetCalculationPoultryService($distribution->getSlug(), $entity->getTargetQuantity(), $entity->getSalesQuantity())[$distributionPoultry->getSlug()];
                    $entity->setMark($mark ?: 0);
                    $em->persist($entity);

                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distributionPoultry]);
                    if ($employeeBoardAttribute) {
                        $employeeBoardAttribute->setMark($entity->getMark() ?: 0);
                        $em->persist($employeeBoardAttribute);
                        $em->flush();
                    }

//                    Sales Growth
                    $growthDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'growth', 'slug' => 'growth-' . $distribution->getSlug() . '-poultry-service'));
                    $growthEntity = new EmployeeBoardSubAttribute();

                    $growthExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $growthDistribution));
                    if ($growthExist) {
                        $growthEntity = $growthExist;
                    }
                    $growthEntity->setEmployeeBoard($board);
                    $growthEntity->setMarkDistribution($growthDistribution);
                    $growthEntity->setTargetQuantity($parameter['salesGrouthPreviousQuantity'] ?: 0);
//                    $growthEntity->setSalesAmount($parameter['quantity'] ?: 0);
//                    $growthEntity->setSalesQuantity($parameter['salesGrouthCurrentQuantity'] ?: 0);
                    $growthEntity->setSalesQuantity($parameter['cumulativeQuantity'] ?: 0);

                    $growthEntity->setMark($this->salesGrowthCalculationPoultryService($distribution->getSlug(), $parameter['salesGrouthPreviousQuantity'], $parameter['cumulativeQuantity'])[$growthDistribution->getSlug()] ?: 0);
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

                    $exist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $distributionAqua));
                    if ($exist) {
                        $entity = $exist;
                    }
                    $entity->setEmployeeBoard($board);
                    $entity->setTargetQuantity($parameter['cumulativeTargetQuantity'] ?: 0);
//                    $entity->setTargetQuantity($parameter['targetQuantity'] ?: 0);
//                    $entity->setSalesQuantity($parameter['quantity'] ?: 0);
                    $entity->setSalesQuantity($parameter['cumulativeQuantity'] ?: 0);
                    $entity->setMarkDistribution($distributionAqua);

                    $mark = $this->salesTargetCalculationAquaService($distribution->getSlug(), $entity->getTargetQuantity(), $entity->getSalesQuantity())[$distributionAqua->getSlug()];
                    $entity->setMark($mark ?: 0);
                    $em->persist($entity);

                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distributionAqua]);
                    if ($employeeBoardAttribute) {
                        $employeeBoardAttribute->setMark($entity->getMark());
                        $em->persist($employeeBoardAttribute);
                        $em->flush();
                    }
//                 Sales Growth
                    $growthDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'growth', 'slug' => 'growth-' . $distribution->getSlug() . '-aqua-service'));
                    $growthEntity = new EmployeeBoardSubAttribute();

                    $growthExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $growthDistribution));
                    if ($growthExist) {
                        $growthEntity = $growthExist;
                    }
                    $growthEntity->setEmployeeBoard($board);
                    $growthEntity->setMarkDistribution($growthDistribution);
                    $growthEntity->setTargetQuantity($parameter['salesGrouthPreviousQuantity'] ?: 0);
//                    $growthEntity->setSalesAmount($parameter['quantity'] ?: 0);
//                    $growthEntity->setSalesQuantity($parameter['salesGrouthCurrentQuantity'] ?: 0);
                    $growthEntity->setSalesQuantity($parameter['cumulativeQuantity'] ?: 0);

                    $growthEntity->setMark($this->salesGrowthCalculationAquaService($distribution->getSlug(), $parameter['salesGrouthPreviousQuantity'], $parameter['cumulativeQuantity'])[$growthDistribution->getSlug()] ?: 0);
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

                    $exist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $distributionCattle));
                    if ($exist) {
                        $entity = $exist;
                    }
                    $entity->setEmployeeBoard($board);
                    $entity->setTargetQuantity($parameter['cumulativeTargetQuantity'] ?: 0);
//                    $entity->setTargetQuantity($parameter['targetQuantity'] ?: 0);
//                    $entity->setSalesQuantity($parameter['quantity'] ?: 0);
                    $entity->setSalesQuantity($parameter['cumulativeQuantity'] ?: 0);
                    $entity->setMarkDistribution($distributionCattle);

                    $mark = $this->salesTargetCalculationCattleService($distribution->getSlug(), $entity->getTargetQuantity(), $entity->getSalesQuantity())[$distributionCattle->getSlug()];
                    $entity->setMark($mark);
                    $em->persist($entity);

                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distributionCattle]);
                    if ($employeeBoardAttribute) {
                        $employeeBoardAttribute->setMark($entity->getMark());
                        $em->persist($employeeBoardAttribute);
                        $em->flush();
                    }

                    //Sales Growth
                    $growthDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'growth', 'slug' => 'growth-' . $distribution->getSlug() . '-cattle-service'));
                    $growthEntity = new EmployeeBoardSubAttribute();

                    $growthExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $growthDistribution));
                    if ($growthExist) {
                        $growthEntity = $growthExist;
                    }
                    $growthEntity->setEmployeeBoard($board);
                    $growthEntity->setMarkDistribution($growthDistribution);
                    $growthEntity->setTargetQuantity($parameter['salesGrouthPreviousQuantity'] ?: 0);
//                    $growthEntity->setSalesAmount($parameter['quantity'] ?: 0);
//                    $growthEntity->setSalesQuantity($parameter['salesGrouthCurrentQuantity'] ?: 0);
                    $growthEntity->setSalesQuantity($parameter['cumulativeQuantity'] ?: 0);

                    $growthEntity->setMark($this->salesGrowthCalculationCattleService($distribution->getSlug(), $parameter['salesGrouthPreviousQuantity'], $parameter['cumulativeQuantity'])[$growthDistribution->getSlug()] ?: 0);
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
                    $totalActualMark = $totalActualMark + $distribution->getMark();

                    $exist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $parameter['id']));
                    if ($exist) {
                        $entity = $exist;
                    }
                    $entity->setEmployeeBoard($board);
                    $entity->setTargetQuantity($parameter['cumulativeTargetQuantity'] ?: 0);
//                    $entity->setTargetQuantity($parameter['targetQuantity'] ?: 0);
//                    $entity->setSalesQuantity($parameter['quantity'] ?: 0);
                    $entity->setSalesQuantity($parameter['cumulativeQuantity'] ?: 0);

                    $entity->setMarkDistribution($distribution);

                    $mark = $this->salesTargetCalculation($entity->getTargetQuantity(), $entity->getSalesQuantity());
                    $entity->setMark($mark);
                    $em->persist($entity);
                    $em->flush();

                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distribution]);
                    if ($employeeBoardAttribute) {
                        $employeeBoardAttribute->setMark($entity->getMark() ?: 0);
                        $em->persist($employeeBoardAttribute);
                        $em->flush();
                    }


                    //Sales Growth
                    $growthDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('salesMode' => 'growth', 'slug' => 'growth-' . $distribution->getSlug()));

                    $growthEntity = new EmployeeBoardSubAttribute();

                    $growthExist = $em->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(array('employeeBoard' => $board, 'markDistribution' => $growthDistribution));
                    if ($growthExist) {
                        $growthEntity = $growthExist;
                    }
                    $growthEntity->setEmployeeBoard($board);
                    $growthEntity->setMarkDistribution($growthDistribution);
                    $growthEntity->setTargetQuantity($parameter['salesGrouthPreviousQuantity'] ?: 0);
//                    $growthEntity->setSalesAmount($parameter['quantity'] ?: 0);
//                    $growthEntity->setSalesQuantity($parameter['salesGrouthCurrentQuantity'] ?: 0);
                    $growthEntity->setSalesQuantity($parameter['cumulativeQuantity'] ?: 0);

                    $growthEntity->setMark($this->salesGrowthCalculation($distribution->getSlug(), $parameter['salesGrouthPreviousQuantity'], $parameter['cumulativeQuantity'])[$growthDistribution->getSlug()] ?: 0);
                    $em->persist($growthEntity);
                    $em->flush();


                    $employeeBoardAttributeForGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $growthDistribution]);
                    if ($employeeBoardAttributeForGrowth) {
                        $employeeBoardAttributeForGrowth->setMark($growthEntity->getMark() ?: 0);
                        $em->persist($employeeBoardAttributeForGrowth);
                        $em->flush();
                    }
                }

            endforeach;

            $discritAchivementDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'district-achievement'));
            $employeeBoardAttributeForDistrictAchivement = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $discritAchivementDistribution]);
            if ($employeeBoardAttributeForDistrictAchivement) {
                $employeeBoardAttributeForDistrictAchivement->setTargetAchievement($totalQuantity ?: 0);
                $employeeBoardAttributeForDistrictAchivement->setTargetAmount($totalTargetQuantity ?: 0);
                $employeeBoardAttributeForDistrictAchivement->setMark($this->salesDistrictAchivementCalculation($totalActualMark, $totalAchivementMark));
                $em->persist($employeeBoardAttributeForDistrictAchivement);
                $em->flush();
            }

            $regionalAchivementDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'regional-achievement'));
            $employeeBoardAttributeForRegionalAchivement = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $regionalAchivementDistribution]);
            if ($employeeBoardAttributeForRegionalAchivement) {
                $employeeBoardAttributeForRegionalAchivement->setTargetAchievement($totalQuantity ?: 0);
                $employeeBoardAttributeForRegionalAchivement->setTargetAmount($totalTargetQuantity ?: 0);
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

        $getEmployeesByLineManager = $this->_em->getRepository(User::class)->findBy(['lineManager' => $employee, 'enabled' => 1, 'isPermanent' => true]);
        $employeeArrs = array();
        foreach ($getEmployeesByLineManager as $childEmployee) {
            if (!empty($childEmployee)) {
                $employeeArrs[] = $childEmployee->getId();
            }
        }
        $parameter = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'core-responsibilities', 'status' => 1));

        $entities = $this->individualTeamMemberMarks($employeeArrs, $parameter, $board->getYear(), $board->getMonth());
//        $individualTeamDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug'=>'individual-team-members-achievement','status'=>1));

        $individualTeamDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'team-members-marks-on-core-activities', 'status' => 1));

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
            $employeeBoardAttributeForIndividualTeam->setMark($individualEntity->getMark() ?: 0);
            $em->persist($employeeBoardAttributeForIndividualTeam);
            $em->flush();
        }

    }

    public function updateOutStandingLimit(EmployeeBoard $board, $districtsId)
    {
        $em = $this->_em;

/*        $locations = $board->getEmployee()->getDistrict();
        $arrs = array();
        if (!empty($locations)) {
            foreach ($locations as $location) {
                $arrs[] = $location->getId();
            }
        }*/

        $outstandingAmount = $em->getRepository(AgentOutstanding::class)->getLocationWiseTotalOutstanding($districtsId, $board->getYear(), $board->getMonth());
        if ($board->getEmployee()->getReportMode()->getSlug() == 'agm-kpi'){
            $outstandingSlug = 'agm-outstanding-limit-vs-actual-feed';
        }elseif ($board->getEmployee()->getReportMode()->getSlug() == 'rsm-arsm-kpi'){
            $outstandingSlug = 'rsm-outstanding-limit-vs-actual-feed';
        }else{
            $outstandingSlug = 'outstanding-limit-vs-actual-feed';
        }

        $outstandingDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => $outstandingSlug));

        $employeeBoardAttributeForOutStandingLimit = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $outstandingDistribution]);

        if ($employeeBoardAttributeForOutStandingLimit) {
            $employeeBoardAttributeForOutStandingLimit->setMark($this->outstandingLimitCalculation($board, $outstandingAmount));
//            $em->persist($employeeBoardAttributeForOutStandingLimit);
            $em->flush();
        }

    }

    public function updateDocSales(EmployeeBoard $board, $districtsId)
    {
        $em = $this->_em;
        /*        $locations = $board->getEmployee()->getDistrict();
                $arrs = array();
                if (!empty($locations)) {
                    foreach ($locations as $location) {
                        $arrs[] = $location->getId();
                    }
                }*/

        $docSalesObj = $em->getRepository(AgentDocSaleCollection::class)->getLocationWiseTotalDocSales($districtsId, $board->getYear(), $board->getMonth());

        $docSalesDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'doc-sales-collection'));

        $employeeBoardAttributeForDocSales = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $docSalesDistribution]);

        if ($employeeBoardAttributeForDocSales) {
            $employeeBoardAttributeForDocSales->setMark($this->docSalesCollectionCalculation($docSalesObj['totalCollectionAmount'], $docSalesObj['totalSalesAmount']));
            $em->persist($employeeBoardAttributeForDocSales);
            $em->flush();
        }

    }

    public function updateCategoryUpgrade(EmployeeBoard $board, $districtsId)
    {
        $em = $this->_em;

        $gradeLetters = ['C', 'D'];
        $categoryUpgradationMark = $em->getRepository(AgentCategory::class)->getCategoryUpgradationMarks($board, $gradeLetters, $districtsId);
        $agentCategoryDistributions = $em->getRepository(MarkChart::class)->findBy(['slug' => ['minimum-50-d-category-agents-converts-to-c', 'minimum-50-c-category-agents-converts-to-b']]);

        foreach ($agentCategoryDistributions as $agentCategoryDistribution) {
            if ($agentCategoryDistribution->getSlug() == 'minimum-50-d-category-agents-converts-to-c') {
                $employeeBoardAttributeForCategoryUpgrade = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $agentCategoryDistribution]);
                if ($employeeBoardAttributeForCategoryUpgrade) {
                    $employeeBoardAttributeForCategoryUpgrade->setMark(isset($categoryUpgradationMark['DtoUpperGrade']) ? $categoryUpgradationMark['DtoUpperGrade'] : 0);
                    $em->persist($employeeBoardAttributeForCategoryUpgrade);
                    $em->flush();
                }
            } elseif ($agentCategoryDistribution->getSlug() == 'minimum-50-c-category-agents-converts-to-b') {
                $employeeBoardAttributeForCategoryUpgrade = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $agentCategoryDistribution]);
                if ($employeeBoardAttributeForCategoryUpgrade) {
                    $employeeBoardAttributeForCategoryUpgrade->setMark(isset($categoryUpgradationMark['CtoUpperGrade']) ? $categoryUpgradationMark['CtoUpperGrade'] : 0);
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
        return $qb->getQuery()->getArrayResult();
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
//        $qb->groupBy('parameter.id');
//        $qb->addGroupBy('ed.id');
        $result = $qb->getQuery()->getOneOrNullResult();

        return $result;
    }

    public function getIndividualTeamMemberMarks($employees, $parameter, EmployeeBoard $board)
    {
        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employeeBoard', 'ed');
        $qb->join('ed.employee', 'em');
        $qb->join('e.parameter', 'parameter');
        $qb->select('SUM(e.mark) as mark', 'SUM(e.actualMark) as actualMark', 'SUM(e.targetAmount) AS targetAmount', 'SUM(e.targetAchievement) AS targetAchievement');
        $qb->addSelect('em.name AS employeeName', 'em.userId AS userId');
        $qb->where('em.id IN (:employee)')->setParameter('employee', $employees);
        $qb->andWhere('parameter.id = :parameter')->setParameter('parameter', $parameter);
        $qb->andWhere('ed.year =:year')->setParameter('year', $board->getYear());
        $qb->andWhere('ed.month =:month')->setParameter('month', $board->getMonth());
        $qb->groupBy('em.id');
        $results = $qb->getQuery()->getArrayResult();

        $data = [];
        foreach ($results as $result) {
            $data[$result['employeeName']] = [
                'userId' => $result['userId'],
                'mark' => $result['mark'],
                'actualMark' => $result['actualMark'],
            ];
        }
        $individualTeamDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'team-members-marks-on-core-activities', 'status' => 1));
        if ($individualTeamDistribution){
            $employeeBoardAttributeForIndividualTeam = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $individualTeamDistribution]);
            $data['obtainMark'] = $employeeBoardAttributeForIndividualTeam ? $employeeBoardAttributeForIndividualTeam->getMark() : 0;
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
        }else{
            return 0;
        }
    }

    public function salesTargetCalculationPoultryService($productSlug, $target, $sales)
    {
        $returnValue = [];
        $slug = $productSlug . '-poultry-service';

        if ($target > 0) {
            $action = (($sales * 100) / $target);
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
        } else {
            $returnValue[$slug] = 0;
        }
        return $returnValue;
    }

    public function salesTargetCalculationAquaService($productSlug, $target, $sales)
    {
        $returnValue = [];
        $slug = $productSlug . '-aqua-service';

        if ($target > 0) {
            $action = (($sales * 100) / $target);
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
        } else {
            $returnValue[$slug] = 0;
        }
        return $returnValue;
    }

    public function salesTargetCalculationCattleService($productSlug, $target, $sales)
    {
        $returnValue = [];
        $slug = $productSlug . '-cattle-service';

        if ($target > 0) {
            $action = (($sales * 100) / $target);
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
        } else {
            $returnValue[$slug] = 0;
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

    public function salesGrowthCalculation($productSlug, $previousValue, $currentValue)
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
                }  elseif ($action < 3 and $action > 0) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
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
                }  elseif ($action < 5 and $action > 0) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
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
                }  elseif ($action < 4 and $action > 0) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
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
                }  elseif ($action < 5 and $action > 0) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
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
                }  elseif ($action < 10 and $action > 0) {
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
    public function salesGrowthCalculationPoultryService($productSlug, $previousValue, $currentValue)
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
                } elseif ($action < 3 and $action > 0) {
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
                } elseif ($action < 5 and $action > 0) {
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
                } elseif ($action < 3 and $action > 0) {
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
                } elseif ($action < 10 and $action > 0) {
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

    public function salesGrowthCalculationAquaService($productSlug, $previousValue, $currentValue)
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
                } elseif ($action < 5 and $action > 0) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }
            } elseif ($productSlug == 'layer') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-aqua-service';
                if ($action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } elseif ($action < 5 and $action > 0) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
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
                } elseif ($action < 8 and $action > 0) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }
            } elseif ($productSlug == 'cattle') {
                $increase = $currentValue - $previousValue;
                $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;
                $growthSlug = 'growth-' . $productSlug . '-aqua-service';
                if ($action >= 5) {
                    $returnValue[$growthSlug] = 2;
                } elseif ($action < 5 and $action > 0) {
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
    public function salesGrowthCalculationCattleService($productSlug, $previousValue, $currentValue)
    {
        $returnValue = [];
        if ($productSlug) {
            $increase = $currentValue - $previousValue;
            $action = $previousValue > 0 ? (($increase / $previousValue) * 100) : 0;

            if ($productSlug == 'broiler') {
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
//                dd($increase, $currentValue, $previousValue, $returnValue);


            } elseif ($productSlug == 'sonali') {
                $growthSlug = 'growth-' . $productSlug . '-cattle-service';
                if ($action >= 5) {
                    $returnValue[$growthSlug] = 2;
                }elseif ($action < 5 and $action > 0) {
                    $returnValue[$growthSlug] = 1;
                }else {
                    $returnValue[$growthSlug] = 0;
                }
            } elseif ($productSlug == 'layer') {

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

                $growthSlug = 'growth-' . $productSlug . '-cattle-service';
                if ($action >= 5) {
                    $returnValue[$growthSlug] = 2;
                }elseif ($action < 5 and $action > 0) {
                    $returnValue[$growthSlug] = 1;
                } else {
                    $returnValue[$growthSlug] = 0;
                }
            } elseif ($productSlug == 'cattle') {

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
                } elseif ($action < 6 and $action > 0){
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
        } else {
            return 0;
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
        } else {
            return 0;
        }

    }

    public function outstandingLimitCalculation(EmployeeBoard $board, $outstandingValue)
    {
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
                } elseif ($outstandingValue > 10000 && $outstandingValue < 200000) {
                    return 4;
                } else{
                    return 5;
                }
            }
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

    private function getAttributeForMonthlyReport($board, $slug)
    {

        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.attribute', 'attribute');
        $qb->where('e.employeeBoard =:employeeBoardId')->setParameter('employeeBoardId', $board->getId());
        $qb->andWhere('attribute.slug = :slug')->setParameter('slug', $slug);
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result;
    }


    public function getActivities()
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.activity', 'activity');
        $qb->select('activity.name AS activityName', 'activity.id');
        $qb->orderBy('e.id', 'ASC');
        $results = $qb->getQuery()->getArrayResult();

        $data = [];
        foreach ($results as $result){
            $data[$result['activityName']] = $result['activityName'];
        }
        return $data;
    }
    public function getTeamMemberSummary($filterBy, $months, $user)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employeeBoard', 'board');
        $qb->join('board.employee', 'employee');
        $qb->join('board.reportMode', 'boardReportMode');
        $qb->leftJoin('employee.designation', 'designation');
        $qb->join('employee.lineManager', 'lineManager');
        $qb->join('employee.reportMode', 'reportMode');
//        $qb->join('e.parameter', 'parameter');
        $qb->join('e.activity', 'activity');

        $qb->select('SUM(e.mark) as mark');
        $qb->addSelect('employee.name AS employeeName','employee.id AS employeeId', 'employee.userId');
        $qb->addSelect('activity.name AS activityName','activity.id AS activityId');
        $qb->addSelect('designation.name AS employeeDesignation');
        $qb->addSelect('board.month','boardReportMode.name AS boardReportFormat','boardReportMode.slug AS boardReportFormatSlug', 'board.obtainMark', 'board.grade', 'board.id AS boardId');

        $qb->where('board.year =:year')->setParameter('year', $filterBy['year']);
        if (isset($filterBy['kpiFormat'])){
            $qb->andWhere('boardReportMode.id = :reportMode')->setParameter('reportMode', $filterBy['kpiFormat']);
        }

        if (isset($filterBy['employee'])){
            $qb->andWhere('employee.id = :employeeId')->setParameter('employeeId', $filterBy['employee']->getId());
        } elseif(isset($filterBy['lineManager'])){
            $qb->andWhere('lineManager.id = :lineManagerId')->setParameter('lineManagerId', $filterBy['lineManager']);
        }
        if (! in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            $qb->andWhere('lineManager.id = :lineManagerId')->setParameter('lineManagerId', $user->getId());

        }
        $qb->andWhere('board.month IN (:months)')->setParameter('months', $months);
        $qb->groupBy('board.month');
        $qb->addGroupBy('activity.id');
        $qb->addGroupBy('employee.id');
        $qb->orderBy('e.id', 'ASC');
        $results = $qb->getQuery()->getArrayResult();


        $data = [];
        foreach ($results as $result) {
            $data[$result['userId']]['data']['employeeName'] = $result['employeeName'];
            $data[$result['userId']]['data']['designation'] = $result['employeeDesignation'];
            $data[$result['userId']]['data'][$result['month']]['reportMode'] = $result['boardReportFormat'];
            $data[$result['userId']]['data'][$result['month']]['boardReportFormatSlug'] = $result['boardReportFormatSlug'];
            $data[$result['userId']]['data'][$result['month']]['obtainMark'] = $result['obtainMark'];
            $data[$result['userId']]['data'][$result['month']]['grade'] = $result['grade'];
            $data[$result['userId']]['data'][$result['month']]['boardId'] = $result['boardId'];
//            $data[$result['userId']]['activityName'] = $this->getActivities();
//            $data[$result['userId']]['activityName'][$result['activityName']]= $result['activityName'];
            $data[$result['userId']]['mark'][$result['month']][$result['activityName']]= $result['mark'];
        }

        return $data;
    }


    public function getTeamMemberMarksByYear(User $employee, $year)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employeeBoard', 'board');
        $qb->join('board.employee', 'employee');
        $qb->join('e.attribute', 'attribute');
        $qb->join('e.parameter', 'parameter');
        $qb->select('e.mark AS lineManagerMark', 'e.selfMark');
        $qb->addSelect('attribute.id AS attributeId','attribute.name AS attributeName');
        $qb->addSelect('board.month');
        $qb->addSelect('parameter.id AS parameterId', 'parameter.slug AS parameterSlug');
        $qb->where('board.year = :year')->setParameter('year', $year);
        $qb->andWhere('employee.id = :employeeId')->setParameter('employeeId', $employee->getId());

        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        $attributes = [];
        foreach ($results as $key => $result) {
            $monthNumber = (new \DateTime($result['month']))->format('m');

//            $data[$monthNumber . '-' . $result['month']]['month'] = $result['month'];
            $data['month'][$monthNumber . '-' . $result['month']][$result['attributeId']] = [
                "lineManagerMark" => $result['lineManagerMark'],
                "selfMark" => $result['selfMark'],
            ];

            if (array_key_exists('total', $data)){
                if (array_key_exists($result['attributeId'], $data['total'])){

                    if (array_key_exists('totalLineManagerMark',$data['total'][$result['attributeId']] )){
                        $data['total'][$result['attributeId']]['totalLineManagerMark'] += $result['lineManagerMark'];
                    }else{
                        $data['total'][$result['attributeId']]['totalLineManagerMark'] = $result['lineManagerMark'];
                    }

                    if (array_key_exists('totalSelfMark',$data['total'][$result['attributeId']] )){
                        $data['total'][$result['attributeId']]['totalSelfMark'] += $result['selfMark'];
                    }else{
                        $data['total'][$result['attributeId']]['totalSelfMark'] = $result['selfMark'];
                    }
                }else{
                    $data['total'][$result['attributeId']]['totalLineManagerMark'] = $result['lineManagerMark'];
                    $data['total'][$result['attributeId']]['totalSelfMark'] = $result['selfMark'];

                }
            }else{
                $data['total'][$result['attributeId']]['totalLineManagerMark'] = $result['lineManagerMark'];
                $data['total'][$result['attributeId']]['totalSelfMark'] = $result['selfMark'];

            }
            $attributes[$result['attributeId']] =  [
                'name' => $result['attributeName'],
                'parameterSlug' => $result['parameterSlug'],
            ];
            $data['attributes'] = $attributes;
        }

        ksort($data);
        return $data;
    }
    
    public function checkMarkOrSelfMark($board, $parameters)
    {
        $qb = $this->createQueryBuilder('e');
        
        $qb->where('e.employeeBoard = :board')->setParameter('board', $board);
        $qb->andWhere('e.parameter IN (:parameters)')->setParameter('parameters', $parameters);
        $qb->andWhere('e.mark IS NULL OR e.selfMark IS NULL');

        return count($qb->getQuery()->getArrayResult());
    }

}
