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

use App\Entity\Core\Agent;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Terminalbd\KpiBundle\Entity\AgentCategory;
use Terminalbd\KpiBundle\Entity\AgentGradeStandard;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\DocumentUpload;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class AgentCategoryRepository extends EntityRepository
{
    public function getAllAgentWithGrade()
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.gradeStandard', 'gradeStandard');
        $qb->select('e.quantity', 'e.month', 'e.year');
        $qb->addSelect('agent.name AS agentName');
        $qb->addSelect('gradeStandard.grade AS gradeLetter');
        $qb->orderBy('agent.name', 'ASC');
        return $qb->getQuery()->getArrayResult();
    }
    public function getAgentGradeMonthWise($filterBy)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.gradeStandard', 'gradeStandard');
        $qb->join('agent.district', 'district');
        $qb->join('district.parent', 'region');
        $qb->join('region.parent', 'zone');
        $qb->select('e.average','e.month','e.year');
        $qb->addSelect('agent.agentId AS agentId', 'agent.name AS agentName');
        $qb->addSelect('district.name AS agentDistrictName');
        $qb->addSelect('region.name AS agentRegionName');
        $qb->addSelect('zone.name AS agentZoneName');
        $qb->addSelect('gradeStandard.grade');
        $qb->where('e.month = :prevMonth')->setParameter('prevMonth', $filterBy['month']);
        $qb->andWhere('e.year = :prevYear')->setParameter('prevYear', $filterBy['year']);
        if(isset($filterBy['district'])){
            $qb->andWhere('district.id =:district')->setParameter('district',$filterBy['district']);
        }
        $qb->groupBy('agent.agentId');
        $qb->orderBy('agent.agentId');
