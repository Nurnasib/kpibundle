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

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('agent.district', 'district');

        $qb->select('e AS prevYearDetails');
        $qb->addSelect('district.id AS districtId');
        $qb->where('district.id IN (:districtId)')->setParameter('districtId', $districtsId);
        $qb->andWhere('e.month = :month')->setParameter('month', $board->getMonth());
        $qb->andWhere('e.year = :year')->setParameter('year', $prevYear);

        $results = $qb->getQuery()->getResult();

        $data = [];
        foreach ($results as $result) {
            if (!in_array($result['districtId'], $data)){
                array_push($data, $result['districtId']);
            }
        }

        $districtIdNotFoundPrevYear = array_merge(array_diff($districtsId, $data), array_diff($data,$districtsId));

//        $prevYearRecords = $this->getPreviousCategory($board->getMonth(), $prevYear, $districtsId);

//        dd($prevYearRecords);

        if ($districtIdNotFoundPrevYear){
            if ($board->getMonth() == 'January'){
                $districtsSales = $this->_em->getRepository(AgentOrder::class)->getAgentWithSalesQuantity(['January'], $prevYear, $districtIdNotFoundPrevYear);
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
                    $districtsSales = $this->_em->getRepository(AgentOrder::class)->getAgentWithSalesQuantity(['January'], $board->getYear(), $districtIdNotFoundPrevYear);

                    foreach ($districtsSales as $districtsSale) {

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
                }
            }else{

                $prevMonth = date('F', strtotime($board->getMonth() . ',' . $board->getYear() . "last month"));
                $monthCount = date('m', strtotime($board->getMonth() . ',' . $board->getYear()));
                $createdMonth = $prevYear . '-' . $monthCount . '-01';
                
                
                $prevMonthRecords = $this->getPreviousCategory($prevMonth, $prevYear, $districtsId);

                $agents = array();
                foreach ($prevMonthRecords as $record) {

                    $exist = $this->findOneBy(['agent' => $record->getAgent(), 'month' => $board->getMonth(), 'year' => $prevYear]);
                    array_push($agents, $record->getAgent()->getId()) ;

                    if(!$exist){

                        $currentMonthRecord = new AgentCategory();
                        $currentMonth = $this->getTotalSales($record->getAgent()->getId(), $board->getMonth(), $prevYear);

                        if (!$currentMonth){
                            $currentMonth['totalQuantity'] = 0;
                            $currentMonth['document_upload_id'] = null;
                        }

                        $totalQuantity = ($record->getCumulativeQuantity() + $currentMonth['totalQuantity']);
                        $avg = ($totalQuantity / ($record->getMonthCount() + 1));
                        $grade = $this->getGradeObj($avg);
                        if ($currentMonth['document_upload_id']){
                            $findDocument = $this->_em->getRepository(DocumentUpload::class)->find($currentMonth['document_upload_id']);
                        }else{
                            $findDocument = null;
                        }

                        $currentMonthRecord->setAgent($record->getAgent());
                        $currentMonthRecord->setGradeStandard($grade);
                        $currentMonthRecord->setQuantity($currentMonth['totalQuantity']);
                        $currentMonthRecord->setMonth($board->getMonth());
                        $currentMonthRecord->setYear($prevYear);
                        $currentMonthRecord->setCreatedAt(new \DateTimeImmutable("now"));
                        $currentMonthRecord->setCreatedMonth(new \DateTimeImmutable($createdMonth));
                        $currentMonthRecord->setMonthCount(($record->getMonthCount() + 1));
                        $currentMonthRecord->setAverage($avg);
                        $currentMonthRecord->setCumulativeQuantity($totalQuantity);
                        $currentMonthRecord->setDocumentUpload($findDocument);

                        $this->_em->persist($currentMonthRecord);
                        $this->_em->flush();
                    }
                }

                if ($agents){

                    // insert category for new agents sale current month
                    $districtsIdString = implode(',' , $districtsId);
                    $agents = implode(',' , $agents);
                    $agentOrderQuery = "SELECT agent_id, SUM(quantity) AS totalQuantity, month, year, document_upload_id
                    FROM kpi_agent_order
                    WHERE month = :month AND year = :year AND agent_id NOT IN ($agents) AND district_id IN ($districtsIdString)
                    GROUP BY agent_id";
                    $stmt = $this->_em->getConnection()->prepare($agentOrderQuery);
                    $stmt->bindValue('month', $board->getMonth());
                    $stmt->bindValue('year', $prevYear);
                    $stmt->execute();
                    $newAgentsSales = $stmt->fetchAll();

                    foreach ($newAgentsSales as $sale) {

                        $exist = $this->findOneBy(['agent' => $sale['agent_id'], 'month' => $sale['month'], 'year' => $sale['year']]);
                        if(!$exist){
                            $currentMonthRecord = new AgentCategory();
                            $grade = $this->getGradeObj(isset($sale['totalQuantity']) ? $sale['totalQuantity'] : 0);
                            if (isset($sale['document_upload_id'])){
                                $findDocument = $this->_em->getRepository(DocumentUpload::class)->find($sale['document_upload_id']);
                            }else{
                                $findDocument = null;
                            }

                            $agentObj = $this->_em->getRepository(Agent::class)->find($sale['agent_id']);

                            $currentMonthRecord->setAgent($agentObj);
                            $currentMonthRecord->setGradeStandard($grade);
                            $currentMonthRecord->setQuantity(isset($sale['totalQuantity']) ? $sale['totalQuantity'] : 0);
                            $currentMonthRecord->setMonth($board->getMonth());
                            $currentMonthRecord->setYear($prevYear);
                            $currentMonthRecord->setCreatedAt(new \DateTimeImmutable("now"));
                            $currentMonthRecord->setCreatedMonth(new \DateTimeImmutable($createdMonth));
                            $currentMonthRecord->setMonthCount(1);
                            $currentMonthRecord->setAverage(isset($sale['totalQuantity']) ? $sale['totalQuantity'] : 0);
                            $currentMonthRecord->setCumulativeQuantity(isset($sale['totalQuantity']) ? $sale['totalQuantity'] : 0);
                            $currentMonthRecord->setDocumentUpload($findDocument);

                            $this->_em->persist($currentMonthRecord);
                            $this->_em->flush();
                        }
                    }
                }

            }
        }
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

    public function getTotalSales($agent, $month, $year)
    {
        $agentOrderQuery = "SELECT SUM(quantity) AS totalQuantity, document_upload_id
                    FROM kpi_agent_order
                    WHERE month = :month AND year = :year AND agent_id = {$agent}
                    GROUP BY agent_id
                    ";
        $stmt = $this->_em->getConnection()->prepare($agentOrderQuery);
        $stmt->bindValue('month', $month);
        $stmt->bindValue('year', $year);
        $stmt->execute();
        $data = $stmt->fetch();
        return $data;

    }

    private function getPreviousCategory($month, $year, $districtsId)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.agent', 'agent');
        $qb->join('agent.district', 'district');

        $qb->select('e');
        $qb->where('district.id IN (:districtId)')->setParameter('districtId', $districtsId);
        $qb->andWhere('e.month = :month')->setParameter('month', $month);
        $qb->andWhere('e.year = :year')->setParameter('year', $year);

        return $qb->getQuery()->getResult();
    }

    public function getCategoryUpgradationMarks(EmployeeBoard $board, $gradeLetters, $districtsId)
    {
        $this->insertPreviousYearCategory($board, $districtsId);

        if ($board->getMonth() != 'January'){ //if not january, clone previous month all categories
            $prevMonth = date('F', strtotime($board->getMonth() . ',' . $board->getYear() . "last month"));
            $monthCount = date('m', strtotime($board->getMonth() . ',' . $board->getYear()));
            $createdMonth = $board->getYear() . '-' . $monthCount . '-01';

            $prevMonthRecords = $this->getPreviousCategory($prevMonth, $board->getYear(), $districtsId);

            $months = [];

            for ($i = 1; $i <= date('m', strtotime($board->getMonth())); $i++){
                $months[] = date('F', strtotime('2022-' . $i . '-01'));
            }
            $agents = array();
            foreach ($prevMonthRecords as $record) {

                $exist = $this->findOneBy(['agent' => $record->getAgent(), 'month' => $board->getMonth(), 'year' => $board->getYear()]);
                array_push($agents,$record->getAgent()->getId()) ;

                if(!$exist){

                    $currentMonthRecord = new AgentCategory();
                    $currentMonth = $this->getTotalSales($record->getAgent()->getId(), $board->getMonth(), $board->getYear());

                    if (!$currentMonth){
                        $currentMonth['totalQuantity'] = 0;
                        $currentMonth['document_upload_id'] = null;
                    }

                    $totalQuantity = ($record->getCumulativeQuantity() + $currentMonth['totalQuantity']);
                    $avg = ($totalQuantity / ($record->getMonthCount() + 1));
                    $grade = $this->getGradeObj($avg);
                    if ($currentMonth['document_upload_id']){
                        $findDocument = $this->_em->getRepository(DocumentUpload::class)->find($currentMonth['document_upload_id']);
                    }else{
                        $findDocument = null;
                    }


                    $currentMonthRecord->setAgent($record->getAgent());
                    $currentMonthRecord->setGradeStandard($grade);
                    $currentMonthRecord->setQuantity($currentMonth['totalQuantity']);
                    $currentMonthRecord->setMonth($board->getMonth());
                    $currentMonthRecord->setYear($board->getYear());
                    $currentMonthRecord->setCreatedAt(new \DateTimeImmutable("now"));
                    $currentMonthRecord->setCreatedMonth(new \DateTimeImmutable($createdMonth));
                    $currentMonthRecord->setMonthCount(($record->getMonthCount() + 1));
                    $currentMonthRecord->setAverage($avg);
                    $currentMonthRecord->setCumulativeQuantity($totalQuantity);
                    $currentMonthRecord->setDocumentUpload($findDocument);

                    $this->_em->persist($currentMonthRecord);
                    $this->_em->flush();
                }
            }

            if ($agents){
                // insert category for new agents sale current month
                $districtsIdString = implode(',' , $districtsId);
                $agents = implode(',' , $agents);
                $agentOrderQuery = "SELECT agent_id, SUM(quantity) AS totalQuantity, month, year, document_upload_id
                    FROM kpi_agent_order
                    WHERE month = :month AND year = :year AND agent_id NOT IN ($agents) AND district_id IN ($districtsIdString)
                    GROUP BY agent_id";
                $stmt = $this->_em->getConnection()->prepare($agentOrderQuery);
                $stmt->bindValue('month', $board->getMonth());
                $stmt->bindValue('year', $board->getYear());
                $stmt->execute();
                $newAgentsSales = $stmt->fetchAll();

                foreach ($newAgentsSales as $sale) {

                    $exist = $this->findOneBy(['agent' => $sale['agent_id'], 'month' => $sale['month'], 'year' => $sale['year']]);
                    if(!$exist){
                        $currentMonthRecord = new AgentCategory();
                        $grade = $this->getGradeObj(isset($sale['totalQuantity']) ? $sale['totalQuantity'] : 0);
                        if (isset($sale['document_upload_id'])){
                            $findDocument = $this->_em->getRepository(DocumentUpload::class)->find($sale['document_upload_id']);
                        }else{
                            $findDocument = null;
                        }

                        $agentObj = $this->_em->getRepository(Agent::class)->find($sale['agent_id']);

                        $currentMonthRecord->setAgent($agentObj);
                        $currentMonthRecord->setGradeStandard($grade);
                        $currentMonthRecord->setQuantity(isset($sale['totalQuantity']) ? $sale['totalQuantity'] : 0);
                        $currentMonthRecord->setMonth($board->getMonth());
                        $currentMonthRecord->setYear($board->getYear());
                        $currentMonthRecord->setCreatedAt(new \DateTimeImmutable("now"));
                        $currentMonthRecord->setCreatedMonth(new \DateTimeImmutable($createdMonth));
                        $currentMonthRecord->setMonthCount(1);
                        $currentMonthRecord->setAverage(isset($sale['totalQuantity']) ? $sale['totalQuantity'] : 0);
                        $currentMonthRecord->setCumulativeQuantity(isset($sale['totalQuantity']) ? $sale['totalQuantity'] : 0);
                        $currentMonthRecord->setDocumentUpload($findDocument);

                        $this->_em->persist($currentMonthRecord);
                        $this->_em->flush();
                    }
                }
            }
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

        foreach ($data as $item) {

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
            }else{

                $months = [];

                for ($i = 1; $i <= date('m', strtotime($board->getMonth())); $i++){
                    $months[] = date('F', strtotime('2022-' . $i . '-01'));
                }

                $sum = $this->getQuantitySum($item['agent_id'], $months, $board->getYear());

                $avg = ($sum / $findCategory->getMonthCount());
                $grade = $this->getGradeObj($avg);

                $findCategory->setGradeStandard($grade);
                $findCategory->setQuantity($item['totalQuantity']);
                $findCategory->setDocumentUpload($findDocument);
                $findCategory->setAverage($avg);
                $findCategory->setCumulativeQuantity($sum);
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
        $qb->addSelect('district.name AS agentDistrictName');

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

            else{
                $currentYearAgentsDtoUpgradeCategory[$key] = [
                    'currentMonthAvg' => 0,
                    'month' => $board->getMonth(),
                    'year' => $board->getYear(),
                    'agentId' => $item['agentId'],
                    'agentDistrictName' => $item['agentDistrictName'],
                    'agentName' => $item['agentName'],
                    'agentRegionName' => $item['agentRegionName'],
                    'agentZoneName' => $item['agentZoneName'],
                    'currentMonthGrade' => '-',
                    'prevYearGrade' => $prevYearAgentsWithDcategory[$key]['grade'],
                    'prevYearAvg' => $prevYearAgentsWithDcategory[$key]['average'],
                ];
            }


        }

        /*
                foreach ($currentYearAgentsDtoUpgradeCategory as $key => $item) {
                    if(array_key_exists($key, $prevYearAgentsWithDcategory)){
                        $currentYearAgentsDtoUpgradeCategory[$key]['prevYearGrade'] = $prevYearAgentsWithDcategory[$key]['grade'];
                        $currentYearAgentsDtoUpgradeCategory[$key]['prevYearAvg'] = $prevYearAgentsWithDcategory[$key]['average'];
                        array_push($currentGrade, $item['currentMonthGrade']);

                    }
                }*/
//        $currentYearAgentsDtoUpgradeCategory['totalAgent'] = count($currentYearAgentsDtoUpgradeCategory);
        $currentYearAgentsDtoUpgradeCategory['totalAgent'] = count($prevYearAgentsWithDcategory);
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
        $qb->addSelect('district.name AS agentDistrictName');

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
        $qb->addSelect('district.name AS agentDistrictName');

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
            else{
                $currentYearAgentsCtoUpgrade[$key] = [
                    'currentMonthAvg' => 0,
                    'month' => $board->getMonth(),
                    'year' => $board->getYear(),
                    'agentId' => $item['agentId'],
                    'agentName' => $item['agentName'],
                    'agentDistrictName' => $item['agentDistrictName'],
                    'agentRegionName' => $item['agentRegionName'],
                    'agentZoneName' => $item['agentZoneName'],
                    'currentMonthGrade' => '',
                    'prevYearGrade' => $prevYearAgentsWithCcategory[$key]['grade'],
                    'prevYearAvg' => $prevYearAgentsWithCcategory[$key]['average'],
                ];
            }

        }

        /*        foreach ($currentYearAgentsCtoUpgrade as $key => $item) {
                    if(array_key_exists($key, $prevYearAgentsWithCcategory)){
                        $currentYearAgentsCtoUpgrade[$key]['prevYearGrade'] = $prevYearAgentsWithCcategory[$key]['grade'];
                        $currentYearAgentsCtoUpgrade[$key]['prevYearAvg'] = $prevYearAgentsWithCcategory[$key]['average'];
                        array_push($currentGrade, $item['currentMonthGrade']);
                    }
                }*/

        $currentYearAgentsCtoUpgrade['totalAgent'] = count($prevYearAgentsWithCcategory);
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
        $agentUpgrade['totalAgentsCountPrevYear'] = count($data[$board->getYear()-1]);
        $agentUpgrade['totalAgentsCountCurrentYear'] = count($data[$board->getYear()]);
        $agentUpgrade['upgradeAgentsCount'] = isset($agentUpgrade['upgradeAgents']) ? count($agentUpgrade['upgradeAgents']) : 0;

        return $agentUpgrade;
    }
}
