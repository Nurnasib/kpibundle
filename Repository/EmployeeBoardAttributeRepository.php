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
        if ($board->getEmployee()->getReportMode()->getSlug() == 'poultry-service') {
            $this->updateEvaluationCriteriaPoultry($board);
        } elseif ($board->getEmployee()->getReportMode()->getSlug() == 'aqua-service') {
            $this->updateEvaluationCriteriaAqua($board);
        } elseif ($board->getEmployee()->getReportMode()->getSlug() == 'cattle-service') {
            $this->updateEvaluationCriteriaCattle($board);
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

        $agentSalesDistribution = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => 'develop-existing-customer-sales-volume'));
        $employeeBoardAttributeForAgentSalesGrowth = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $agentSalesDistribution]);

        if ($employeeBoardAttributeForAgentSalesGrowth) {
            $mark = $this->twentyPercentGrowthAgentNumberPercentageCalculation($commonAgentBetweenYears, $twentyPercentGrowthAgents);
            $employeeBoardAttributeForAgentSalesGrowth->setMark($mark);
            $em->persist($employeeBoardAttributeForAgentSalesGrowth);
            $em->flush();
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


    public function updateEvaluationCriteriaCattle(EmployeeBoard $board)
    {
//        dd(date("01-m-{$board->getYear()}",strtotime('February')));
        $em = $this->_em;
        $filterBy['employeeId'] = $board->getEmployee()->getId();
        $filterBy['monthStart'] = date("{$board->getYear()}-m-01", strtotime($board->getMonth()));
        $filterBy['monthEnd'] = date("{$board->getYear()}-m-t", strtotime($board->getMonth()));

        $monthlyFarmVisitReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-farm-visit-report');
        if ($monthlyFarmVisitReport) {
//            $numberOfReports = (int)$em->getRepository(FcrDetails::class)->getMonthlyFcrAfterSaleTotalReport($filterBy);
            $numberOfReports = 0;
            $mark = $numberOfReports * 0.25;
            $monthlyFarmVisitReport->setMark($mark);
            $monthlyFarmVisitReport->setTargetReport(40);
            $monthlyFarmVisitReport->setAchieveReport($numberOfReports);
            $monthlyFarmVisitReport->setTargetMark(10);
            $em->persist($monthlyFarmVisitReport);
            $em->flush();
        }

        $monthlyFeedPerformanceReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-dairy-fattening-feed-performance-report');
        if ($monthlyFeedPerformanceReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 1;

            $monthlyFeedPerformanceReport->setTargetReport(10);
            $monthlyFeedPerformanceReport->setAchieveReport($numberOfReports);
            $monthlyFeedPerformanceReport->setTargetMark(10);
            $monthlyFeedPerformanceReport->setMark($mark);
            $em->persist($monthlyFeedPerformanceReport);
            $em->flush();
        }

        $monthlyLifeCycleReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-dairy-fattening-life-cycle-report');
        if ($monthlyLifeCycleReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 5;

            $monthlyLifeCycleReport->setTargetReport(2);
            $monthlyLifeCycleReport->setAchieveReport($numberOfReports);
            $monthlyLifeCycleReport->setTargetMark(10);
            $monthlyLifeCycleReport->setMark($mark);
            $em->persist($monthlyLifeCycleReport);
            $em->flush();
        }

        $monthlyFarmerTouchReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-farmers-touch-report');
        if ($monthlyFarmerTouchReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 0.5;

            $monthlyFarmerTouchReport->setTargetReport(20);
            $monthlyFarmerTouchReport->setAchieveReport($numberOfReports);
            $monthlyFarmerTouchReport->setTargetMark(10);
            $monthlyFarmerTouchReport->setMark($mark);
            $em->persist($monthlyFarmerTouchReport);
            $em->flush();
        }

        $monthlyNewFarmerIntroduceReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-new-farm-introduce-report');
        if ($monthlyNewFarmerIntroduceReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 2;

            $monthlyNewFarmerIntroduceReport->setTargetReport(5);
            $monthlyNewFarmerIntroduceReport->setAchieveReport($numberOfReports);
            $monthlyNewFarmerIntroduceReport->setTargetMark(10);
            $monthlyNewFarmerIntroduceReport->setMark($mark);
            $em->persist($monthlyNewFarmerIntroduceReport);
            $em->flush();
        }

        $monthlyAgentUpgradationReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-agent-upgradation-report');
        if ($monthlyAgentUpgradationReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 10;

            $monthlyAgentUpgradationReport->setTargetReport(1);
            $monthlyAgentUpgradationReport->setAchieveReport($numberOfReports);
            $monthlyAgentUpgradationReport->setTargetMark(10);
            $monthlyAgentUpgradationReport->setMark($mark);
            $em->persist($monthlyAgentUpgradationReport);
            $em->flush();
        }

        $monthlyFeedSaleReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-company-wise-feed-sale-price-report');
        if ($monthlyFeedSaleReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 1;

            $monthlyFeedSaleReport->setTargetReport(10);
            $monthlyFeedSaleReport->setAchieveReport($numberOfReports);
            $monthlyFeedSaleReport->setTargetMark(10);
            $monthlyFeedSaleReport->setMark($mark);
            $em->persist($monthlyFeedSaleReport);
            $em->flush();
        }

        $monthlyFarmersTrainingProgramReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-farmers-training-program-report');
        if ($monthlyFarmersTrainingProgramReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 1;

            $monthlyFarmersTrainingProgramReport->setTargetReport(10);
            $monthlyFarmersTrainingProgramReport->setAchieveReport($numberOfReports);
            $monthlyFarmersTrainingProgramReport->setTargetMark(10);
            $monthlyFarmersTrainingProgramReport->setMark($mark);
            $em->persist($monthlyFarmersTrainingProgramReport);
            $em->flush();
        }

        $monthlyDiseasesReport = $this->getAttrbuteForMonthlyReport($board, 'cattle-diseases-report');
        if ($monthlyDiseasesReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 1;

            $monthlyDiseasesReport->setTargetReport(10);
            $monthlyDiseasesReport->setAchieveReport($numberOfReports);
            $monthlyDiseasesReport->setTargetMark(10);
            $monthlyDiseasesReport->setMark($mark);
            $em->persist($monthlyDiseasesReport);
            $em->flush();
        }

        $monthlyCustomerFeedbackReport = $this->getAttrbuteForMonthlyReport($board, 'product-performance-feedback-report');
        if ($monthlyCustomerFeedbackReport) {
            $numberOfReports = 0;
            $mark = 10;

            $monthlyCustomerFeedbackReport->setTargetReport(0);
            $monthlyCustomerFeedbackReport->setAchieveReport($numberOfReports);
            $monthlyCustomerFeedbackReport->setTargetMark(10);
            $monthlyCustomerFeedbackReport->setMark($mark);
            $em->persist($monthlyCustomerFeedbackReport);
            $em->flush();
        }
    }

    public function updateEvaluationCriteriaAqua(EmployeeBoard $board)
    {
//        dd(date("01-m-{$board->getYear()}",strtotime('February')));
        $em = $this->_em;
        $filterBy['employeeId'] = $board->getEmployee()->getId();
        $filterBy['monthStart'] = date("{$board->getYear()}-m-01", strtotime($board->getMonth()));
        $filterBy['monthEnd'] = date("{$board->getYear()}-m-t", strtotime($board->getMonth()));

        $monthlyLifeCycleReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-life-cycle-report');
        if ($monthlyLifeCycleReport) {
//            $numberOfReports = (int)$em->getRepository(FcrDetails::class)->getMonthlyFcrAfterSaleTotalReport($filterBy);
            $numberOfReports = 0;
            $mark = $numberOfReports * 5;
            $monthlyLifeCycleReport->setMark($mark);
            $monthlyLifeCycleReport->setTargetReport(2);
            $monthlyLifeCycleReport->setAchieveReport($numberOfReports);
            $monthlyLifeCycleReport->setTargetMark(10);
            $em->persist($monthlyLifeCycleReport);
            $em->flush();
        }

        $monthlyCompanySpeciesFcrReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-company-species-wise-avg-fcr-report');
        if ($monthlyCompanySpeciesFcrReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 2;

            $monthlyCompanySpeciesFcrReport->setTargetReport(5);
            $monthlyCompanySpeciesFcrReport->setAchieveReport($numberOfReports);
            $monthlyCompanySpeciesFcrReport->setTargetMark(10);
            $monthlyCompanySpeciesFcrReport->setMark($mark);
            $em->persist($monthlyCompanySpeciesFcrReport);
            $em->flush();
        }

        $monthlyFeedSaleReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-company-wise-feed-sale-report');
        if ($monthlyFeedSaleReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 10;

            $monthlyFeedSaleReport->setTargetReport(1);
            $monthlyFeedSaleReport->setAchieveReport($numberOfReports);
            $monthlyFeedSaleReport->setTargetMark(10);
            $monthlyFeedSaleReport->setMark($mark);
            $em->persist($monthlyFeedSaleReport);
            $em->flush();
        }

        $monthlyNewFarmerTouchReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-new-farmertouch-report');
        if ($monthlyNewFarmerTouchReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 1;

            $monthlyNewFarmerTouchReport->setTargetReport(10);
            $monthlyNewFarmerTouchReport->setAchieveReport($numberOfReports);
            $monthlyNewFarmerTouchReport->setTargetMark(10);
            $monthlyNewFarmerTouchReport->setMark($mark);
            $em->persist($monthlyNewFarmerTouchReport);
            $em->flush();
        }

        $monthlyNewFarmerIntroduceReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-new-farmer-introduce-report');
        if ($monthlyNewFarmerIntroduceReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 10;

            $monthlyNewFarmerIntroduceReport->setTargetReport(1);
            $monthlyNewFarmerIntroduceReport->setAchieveReport($numberOfReports);
            $monthlyNewFarmerIntroduceReport->setTargetMark(10);
            $monthlyNewFarmerIntroduceReport->setMark($mark);
            $em->persist($monthlyNewFarmerIntroduceReport);
            $em->flush();
        }

        $monthlyFarmerTrainingReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-farmers-training-report');
        if ($monthlyFarmerTrainingReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 1;

            $monthlyFarmerTrainingReport->setTargetReport(10);
            $monthlyFarmerTrainingReport->setAchieveReport($numberOfReports);
            $monthlyFarmerTrainingReport->setTargetMark(10);
            $monthlyFarmerTrainingReport->setMark($mark);
            $em->persist($monthlyFarmerTrainingReport);
            $em->flush();
        }

        $monthlyLessCostingFarmReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-less-costing-farm-report');
        if ($monthlyLessCostingFarmReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 1;

            $monthlyLessCostingFarmReport->setTargetReport(10);
            $monthlyLessCostingFarmReport->setAchieveReport($numberOfReports);
            $monthlyLessCostingFarmReport->setTargetMark(10);
            $monthlyLessCostingFarmReport->setMark($mark);
            $em->persist($monthlyLessCostingFarmReport);
            $em->flush();
        }

        $monthlyNewAgentCreationReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-new-agent-creation-and-up-gradation-report');
        if ($monthlyNewAgentCreationReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 1;

            $monthlyNewAgentCreationReport->setTargetReport(10);
            $monthlyNewAgentCreationReport->setAchieveReport($numberOfReports);
            $monthlyNewAgentCreationReport->setTargetMark(10);
            $monthlyNewAgentCreationReport->setMark($mark);
            $em->persist($monthlyNewAgentCreationReport);
            $em->flush();
        }

        $monthlySpeciesWiseFishPriceReport = $this->getAttrbuteForMonthlyReport($board, 'aqua-species-wise-fish-price-report');
        if ($monthlySpeciesWiseFishPriceReport) {
            $numberOfReports = 0;
            $mark = $numberOfReports * 1;

            $monthlySpeciesWiseFishPriceReport->setTargetReport(10);
            $monthlySpeciesWiseFishPriceReport->setAchieveReport($numberOfReports);
            $monthlySpeciesWiseFishPriceReport->setTargetMark(10);
            $monthlySpeciesWiseFishPriceReport->setMark($mark);
            $em->persist($monthlySpeciesWiseFishPriceReport);
            $em->flush();
        }

        $monthlyCustomerFeedbackReport = $this->getAttrbuteForMonthlyReport($board, 'customer-feedback-report');
        if ($monthlyCustomerFeedbackReport) {
            $numberOfReports = 0;
            $mark = 10;

            $monthlyCustomerFeedbackReport->setTargetReport(0);
            $monthlyCustomerFeedbackReport->setAchieveReport($numberOfReports);
            $monthlyCustomerFeedbackReport->setTargetMark(10);
            $monthlyCustomerFeedbackReport->setMark($mark);
            $em->persist($monthlyCustomerFeedbackReport);
            $em->flush();
        }
    }

    public function updateEvaluationCriteriaPoultry(EmployeeBoard $board)
    {
//        dd(date("01-m-{$board->getYear()}",strtotime('February')));
        $em = $this->_em;
        $filterBy['employeeId'] = $board->getEmployee()->getId();
        $filterBy['monthStart'] = date("{$board->getYear()}-m-01", strtotime($board->getMonth()));
        $filterBy['monthEnd'] = date("{$board->getYear()}-m-t", strtotime($board->getMonth()));

        $monthlyFcrReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-fcr-after-sale-report');
        if ($monthlyFcrReport) {
            $numberOfReports = (int)$em->getRepository(FcrDetails::class)->getMonthlyFcrAfterSaleTotalReport($filterBy);
            $mark = $numberOfReports * 0.25;
            $monthlyFcrReport->setMark($mark);
            $monthlyFcrReport->setTargetReport(40);
            $monthlyFcrReport->setAchieveReport($numberOfReports);
            $monthlyFcrReport->setTargetMark(10);
            $em->persist($monthlyFcrReport);
            $em->flush();
        }

        $monthlyBroilerBeforeLayerPerformanceReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-broiler-before-sale-layer-performance-report');
        if ($monthlyBroilerBeforeLayerPerformanceReport) {
            $totalBroilerBeforeSale = (int)$em->getRepository(FcrDetails::class)->getMonthlyBroilerBeforeSaleTotalReport($filterBy);
            $totalLayerPerformance = (int)$em->getRepository(LayerPerformanceDetails::class)->getMonthlyLayerPerformanceTotalReport($filterBy);
            $numberOfReports = $totalBroilerBeforeSale + $totalLayerPerformance;
            $mark = $numberOfReports * 0.125;

            $monthlyBroilerBeforeLayerPerformanceReport->setTargetReport(80);
            $monthlyBroilerBeforeLayerPerformanceReport->setAchieveReport($numberOfReports);
            $monthlyBroilerBeforeLayerPerformanceReport->setTargetMark(10);
            $monthlyBroilerBeforeLayerPerformanceReport->setMark($mark);
            $em->persist($monthlyBroilerBeforeLayerPerformanceReport);
            $em->flush();
        }

        $monthlyBroilerLayerLifeCycleReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-broiler-life-cycle-layer-life-cycle-report');
        if ($monthlyBroilerLayerLifeCycleReport) {
            $totalBroilerLifeCycle = (int)$em->getRepository(ChickLifeCycle::class)->getMonthlyBroilerLifeCycleTotalReport($filterBy);
            $totalLayerLifeCycle = (int)$em->getRepository(LayerLifeCycle::class)->getMonthlyLayerLifeCycleTotalReport($filterBy);
            $numberOfReports = $totalBroilerLifeCycle + $totalLayerLifeCycle;
            $mark = $numberOfReports * 2;

            $monthlyBroilerLayerLifeCycleReport->setTargetReport(5);
            $monthlyBroilerLayerLifeCycleReport->setAchieveReport($numberOfReports);
            $monthlyBroilerLayerLifeCycleReport->setTargetMark(10);
            $monthlyBroilerLayerLifeCycleReport->setMark($mark);
            $em->persist($monthlyBroilerLayerLifeCycleReport);
            $em->flush();
        }

        $monthlyNewFarmInformationSurveyReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-new-farm-information-report');
        if ($monthlyNewFarmInformationSurveyReport) {
            $numberOfReports = (int)$em->getRepository(FarmerTouchReport::class)->getMonthlyNewfarmInformationOrSurveyTotalReport($filterBy);
            $mark = $numberOfReports * 0.5;

            $monthlyNewFarmInformationSurveyReport->setTargetReport(20);
            $monthlyNewFarmInformationSurveyReport->setAchieveReport($numberOfReports);
            $monthlyNewFarmInformationSurveyReport->setTargetMark(10);
            $monthlyNewFarmInformationSurveyReport->setMark($mark);
            $em->persist($monthlyNewFarmInformationSurveyReport);
            $em->flush();
        }

        $monthlyLessCostingFarmReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-less-costing-farm-report');
        if ($monthlyLessCostingFarmReport) {
            $numberOfReports = (int)$em->getRepository(CostBenefitAnalysisForLessCostingFarm::class)->getMonthlyLessCostingFarmOrSkillFarmDevelopTotalReport($filterBy);
            $mark = $numberOfReports * 10;

            $monthlyLessCostingFarmReport->setTargetReport(1);
            $monthlyLessCostingFarmReport->setAchieveReport($numberOfReports);
            $monthlyLessCostingFarmReport->setTargetMark(10);
            $monthlyLessCostingFarmReport->setMark($mark);
            $em->persist($monthlyLessCostingFarmReport);
            $em->flush();
        }

        $monthlyAntibioticFreeFarmReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-antibiotic-free-farm-report');
        if ($monthlyAntibioticFreeFarmReport) {
            $numberOfReports = (int)$em->getRepository(AntibioticFreeFarm::class)->getMonthlyAntibioticFreeFarmTotalReport($filterBy);
            $mark = $numberOfReports * 10;

            $monthlyAntibioticFreeFarmReport->setTargetReport(1);
            $monthlyAntibioticFreeFarmReport->setAchieveReport($numberOfReports);
            $monthlyAntibioticFreeFarmReport->setTargetMark(10);
            $monthlyAntibioticFreeFarmReport->setMark($mark);
            $em->persist($monthlyAntibioticFreeFarmReport);
            $em->flush();
        }

        $monthlyFarmersTrainingReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-farmers-training-report');
        if ($monthlyFarmersTrainingReport) {
            $numberOfReports = (int)$em->getRepository(FarmerTrainingReport::class)->getMonthlyfarmersTrainingProgramTotalReport($filterBy);
            $mark = $numberOfReports * 10;

            $monthlyFarmersTrainingReport->setTargetReport(1);
            $monthlyFarmersTrainingReport->setAchieveReport($numberOfReports);
            $monthlyFarmersTrainingReport->setTargetMark(10);
            $monthlyFarmersTrainingReport->setMark($mark);
            $em->persist($monthlyFarmersTrainingReport);
            $em->flush();
        }

        $monthlyNewFarmerIntroduceReport = $this->getAttrbuteForMonthlyReport($board, 'new-farm-introduce-report');
        if ($monthlyNewFarmerIntroduceReport) {
            $numberOfReports = (int)$em->getRepository(FarmerIntroduceDetails::class)->getMonthlyNewFarmerIntroduceTotalReport($filterBy);
            $mark = $numberOfReports * 10;

            $monthlyNewFarmerIntroduceReport->setTargetReport(1);
            $monthlyNewFarmerIntroduceReport->setAchieveReport($numberOfReports);
            $monthlyNewFarmerIntroduceReport->setTargetMark(10);
            $monthlyNewFarmerIntroduceReport->setMark($mark);
            $em->persist($monthlyNewFarmerIntroduceReport);
            $em->flush();
        }

        $monthlyTroubleshootingDiseasesReport = $this->getAttrbuteForMonthlyReport($board, 'poultry-troubleshooting-diseases-mapping-report');
        if ($monthlyTroubleshootingDiseasesReport) {
            $numberOfReports = (int)$em->getRepository(FarmerIntroduceDetails::class)->getMonthlyNewFarmerIntroduceTotalReport($filterBy);
            $mark = $numberOfReports * 10;

            $monthlyTroubleshootingDiseasesReport->setTargetReport(1);
            $monthlyTroubleshootingDiseasesReport->setAchieveReport($numberOfReports);
            $monthlyTroubleshootingDiseasesReport->setTargetMark(10);
            $monthlyTroubleshootingDiseasesReport->setMark($mark);
            $em->persist($monthlyTroubleshootingDiseasesReport);
            $em->flush();
        }

        $productPerformanceFeedbackReport = $this->getAttrbuteForMonthlyReport($board, 'product-performance-feedback-report');
        if ($productPerformanceFeedbackReport) {
            $numberOfReports = 0;
            $mark = 10;

            $productPerformanceFeedbackReport->setTargetReport(0);
            $productPerformanceFeedbackReport->setAchieveReport($numberOfReports);
            $productPerformanceFeedbackReport->setTargetMark(10);
            $productPerformanceFeedbackReport->setMark($mark);
            $em->persist($productPerformanceFeedbackReport);
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

                    $poultryFeedEntity->setMark($mark);
                    $em->persist($poultryFeedEntity);
                    $em->flush();

                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distribution]);
                    if ($employeeBoardAttribute) {
                        $employeeBoardAttribute->setMark($entity->getMark());
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
                    $aquaFeedEntity->setMark($mark);
                    $em->persist($aquaFeedEntity);
                    $em->flush();

                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distribution]);
                    if ($employeeBoardAttribute) {
                        $employeeBoardAttribute->setMark($entity->getMark());
                        $em->persist($employeeBoardAttribute);
                        $em->flush();
                    }

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
                    $cattleFeedEntity->setMark($mark);
                    $em->persist($cattleFeedEntity);
                    $em->flush();

                    $employeeBoardAttribute = $this->findOneBy(['employeeBoard' => $board, 'attribute' => $distribution]);
                    if ($employeeBoardAttribute) {
                        $employeeBoardAttribute->setMark($entity->getMark());
                        $em->persist($employeeBoardAttribute);
                        $em->flush();
                    }

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