//        $qb->andWhere('agent.id = :agentId')->setParameter('agentId', 1271);
//        $qb->where('e.year', ':prevYear')->setParameter('prevYear', 2020);
        $array = [];
        $results = $qb->getQuery()->getArrayResult();
        foreach ($results as $row){
            $array[$row['agentId']]= $row;
        }

        return $array;
    }

    public function getPreviousYearCategoryAndAverage($prevYear, $month)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.gradeStandard', 'gradeStandard');
        $qb->select('e.average');
        $qb->addSelect('gradeStandard.grade');
        $qb->addSelect('agent.agentId AS agentId');
        $qb->where('e.month = :month')->setParameter('month', $month);
        $qb->andWhere('e.year = :prevYear')->setParameter('prevYear', $prevYear);
        $qb->groupBy('agent.agentId');
        $qb->orderBy('agent.agentId');
        $results = $qb->getQuery()->getArrayResult();
        $array = [];
        foreach ($results as $row){
            $array[$row['agentId']]= $row;
        }

        return $array;
    }

    public function insertAgentOrderInAgentCategory($monthName, $year, $file)
    {

        $createdMonth = $year . '-' .date('m', strtotime($monthName)) . '-01';

        $query = "INSERT INTO kpi_agent_category(agent_id, quantity, month, year, created_at, document_upload_id, created_month)
                    SELECT agent_id, SUM(quantity) AS totalQuantity, month, year, CURRENT_TIMESTAMP, document_upload_id, :createdMonth 
                    FROM kpi_agent_order
                    WHERE month = :month AND year = :year
                    GROUP BY agent_id, document_upload_id";

        $em = $this->_em;
        $stmt = $em->getConnection()->prepare($query);
        $stmt->bindValue('month', $monthName);
        $stmt->bindValue('year', $year);
        $stmt->bindValue('createdMonth', $createdMonth);
        $insert = $stmt->execute();

        if($insert){
            $updateGradeAndAvg = "UPDATE  kpi_agent_category AS kac
                                INNER JOIN
                                (
                                SELECT agent_id, AVG(quantity) AS avg_val
                                FROM kpi_agent_category
                                WHERE year = :year
                                GROUP BY agent_id
                                ) b ON kac.agent_id = b.agent_id
                                SET kac.average = b.avg_val,
                                kac.grade_standard_id = (
                                CASE 
                                WHEN b.avg_val >= 100 THEN 1
                                WHEN b.avg_val >= 50 AND b.avg_val < 100 THEN 2
                                WHEN b.avg_val >= 30 AND b.avg_val < 50 THEN 3
                                ELSE 
                                4
                                END
                                )   
                            WHERE kac.document_upload_id = :fileId";

            $stmtUpdate = $em->getConnection()->prepare($updateGradeAndAvg);
            $stmtUpdate->bindValue('year', $year);
            $stmtUpdate->bindValue('fileId', $file->getId());
            $update = $stmtUpdate->execute();

            if($update){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getPreviousYearCategory($districsId)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->join('e.agent', 'agent');
        $qb->join('agent.district', 'agentDistrict');
        $qb->select();
        $qb->where('agentDistrict.id IN (:agentDistrictId)')->setParameter('agentDistrictId', $districsId);
        $qb->andWhere("e.month = 'December'");
        $qb->andWhere('e.year = :year')->setParameter('year', 2020);
        return $qb->getQuery()->getArrayResult();
    }



    private function insertPreviousYearCategory(EmployeeBoard $board, $districtsId)
    {
        $prevYear = $board->getYear()-1;

        $findRecord = $this->findBy(['month' => $board->getMonth(), 'year' => $prevYear]);
        if (!$findRecord){
            if ($board->getMonth() == 'January'){
                $districtsSales = $this->_em->getRepository(AgentOrder::class)->getAgentWithSalesQuantity(['January'], $prevYear, $districtsId);
                $createdMonth = $prevYear . '-01-01';

                if ($districtsSales){ //if previous year january agent sales exists
                    foreach ($districtsSales as $districtsSale) {
                        $findCategory = $this->findOneBy(['agent' => $districtsSale['agentId'], 'month' => 'January', 'year' => $prevYear]);

//                        $avg = $districtsSale['totalQuantity'];
                        $findGrade = $this->getGradeObj($districtsSale['totalQuantity']);

                        if (!$findCategory){
                            $agentObj = $this->_em->getRepository(Agent::class)->find($districtsSale['agentId']);

                            if ($agentObj){
                                $monthYear = 'January,' . $prevYear;
                                $findDocument = $this->_em->getRepository(DocumentUpload::class)->findOneBy(['monthYear' => $monthYear, 'title' => 'agent sales']);

                                $newCategory = new AgentCategory();
                                $newCategory->setAgent($agentObj);
                                $newCategory->setGradeStandard($findGrade);
                                $newCategory->setQuantity($districtsSale['totalQuantity']);
                                $newCategory->setMonth('January');
                                $newCategory->setYear($prevYear);
                                $newCategory->setCreatedAt(new \DateTimeImmutable('now'));
                                $newCategory->setUpdatedAt(new \DateTimeImmutable('now'));
                                $newCategory->setDocumentUpload($findDocument);
                                $newCategory->setAverage($districtsSale['totalQuantity']);
                                $newCategory->setCreatedMonth(new \DateTimeImmutable($createdMonth));
                                $newCategory->setMonthCount(1);
                                $newCategory->setCumulativeQuantity($districtsSale['totalQuantity']);

                                $this->_em->persist($newCategory);
                                $this->_em->flush();
                            }

                        }
                    }

                }else{ //if previous year january agent sales does exists
                    $districtsSales = $this->_em->getRepository(AgentOrder::class)->getAgentWithSalesQuantity(['January'], $board->getYear(), $districtsId);

                    $findCategory = $this->findOneBy(['agent' => $districtsSale['agentId'], 'month' => 'January', 'year' => $prevYear]);

                    $findGrade = $this->getGradeObj(0);

                    if (!$findCategory){
                        $agentObj = $this->_em->getRepository(Agent::class)->find($districtsSale['agentId']);

                        if ($agentObj){
                            $monthYear = 'January,' . $prevYear;
                            $findDocument = $this->_em->getRepository(DocumentUpload::class)->findOneBy(['monthYear' => $monthYear, 'title' => 'agent sales']);

                            $newCategory = new AgentCategory();
                            $newCategory->setAgent($agentObj);
                            $newCategory->setGradeStandard($findGrade);
                            $newCategory->setQuantity(0);
                            $newCategory->setMonth('January');
                            $newCategory->setYear($prevYear);
                            $newCategory->setCreatedAt(new \DateTimeImmutable('now'));
                            $newCategory->setUpdatedAt(new \DateTimeImmutable('now'));
                            $newCategory->setDocumentUpload($findDocument);
                            $newCategory->setAverage(0);
                            $newCategory->setCreatedMonth(new \DateTimeImmutable($createdMonth));
                            $newCategory->setMonthCount(1);
                            $newCategory->setCumulativeQuantity(0);


                            $this->_em->persist($newCategory);
                            $this->_em->flush();
                        }
                    }
                }
            }else{
/*                $months = [];

                for ($i = 1; $i <= date('m', strtotime($board->getMonth())); $i++){
                    $months[] = date('F', strtotime('2022-' . $i . '-01'));
                }*/

                $prevMonth = date('F', strtotime($board->getMonth() . ',' . $board->getYear() . "last month"));
                $monthCount = date('m', strtotime($board->getMonth() . ',' . $board->getYear()));
                $createdMonth = $prevYear . '-' . $monthCount . '-01';
//                $monthYear = 'January,' . $prevYear;
//                $findDocument = $this->_em->getRepository(DocumentUpload::class)->findOneBy(['monthYear' => $monthYear, 'title' => 'agent sales']);


                $query = "INSERT INTO kpi_agent_category(agent_id, grade_standard_id, quantity, month, year, created_at, average, created_month, month_count, cumulative_quantity)
            SELECT agent_id, grade_standard_id, quantity, :currentMonth, year, CURRENT_TIMESTAMP, average, :createdMonth, :monthCount, cumulative_quantity
            FROM kpi_agent_category
            WHERE month = :prevMonth AND year = :year";

                $em = $this->_em;
                $stmt = $em->getConnection()->prepare($query);
                $stmt->bindValue('prevMonth', $prevMonth);
                $stmt->bindValue('currentMonth', $board->getMonth());
                $stmt->bindValue('year', $prevYear);
                $stmt->bindValue('createdMonth', $createdMonth);
                $stmt->bindValue('monthCount', $monthCount);
                $insert = $stmt->execute();

            }
        }




//
//
//        $prevYear = $board->getYear()-1;
//        $months = [];
//
//        for ($i = 1; $i <= date('m', strtotime($board->getMonth())); $i++){
//            $months[] = date('F', strtotime('2022-' . $i . '-01'));
//        }
//        $createdMonth = $board->getYear() . '-' . date('m', strtotime($board->getMonth())) . '-01';
//
//        $districtsSales = $this->_em->getRepository(AgentOrder::class)->getAgentWithSalesQuantity($months, $prevYear, $districtsId);
//
//
//        $qb = $this->createQueryBuilder('e');
//        $qb->join('e.agent','agent');
//        $qb->join('agent.district','district');
//
//        $qb->select('agent.id', 'agent.agentId', 'e.year');
//
//        $qb->where('e.year IN (:years)')->setParameter('years', [$board->getYear()-1, $board->getYear()]);
//        $qb->andWhere('district.id IN (:districtsId)')->setParameter('districtsId', $districtsId);
//        $qb->andWhere('e.month = :month')->setParameter('month', $board->getMonth());
//
//        $results = $qb->getQuery()->getArrayResult();
//        $data = [];
//        foreach ($results as $result) {
//            $data[$result['year']][] = $result['id'];
//        }
//        if (!array_key_exists($board->getYear()-1, $data)){
//            $data[$board->getYear()-1] = [];
//        }
//        if (!array_key_exists($board->getYear(), $data)){
//            $data[$board->getYear()] = [];
//        }
//
//        $agentsNotInCurrentMonth = array_diff($data[$board->getYear()-1], $data[$board->getYear()]); // get previous year agents that not in current month
//        $agentsNotInPreviousYear = array_diff($data[$board->getYear()], $data[$board->getYear()-1]); // get current month agents that not in previous year
//        $commonAgents = array_intersect($data[$board->getYear()], $data[$board->getYear()-1]);

//        $totalUniqueAgents = array_unique(array_merge($data[$board->getYear()-1], $data[$board->getYear()]));

//        $agentsNotInCurrentMonthAndCommonAgents = array_merge($agentsNotInCurrentMonth, $commonAgents);

//
//        $months = [];
//
//        for ($i = 1; $i <= date('m', strtotime($board->getMonth())); $i++){
//            $months[] = date('F', strtotime('2022-' . $i . '-01'));
//        }
//        $createdMonth = $board->getYear() . '-' . date('m', strtotime($board->getMonth())) . '-01';
//
//
//        foreach ($agentsNotInCurrentMonthAndCommonAgents as $id) {
//            $agentObj = $this->_em->getRepository(Agent::class)->find($id);
//
//            if ($agentObj){
//
//                $findCategory = $this->findOneBy(['agent' => $agentObj, 'month' => $board->getMonth(), 'year' => $board->getYear()]);
//                $avg = $this->getQuantitySum($agentObj, $months, $board->getYear()) / count($months);
//                $findGrade = $this->getGradeObj($avg);
//
//                if (!$findCategory){
//
//                    $monthYear = $board->getMonth() . ',' . $board->getYear();
//                    $findDocument = $this->_em->getRepository(DocumentUpload::class)->findOneBy(['monthYear' => $monthYear, 'title' => 'agent sales']);
//
//                    $newCategory = new AgentCategory();
//                    $newCategory->setAgent($agentObj);
//                    $newCategory->setGradeStandard($findGrade);
//                    $newCategory->setQuantity(0);
//                    $newCategory->setMonth($board->getMonth());
//                    $newCategory->setYear($board->getYear());
//                    $newCategory->setCreatedAt(new \DateTimeImmutable('now'));
//                    $newCategory->setUpdatedAt(new \DateTimeImmutable('now'));
//                    $newCategory->setDocumentUpload($findDocument);
//                    $newCategory->setAverage($avg);
//                    $newCategory->setCreatedMonth(new \DateTimeImmutable($createdMonth));
//
//                    $this->_em->persist($newCategory);
//                    $this->_em->flush();
//                }else{
//                    $findCategory->setAverage($avg);
//                    $findCategory->setGradeStandard($findGrade);
//
//                    $this->_em->flush();
//                }
//            }
//        }
//        foreach ($agentsNotInPreviousYear as $id) {
//            $agentObj = $this->_em->getRepository(Agent::class)->find($id);
//
//            if ($agentObj){
//                $findCategory = $this->findOneBy(['agent' => $agentObj, 'month' => $board->getMonth(), 'year' => $board->getYear()]);
//                if ($findCategory){
//                    $grade = $this->getGradeObj($findCategory->getQuantity());
//                    $findCategory->setAverage($findCategory->getQuantity());
//                    $findCategory->setGradeStandard($grade);
//
//                    $this->_em->flush();
//
//                }
//
//                $monthYear = $board->getMonth() . ',' . ($board->getYear()-1);
//                $findDocument = $this->_em->getRepository(DocumentUpload::class)->findOneBy(['monthYear' => $monthYear, 'title' => 'agent sales']);
//
//                $avg = $this->getQuantitySum($agentObj, $months, ($board->getYear()-1)) / count($months);
//                $prevYearEntry = new AgentCategory();
//                $prevYearEntry->setAgent($agentObj);
//                $prevYearEntry->setQuantity(0);
//                $prevYearEntry->setMonth($board->getMonth());
//                $prevYearEntry->setYear($board->getYear()-1);
//                $prevYearEntry->setAverage($avg);
//                $prevYearEntry->setCreatedMonth(new \DateTimeImmutable($createdMonth));
//                $prevYearEntry->setCreatedAt(new \DateTimeImmutable("now"));
//                $prevYearEntry->setUpdatedAt(new \DateTimeImmutable("now"));
//                $prevYearEntry->setDocumentUpload($findDocument);
//                $prevYearEntry->setGradeStandard($this->getGradeObj($avg));
//
//                $this->_em->persist($prevYearEntry);
//                $this->_em->flush();
//            }
//        }
    }

    private function getGradeObj($amount){
        if ($amount >= 100){
            $grade = 'A';
        }elseif ($amount >= 50 && $amount < 100){
            $grade = 'B';
        }elseif ($amount >= 30 && $amount < 50){
            $grade = 'C';
        }elseif ($amount < 30){
            $grade = 'D';
        }
        return $this->_em->getRepository(AgentGradeStandard::class)->findOneBy(['grade' => $grade]);
    }

    private function getQuantitySum($agent, $months, $year)
    {
        $qb =$this->createQueryBuilder('e');
        $qb->select('SUM(e.quantity)');

        $qb->where('e.year = :year')->setParameter('year', $year);
        $qb->andWhere('e.month IN (:months)')->setParameter('months', $months);
        $qb->andWhere('e.agent = :agent')->setParameter('agent', $agent);

        $qb->groupBy('e.agent');

        try {
            return $qb->getQuery()->getSingleScalarResult();
        } catch (NoResultException $e) {
            return 0;
        } catch (NonUniqueResultException $e) {
            return 0;
        }
    }
    public function getCategoryUpgradationMarks(EmployeeBoard $board, $gradeLetters, $districtsId)
    {
        $this->insertPreviousYearCategory($board, $districtsId);

        if ($board->getMonth() != 'January'){
            $prevMonth = date('F', strtotime($board->getMonth() . ',' . $board->getYear() . "last month"));
            $monthCount = date('m', strtotime($board->getMonth() . ',' . $board->getYear()));
            $createdMonth = $board->getYear() . '-' . $monthCount . '-01';
//                $monthYear = 'January,' . $prevYear;
//                $findDocument = $this->_em->getRepository(DocumentUpload::class)->findOneBy(['monthYear' => $monthYear, 'title' => 'agent sales']);


            $prevMonthRecords = $this->findBy(['month' => $prevMonth, 'year' => $board->getYear()]);


            foreach ($prevMonthRecords as $record) {

                $exist = $this->findOneBy(['agent' => $record->getAgent(), 'month' => $board->getMonth(), 'year' => $board->getYear()]);
                $currentMonthRecord = $exist ? $exist : new AgentCategory();

                $currentMonthRecord->setAgent($record->getAgent());
                $currentMonthRecord->setGradeStandard(null);
                $currentMonthRecord->setQuantity(0);
                $currentMonthRecord->setMonth($board->getMonth());
                $currentMonthRecord->setYear($board->getYear());
                $currentMonthRecord->setCreatedAt(new \DateTimeImmutable("now"));
                $currentMonthRecord->setCreatedMonth(new \DateTimeImmutable($createdMonth));
                $currentMonthRecord->setMonthCount(($record->getMonthCount() + 1));
                $currentMonthRecord->setCumulativeQuantity($record->getCumulativeQuantity());

                $this->_em->persist($currentMonthRecord);
                $this->_em->flush();

            }
            /*$query = "INSERT INTO kpi_agent_category(agent_id, quantity, month, year, created_at, average, created_month, month_count, cumulative_quantity)
            SELECT agent_id, 0, :currentMonth, year, CURRENT_TIMESTAMP, 0, :createdMonth, :monthCount, cumulative_quantity
            FROM kpi_agent_category
            WHERE month = :prevMonth AND year = :year";

            $em = $this->_em;
            $stmt = $em->getConnection()->prepare($query);
            $stmt->bindValue('prevMonth', $prevMonth);
            $stmt->bindValue('month', $prevMonth);
            $stmt->bindValue('currentMonth', $board->getMonth());
            $stmt->bindValue('year', $board->getYear());
            $stmt->bindValue('createdMonth', $createdMonth);
            $stmt->bindValue('monthCount', $monthCount);
            $insert = $stmt->execute();*/
        }

        $districtsIdString = implode(',' , $districtsId);
        $agentOrderQuery = "SELECT agent_id, SUM(quantity) AS totalQuantity, month, year, document_upload_id
                    FROM kpi_agent_order
                    WHERE month = :month AND year = :year AND district_id IN ($districtsIdString)
                    GROUP BY agent_id";
        $stmt = $this->_em->getConnection()->prepare($agentOrderQuery);
        $stmt->bindValue('month', $board->getMonth());
        $stmt->bindValue('year', $board->getYear());
        $stmt->execute();
        $data = $stmt->fetchAll();


//        $prevMonth = date('F', strtotime($board->getMonth() . ',' . $board->getYear() . "last month"));

        foreach ($data as $item) {

//            /** @var AgentCategory $findRecord**/
//            $findRecord = $this->findOneBy(['agent' => $item['agent_id'],'month' => $prevMonth, 'year' => $item['year']]);
//            $findTotalRecord = $this->findBy(['agent' => $item['agent_id'], 'year' => $item['year']]);
//
//            $cumulativeQty = $item['totalQuantity'] + ($findRecord ? $findRecord->getCumulativeQuantity() : 0);

/*            $agentFindQuery = "SELECT id FROM kpi_agent_category WHERE month = :month AND year = :year AND agent_id = :agent_id";
            $stmt = $this->_em->getConnection()->prepare($agentFindQuery);
            $stmt->bindValue('agent_id', $item['agent_id']);
            $stmt->bindValue('month', $item['month']);
            $stmt->bindValue('year', $item['year']);
            $stmt->execute();
            $findCategory = $stmt->fetch();*/

            $findCategory = $this->findOneBy(['month' => $item['month'], 'year' => $item['year'], 'agent' => $item['agent_id']]);
            $findDocument = $this->_em->getRepository(DocumentUpload::class)->find($item['document_upload_id']);


            if (!$findCategory){

                $agent = $this->_em->getRepository(Agent::class)->find($item['agent_id']);
                if ($agent){
                    $monthCount = date('m', strtotime($board->getMonth() . ',' . $board->getYear()));
                    $createdMonth = $board->getYear() . '-' . $monthCount . '-01';

                    $findAgentRecords = $this->findBy(['month' => $item['month'], 'year' => $item['year'], 'agent' => $agent]);
                    $monthCount = (count($findAgentRecords) + 1);
                    $avg = $item['totalQuantity'] / $monthCount;
                    $grade = $this->getGradeObj($avg);

                    $newCategory = new AgentCategory();
                    $newCategory->setAgent($agent);
                    $newCategory->setGradeStandard($grade);
                    $newCategory->setQuantity($item['totalQuantity']);
                    $newCategory->setMonth($item['month']);
                    $newCategory->setYear($item['year']);
                    $newCategory->setCreatedAt(new \DateTimeImmutable("now"));
                    $newCategory->setDocumentUpload($findDocument);
                    $newCategory->setAverage($avg);
                    $newCategory->setCreatedMonth(new \DateTimeImmutable($createdMonth));
                    $newCategory->setMonthCount($monthCount);
                    $newCategory->setCumulativeQuantity($item['totalQuantity']);

                    $this->_em->persist($newCategory);
                    $this->_em->flush();
                }
/*


                $avg = $cumulativeQty / (count($findTotalRecord) + 1);
                $grade = $this->getGradeObj($avg);
                $createdMonth = $board->getYear() . '-' .date('m', strtotime($board->getMonth())) . '-01';

                $categoryInsertQuery = "INSERT INTO kpi_agent_category(agent_id, grade_standard_id, quantity, month, year, created_at, document_upload_id, created_month, average, month_count, cumulative_quantity) 
                                        VALUES (:agent_id, :grade_standard_id, :quantity, :month, :year, CURRENT_TIMESTAMP, :document_upload_id, :created_month, :average, :month_count, :cumulative_quantity)";
                $stmt = $this->_em->getConnection()->prepare($categoryInsertQuery);
                $stmt->bindValue('agent_id', $item['agent_id']);
                $stmt->bindValue('grade_standard_id', $grade->getId());
                $stmt->bindValue('quantity', $item['totalQuantity']);
                $stmt->bindValue('month', $item['month']);
                $stmt->bindValue('year', $item['year']);
                $stmt->bindValue('document_upload_id', $item['document_upload_id']);
                $stmt->bindValue('average', $avg);
                $stmt->bindValue('created_month', $createdMonth);
                $stmt->bindValue('month_count', (count($findTotalRecord) + 1));
                $stmt->bindValue('cumulative_quantity', $cumulativeQty);
                $stmt->execute();*/
            }else{

//                $findDocument = $this->_em->getRepository(DocumentUpload::class)->find($item['document_upload_id']);

                $avg = ($findCategory->getCumulativeQuantity() + $item['totalQuantity']) / $findCategory->getMonthCount();
                $grade = $this->getGradeObj($avg);

                $findCategory->setGradeStandard($grade);
                $findCategory->setQuantity($item['totalQuantity']);
                $findCategory->setDocumentUpload($findDocument);
                $findCategory->setAverage($avg);
                $findCategory->setCumulativeQuantity(($findCategory->getCumulativeQuantity() + $item['totalQuantity']));

                $this->_em->flush();
            }
        }



        $prevYear = $board->getYear() - 1;
        $month = $board->getMonth();

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent','agent');
        $qb->join('agent.district','district');
        $qb->join('e.gradeStandard','gradeStandard');

        $qb->select('gradeStandard.grade');
        $qb->addSelect('agent.id AS agentId');

        $qb->where('e.year = :prevYear')->setParameter('prevYear', $prevYear);
        $qb->andWhere('district.id IN (:districtsId)')->setParameter('districtsId', $districtsId);
//        $qb->andWhere("e.month = 'December'");
        $qb->andWhere('e.month = :month')->setParameter('month', $month);
        $qb->andWhere('gradeStandard.grade IN (:gradeLetters)')->setParameter('gradeLetters', $gradeLetters);

        $results = $qb->getQuery()->getArrayResult();


        $agentsIdWithCategory =[];
        foreach ($results as $result){
            $agentsIdWithCategory[$result['grade']][]= $result['agentId'];
        }

        $categoryUpgradationPercentages = $this->currentMonthCategoryUpgradationPercentage($agentsIdWithCategory, $board);
        return $this->categoryUpgradationMarks($categoryUpgradationPercentages);
    }


    private function currentMonthCategoryUpgradationPercentage($agentsIdWithCategory, EmployeeBoard $board)
    {
        $month = $board->getMonth();
        $currentYear = $board->getYear();
        $categoryUpgradationPercentages = [];

        foreach ($agentsIdWithCategory as $category => $agentsId){

            $omittedGradeLetters = range($category, 'D');

            $qb = $this->createQueryBuilder('e');
            $qb->join('e.agent','agent');
            $qb->join('e.gradeStandard','gradeStandard');

            $qb->select('e.id','agent.id AS agentId','gradeStandard.grade');

            $qb->where('e.year = :currentYear')->setParameter('currentYear', $currentYear);
            $qb->andWhere('e.month = :month')->setParameter('month', $month);
            $qb->andWhere('gradeStandard.grade NOT IN (:omittedGradeLetter)')->setParameter('omittedGradeLetter', $omittedGradeLetters);
            $qb->andWhere('agent.id IN (:agentId)')->setParameter('agentId', $agentsId);
            $results = $qb->getQuery()->getArrayResult();

            $pervYearCategoryNumber = count($agentsId);
            $currentCategoryNumber = count($results);

//            $prevGrade = chr(ord($category)-1);
//            $categoryUpgradationPercentages[$category . 'to' . 'UpperGrade'] = round(($currentCategoryNumber * 100) / ($pervYearCategoryNumber / 2));
            $categoryUpgradationPercentages[$category . 'to' . 'UpperGrade'] = round(($currentCategoryNumber * 100) / $pervYearCategoryNumber);
        }
        return $categoryUpgradationPercentages;
    }

    private function categoryUpgradationMarks($categoryUpgradationPercentages)
    {
        $marks = [];
        foreach ($categoryUpgradationPercentages as $grade => $percentage){
            if($percentage >= 20){
                $marks[$grade] = 5;
            }elseif ($percentage < 20 && $percentage >= 15){
                $marks[$grade] = 4;
            }elseif ($percentage < 15 && $percentage >= 10){
                $marks[$grade] = 3;
            }elseif ($percentage < 10 && $percentage >= 5){
                $marks[$grade] = 2;
            }elseif ($percentage < 5 && $percentage >= 1){
                $marks[$grade] = 1;
            }else{
                $marks[$grade] = 0;
            }
        }
        return $marks;
    }



    public function getAgentWithDcategory(EmployeeBoard $board, $districtsId)
    {
        $prevYear = $board->getYear()-1;

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent','agent');
        $qb->join('agent.district','district');
        $qb->leftJoin('district.parent', 'region');
        $qb->leftJoin('region.parent', 'zone');
        $qb->join('e.gradeStandard','gradeStandard');

        $qb->select('gradeStandard.grade');
        $qb->addSelect('agent.agentId AS agentId', 'agent.name AS agentName');
        $qb->addSelect('e.average');
        $qb->addSelect('region.name AS agentRegionName');
        $qb->addSelect('zone.name AS agentZoneName');

        $qb->where('e.year = :prevYear')->setParameter('prevYear', $prevYear);
        $qb->andWhere('district.id IN (:districtsId)')->setParameter('districtsId', $districtsId);
        $qb->andWhere('e.month = :month')->setParameter('month', $board->getMonth());
        $qb->andWhere("gradeStandard.grade = 'D'");

        $agents = $qb->getQuery()->getArrayResult();

        $prevYearAgentsWithDcategory = [];
        $agentsId = [];

        foreach ($agents as $agent){
            $prevYearAgentsWithDcategory[(int)$agent['agentId']]= $agent;
            $agentsId[] = $agent['agentId'];

        }

        $currentGrade = [];
        $currentYearAgentsDtoUpgradeCategory = $this->getAgentCurrentMonth($board, $agentsId);

        foreach ($prevYearAgentsWithDcategory as $key => $item) {
            if (array_key_exists($key, $currentYearAgentsDtoUpgradeCategory)){
                $currentYearAgentsDtoUpgradeCategory[$key]['prevYearGrade'] = $prevYearAgentsWithDcategory[$key]['grade'];
                $currentYearAgentsDtoUpgradeCategory[$key]['prevYearAvg'] = $prevYearAgentsWithDcategory[$key]['average'];
                array_push($currentGrade, $currentYearAgentsDtoUpgradeCategory[$key]['currentMonthGrade']);
            }

/*            else{
                $currentYearAgentsDtoUpgradeCategory[$key] = [
                    'currentMonthAvg' => 0,
                    'month' => $board->getMonth(),
                    'year' => $board->getYear(),
                    'agentId' => $item['agentId'],
                    'agentName' => $item['agentName'],
                    'agentRegionName' => $item['agentRegionName'],
                    'agentZoneName' => $item['agentZoneName'],
                    'currentMonthGrade' => '',
                    'prevYearGrade' => $prevYearAgentsWithDcategory[$key]['grade'],
                    'prevYearAvg' => $prevYearAgentsWithDcategory[$key]['average'],
                ];
            }*/


        }

        /*
                foreach ($currentYearAgentsDtoUpgradeCategory as $key => $item) {
                    if(array_key_exists($key, $prevYearAgentsWithDcategory)){
                        $currentYearAgentsDtoUpgradeCategory[$key]['prevYearGrade'] = $prevYearAgentsWithDcategory[$key]['grade'];
                        $currentYearAgentsDtoUpgradeCategory[$key]['prevYearAvg'] = $prevYearAgentsWithDcategory[$key]['average'];
                        array_push($currentGrade, $item['currentMonthGrade']);

                    }
                }*/
        $currentYearAgentsDtoUpgradeCategory['totalAgent'] = count($currentYearAgentsDtoUpgradeCategory);
        $currentYearAgentsDtoUpgradeCategory['totalUpgradeAgent'] = count(array_intersect($currentGrade, ['A','B','C']));
        return $currentYearAgentsDtoUpgradeCategory;
    }

    private function getAgentCurrentMonth(EmployeeBoard $board, $agentsId)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('e.gradeStandard', 'gradeStandard');
        $qb->join('agent.district', 'district');
        $qb->leftJoin('district.parent', 'region');
        $qb->leftJoin('region.parent', 'zone');

        $qb->select('e.average AS currentMonthAvg','e.month','e.year');
        $qb->addSelect('agent.agentId AS agentId', 'agent.name AS agentName');
        $qb->addSelect('region.name AS agentRegionName');
        $qb->addSelect('zone.name AS agentZoneName');
        $qb->addSelect('gradeStandard.grade AS currentMonthGrade');

        $qb->where('e.year = :currentYear')->setParameter('currentYear', $board->getYear());
        $qb->andWhere('e.month = :month')->setParameter('month', $board->getMonth());
        $qb->andWhere('agent.agentId IN (:agentId)')->setParameter('agentId', $agentsId);
        $results = $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $result){
            $data[(int)$result['agentId']]= $result;
            $data[(int)$result['agentId']]['prevYearGrade'] = '';
            $data[(int)$result['agentId']]['prevYearAvg'] = 0;
        }


        return $data;
    }


    public function getAgentWithCcategory(EmployeeBoard $board, $districtsId)
    {
        $prevYear = $board->getYear()-1;

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent','agent');
        $qb->join('agent.district','district');
        $qb->leftJoin('district.parent', 'region');
        $qb->leftJoin('region.parent', 'zone');
        $qb->join('e.gradeStandard','gradeStandard');

        $qb->select('gradeStandard.grade');
        $qb->addSelect('agent.agentId AS agentId', 'agent.name AS agentName');
        $qb->addSelect('e.average');
        $qb->addSelect('region.name AS agentRegionName');
        $qb->addSelect('zone.name AS agentZoneName');

        $qb->where('e.year = :prevYear')->setParameter('prevYear', $prevYear);
        $qb->andWhere('district.id IN (:districtsId)')->setParameter('districtsId', $districtsId);
        $qb->andWhere('e.month = :month')->setParameter('month', $board->getMonth());
        $qb->andWhere("gradeStandard.grade = 'C'");

        $agents = $qb->getQuery()->getArrayResult();

        $prevYearAgentsWithCcategory = [];
        $agentsId =[];

        foreach ($agents as $agent){
            $prevYearAgentsWithCcategory[(int)$agent['agentId']]= $agent;
            $agentsId[]= $agent['agentId'];

        }

        $currentGrade = [];
        $currentYearAgentsCtoUpgrade = $this->getAgentCurrentMonth($board, $agentsId);

        foreach ($prevYearAgentsWithCcategory as $key => $item) {
            if (array_key_exists($key, $currentYearAgentsCtoUpgrade)){
                $currentYearAgentsCtoUpgrade[$key]['prevYearGrade'] = $prevYearAgentsWithCcategory[$key]['grade'];
                $currentYearAgentsCtoUpgrade[$key]['prevYearAvg'] = $prevYearAgentsWithCcategory[$key]['average'];
                array_push($currentGrade, $currentYearAgentsCtoUpgrade[$key]['currentMonthGrade']);
            }
/*            
            else{
                $currentYearAgentsCtoUpgrade[$key] = [
                    'currentMonthAvg' => 0,
                    'month' => $board->getMonth(),
                    'year' => $board->getYear(),
                    'agentId' => $item['agentId'],
                    'agentName' => $item['agentName'],
                    'agentRegionName' => $item['agentRegionName'],
                    'agentZoneName' => $item['agentZoneName'],
                    'currentMonthGrade' => '',
                    'prevYearGrade' => $prevYearAgentsWithCcategory[$key]['grade'],
                    'prevYearAvg' => $prevYearAgentsWithCcategory[$key]['average'],
                ];
            }*/

        }

        /*        foreach ($currentYearAgentsCtoUpgrade as $key => $item) {
                    if(array_key_exists($key, $prevYearAgentsWithCcategory)){
                        $currentYearAgentsCtoUpgrade[$key]['prevYearGrade'] = $prevYearAgentsWithCcategory[$key]['grade'];
                        $currentYearAgentsCtoUpgrade[$key]['prevYearAvg'] = $prevYearAgentsWithCcategory[$key]['average'];
                        array_push($currentGrade, $item['currentMonthGrade']);
                    }
                }*/

        $currentYearAgentsCtoUpgrade['totalAgent'] = count($currentYearAgentsCtoUpgrade);
        $currentYearAgentsCtoUpgrade['totalUpgradeAgent'] = count(array_intersect($currentGrade, ['A','B']));
        return $currentYearAgentsCtoUpgrade;
    }

    public function getAgentUpgradation(EmployeeBoard $board, $districtsId)
    {
        $years = [$board->getYear()-1, (int)$board->getYear()];

        $qb = $this->createQueryBuilder('e');

        $qb->join('e.agent', 'agent');
        $qb->leftJoin('e.gradeStandard', 'grade_standard');
        $qb->join('agent.district', 'district');

        $qb->select('e.average', 'e.month', 'e.year');
        $qb->addSelect('agent.agentId', 'agent.name AS agentName', 'agent.address AS agentAddress');
        $qb->addSelect('grade_standard.grade');

        $qb->where('district.id IN (:districtsId)')->setParameter('districtsId', $districtsId);
        $qb->andWhere('e.year IN (:years)')->setParameter('years', $years);
        $qb->andWhere('e.month = :month')->setParameter('month', $board->getMonth());

        $results = $qb->getQuery()->getArrayResult();

        $data = [];
        foreach ($results as $result) {
            $data[$result['year']][$result['agentId']] = $result;
        }

        if (!array_key_exists($board->getYear(), $data)){
            $data[$board->getYear()] = [];
        }
        if (!array_key_exists($board->getYear()-1, $data)){
            $data[$board->getYear()-1] = [];
        }

        $agentUpgrade = [];


        foreach ($data[$board->getYear()] as $agentId => $currentYearAgent) {
            if (array_key_exists($agentId, $data[$board->getYear()-1])){ // find agent in previous year
                if ($data[$board->getYear()-1][$agentId]['grade'] != $data[$board->getYear()][$agentId]['grade']){ // Remove equal grade agent
                    $prevYearGradePosition = ord(strtoupper($data[$board->getYear()-1][$agentId]['grade'])) - ord('A') + 1; //find grade position
                    $currentYearGradePosition = ord(strtoupper($data[$board->getYear()][$agentId]['grade'])) - ord('A') + 1; //find grade position

                    if ($currentYearGradePosition < $prevYearGradePosition){ // check grade position upgradation

                        $agentUpgrade['upgradeAgents'][$agentId] = [
                            'agentId' => $agentId,
                            'agentName' => $currentYearAgent['agentName'],
                            'agentAddress' => $currentYearAgent['agentAddress'],
                            'average-' . ($board->getYear()-1) => $data[$board->getYear()-1][$agentId]['average'],
                            'average-' . $board->getYear() => $data[$board->getYear()][$agentId]['average'],
                            'grade-' . ($board->getYear()-1) => $data[$board->getYear()-1][$agentId]['grade'],
                            'grade-' . $board->getYear() => $data[$board->getYear()][$agentId]['grade']
                        ];

                    }
                }
            }
        }

        if (!array_key_exists('upgradeAgents', $agentUpgrade)){
            $agentUpgrade['upgradeAgents'] = [];
        }
//        $agentUpgrade['totalAgentsCount'] = count($data[$board->getYear()-1] + $data[$board->getYear()]);
        $agentUpgrade['totalAgentsCount'] = count($data[$board->getYear()]);
        $agentUpgrade['upgradeAgentsCount'] = isset($agentUpgrade['upgradeAgents']) ? count($agentUpgrade['upgradeAgents']) : 0;

        return $agentUpgrade;
    }
}
