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
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use function Doctrine\ORM\QueryBuilder;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class EmployeeBoardRepository extends EntityRepository
{

    public function getEmployeeBoardList(User $user)
    {
        $qb = $this->createQueryBuilder('s');
        $qb->join('s.employee','u');
//        $qb->leftJoin('u.lineManager','lm');
        $qb->leftJoin('s.lineManager','lm');
        $qb->leftJoin('u.designation','d');
        $qb->leftJoin('s.createdBy', 'createdBy');
        $qb->leftJoin('s.approvedBy', 'approvedBy');
//        $qb->leftJoin('u.reportMode', 'reportMode');
        $qb->leftJoin('s.reportMode', 'reportMode');
        $qb->select('s.id as id','s.month as month','s.year as year','s.status','s.created', 's.district', 's.process', 's.isReverse', 's.grade');
        $qb->addSelect('u.name as name','u.userId','d.name as designation');
        $qb->addSelect('lm.name as lineManager', 'lm.userId as lineManagerUserId');
        $qb->addSelect('createdBy.name AS createdByName');
        $qb->addSelect('approvedBy.name AS approvedByName');
        $qb->addSelect('reportMode.name AS reportFormat');
        $qb->addSelect('reportMode.slug AS reportFormatSlug');

        if (in_array('ROLE_LINE_MANAGER', $user->getRoles())){
/*            foreach ($user->getDistrict() as $district) {
                $qb->andWhere($qb->expr()->like("s.district", ':district'))
                    ->setParameters([
                        'district' => '%'. $district->name .'%'
                    ]);
            }*/
            $qb->andWhere('lm = :user')->setParameter('user', $user);
        }elseif (!in_array('ROLE_LINE_MANAGER', $user->getRoles()) && !in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            $qb->andWhere('s.employee = :user')->setParameter('user', $user);
        }
        $qb->andWhere('s.isInput = true');
        $qb->orderBy('s.created','DESC');
        $results = $qb->getQuery()->getArrayResult();

        $data = [];
        foreach ($results as $key=>$result) {
            $arr = json_decode($result['district'], true);
            $data[$key] = $result;
            $data[$key]['district'] = $arr ? implode(', ', $arr) : '';
        }
        return $data;

    }

    public function getEmployeeBoardListFilterBy(User $user, $filterBy)
    {

        $qb = $this->createQueryBuilder('s');
        $qb->join('s.employee','u');
        $qb->leftJoin('s.reportMode','boardReportMode');
//        $qb->leftJoin('u.lineManager','lm');
        $qb->leftJoin('s.lineManager','lm');
        $qb->leftJoin('u.designation','d');
        $qb->leftJoin('s.createdBy', 'createdBy');
        $qb->leftJoin('s.approvedBy', 'approvedBy');
//        $qb->leftJoin('u.reportMode', 'reportMode');
        $qb->leftJoin('s.reportMode', 'reportMode');
        $qb->select('s.id as id','s.month as month','s.year as year','s.status','s.created','s.district','s.process','s.isReverse','s.grade');
        $qb->addSelect('u.name as name','u.userId','d.name as designation');
        $qb->addSelect('lm.name as lineManager', 'lm.name as lineManagerUserId');
        $qb->addSelect('createdBy.name AS createdByName');
        $qb->addSelect('approvedBy.name AS approvedByName');
        $qb->addSelect('reportMode.name AS reportFormat','reportMode.slug AS reportFormatSlug');

        if ($filterBy['employee']){
            $qb->andWhere('u.id = :employeeId')->setParameter('employeeId', $filterBy['employee']->getId());
        }
        if ($filterBy['designation']){
            $qb->andWhere('d.id = :designationId')->setParameter('designationId', $filterBy['designation']->getId());
        }
        if ($filterBy['kpiFormat']){
            $qb->andWhere('boardReportMode.id = :reportModeId')->setParameter('reportModeId', $filterBy['kpiFormat']->getId());
        }
        if (isset($filterBy['lineManager'])){
            $qb->andWhere('lm.id = :lineManagerId')->setParameter('lineManagerId', $filterBy['lineManager']);
        }
        if ($filterBy['month']){
            $qb->andWhere('s.month = :month')->setParameter('month', $filterBy['month']);
        }
        if ($filterBy['year']){
            $qb->andWhere('s.year = :year')->setParameter('year', $filterBy['year']);
        }
        if ($filterBy['process']){
            $qb->andWhere('s.process = :process')->setParameter('process', $filterBy['process']);
        }
        if ($filterBy['grade']){
            $qb->andWhere('s.grade = :grade')->setParameter('grade', $filterBy['grade']);
        }
        if (array_key_exists('createdBy', $filterBy) && isset($filterBy['createdBy'])){
            $qb->andWhere('createdBy.id = :createdById')->setParameter('createdById', $filterBy['createdBy']);
        }

        if (in_array('ROLE_LINE_MANAGER', $user->getRoles())){
            $qb->andWhere('lm = :user')->setParameter('user', $user);
        }elseif (!in_array('ROLE_LINE_MANAGER', $user->getRoles()) && !in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            $qb->andWhere('s.employee = :user')->setParameter('user', $user);
        }

        $qb->andWhere('s.isInput = true');
        $qb->orderBy('s.created','DESC');
        $results =  $qb->getQuery()->getArrayResult();
        $data = [];
        foreach ($results as $key=>$result) {
            $arr = json_decode($result['district'], true);
            $data[$key] = $result;
            $data[$key]['district'] = $arr ? implode(', ', $arr) : '';
        }
        return $data;

    }

    public function getSalesAmount()
    {
        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.setupMatrix','p');
        $qb->leftJoin('p.sales','s');
        $qb->select('e.id as id','SUM(s.amount) as amount');
        $qb->groupBy('e.id');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['id']] = $row;
        }
        return $data;
    }

    public function processSetup($entity)
    {
        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.setupMatrix','p');
        $qb->leftJoin('p.sales','s');
        $qb->join('p.upozila','u');
        $qb->select('u.id as upozila','SUM(s.amount) as amount');
        $qb->where('e.id = :entity')->setParameter('entity',$entity);
        $qb->groupBy('u.id');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['upozila']] = $row;
        }
        return $data;
    }

    public function getGeneratedTeamMember($totalTeamMembersId, $month, $year)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employee', 'employee');
        $qb->where('e.year = :year')->setParameter('year', $year)
            ->andWhere('e.month = :month')->setParameter('month', $month)
            ->andWhere($qb->expr()->isNotNull('e.approvedBy'))
            ->andWhere('employee.id IN (:employee)')->setParameter('employee', $totalTeamMembersId)
            ;

        return $qb->getQuery()->getResult();
    }

    public function getMonthlyStatus($year, $selectedLineManager, User $user)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employee', 'employee');
        $qb->join('employee.lineManager', 'lineManager');
        $qb->select('e.month');
        $qb->addSelect('employee.userId', 'employee.name', 'employee.isPermanent');
        $qb->addSelect('lineManager.userId AS lineManagerUserId', 'lineManager.name AS lineManagerName');
        $qb->where('e.year = :year')->setParameter('year', $year);
        $qb->andWhere('e.process = :process')->setParameter('process', 'approved');
        if (!in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            $qb->andWhere('lineManager.id = :lineManagerId')->setParameter('lineManagerId', $user->getId());
        }
        if ($selectedLineManager){
            $qb->andWhere('lineManager.userId = :lmUserId')->setParameter('lmUserId', $selectedLineManager);
        }
        $results = $qb->getQuery()->getArrayResult();
        $data = [];

        if (in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            foreach ($results as $result) {
                $data[$result['lineManagerUserId']]['lineManager'] = [
                    'userId' => $result['lineManagerUserId'],
                    'name' => $result['lineManagerName'],
                ];
                $data[$result['lineManagerUserId']]['teamMember'][$result['userId']]['name'] = $result['name'];
                $data[$result['lineManagerUserId']]['teamMember'][$result['userId']]['status'][$result['month']] = true;
            }
        }else{
            //get team members
            $teamMembers = $this->_em->getRepository(User::class)->getKpiEmployees($user);

            foreach ($results as $result) {
                $data[$result['userId']]['name'] = $result['name'];
                $data[$result['userId']]['userId'] = $result['userId'];
                $data[$result['userId']]['status'][$result['month']] = true;
                $data[$result['userId']]['permanentStatus'] = $result['isPermanent'];

            }

            //insert team members
            foreach ($teamMembers as $teamMember) {
                if (! isset($data[$teamMember['userId']])){
                    $data[$teamMember['userId']]['name'] = $teamMember['employeeName'];
                    $data[$teamMember['userId']]['userId'] = $teamMember['userId'];
                    $data[$teamMember['userId']]['status'] = [];
                    $data[$teamMember['userId']]['permanentStatus'] = $teamMember['isPermanent'];
                }
            }
        }

        return $data;

    }

    public function getTeamMembersListByYear(User $user, $year)
    {
        if (!$year){
            $year = date('Y');
        }
        $qb = $this->createQueryBuilder('board');
        $qb->join('board.employee', 'employee');
        $qb->leftJoin('board.lineManager', 'lineManager');
        $qb->leftJoin('employee.designation', 'designation');
        $qb->select('board.id AS boardId', 'AVG(board.selfMark) AS selfMarkAvg', 'AVG(board.obtainMark) AS lineManagerMarkAvg');
        $qb->addSelect('employee.id AS employeeId', 'employee.userId', 'employee.name AS employeeName', 'employee.joiningDate');
        $qb->addSelect('lineManager.name AS lineManagerName');
        $qb->addSelect('designation.name AS designationName');
        $qb->where('board.approvedBy IS NOT NULL');
        if (!in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            $qb->andWhere('lineManager = :lineManager')->setParameter('lineManager', $user);
        }
        $qb->andWhere('board.year = :year')->setParameter('year', $year);
        $qb->groupBy('employee.id');
        $qb->orderBy('employee.name', 'ASC');
        return $qb->getQuery()->getResult();
    }

    public function getTeamMemberGradeDetailsByYear($employee ,$year)
    {
        $qb = $this->createQueryBuilder('board');
        $qb->join('board.employee', 'employee');
        $qb->select('board.month');
        $qb->addSelect('board.selfGrade', 'board.grade');
        $qb->where('board.year = :year')->setParameter('year', $year);
        $qb->andWhere('employee.id = :employee')->setParameter('employee', $employee->getId());

        $results = $qb->getQuery()->getResult();

        $data = [];
        foreach ($results as $result) {
            $monthNumber = (new \DateTime($result['month']))->format('m');
            $data[$monthNumber] = [
                'month' => $result['month'],
                'selfGrade' => $result['selfGrade'],
                'lineManagerGrade' => $result['grade'],
            ];
        }
        ksort($data);
        return $data;
    }
    
    public function getFormatWiseGradeNumberKpi($selectedFormat, $months, $years)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->join('e.reportMode', 'reportMode');

        $qb->select('e.id', 'e.month', 'e.year', 'e.grade');
        $qb->addSelect('reportMode.id AS reportModeId', 'reportMode.name AS reportModeName');

        $qb->where('e.process = :process')->setParameter('process', 'approved');
        $qb->andWhere('e.month IN (:months)')->setParameter('months', $months);
        $qb->andWhere('e.year IN (:years)')->setParameter('years', $years);
        if ($selectedFormat){
            $qb->andWhere('reportMode.id = :reportModeId')->setParameter('reportModeId', $selectedFormat);
        }

        $qb->orderBy('reportMode.id', 'ASC');

        $results = $qb->getQuery()->getArrayResult();

        $data = [];
        foreach ($results as $key => $result) {
            $data[$result['reportModeName']][$result['grade']]['details'][] = $result;

            $data[$result['reportModeName']][$result['grade']]['count'] = count($data[$result['reportModeName']][$result['grade']]['details']);

            $data[$result['reportModeName']]['total'] = array_sum(array_column($data[$result['reportModeName']], 'count'));

        }
        unset($data['gradeWiseTotal']);
//        ksort($data);

        return $data;
    }

    public function getRankingPeople($format, $months, $years, $flag, $limit)
    {

        $qb = $this->createQueryBuilder('e');

        $qb->join('e.reportMode', 'reportMode');
        $qb->join('e.employee', 'employee');

//        $qb->select('e.id', 'e.month', 'e.year', 'e.grade', 'e.obtainMark');
        $qb->select('AVG(e.obtainMark) AS obtainMarkAVG');
//        $qb->addSelect('reportMode.id AS reportModeId', 'reportMode.name AS reportModeName');
        $qb->addSelect('employee.userId', 'employee.name');

        $qb->where('e.process = :process')->setParameter('process', 'approved');
        $qb->andWhere('e.month IN (:months)')->setParameter('months', $months);
        $qb->andWhere('e.year IN (:years)')->setParameter('years', $years);
        $qb->andWhere('reportMode.slug = :slug')->setParameter('slug', $format);
//        $qb->andWhere('employee.userId = 36002');
        $qb->groupBy('employee.id');
        if ($flag === 'top') {
            $qb->orderBy('AVG(e.obtainMark)', 'DESC');
        }elseif ($flag === 'bottom'){
            $qb->orderBy('AVG(e.obtainMark)', 'ASC');
        }
        $qb->setMaxResults($limit);

        $results = $qb->getQuery()->getArrayResult();

//        $data = [];
//        foreach ($results as $result) {
//            $data[$result['userId']][] = $result;
//        }


        if ($flag === 'top') {
            usort($results, function($a, $b) {  //sort descending order by obtainMarkSum
                return $b['obtainMarkAVG'] <=> $a['obtainMarkAVG'];
            });
        } elseif ($flag === 'bottom') {
            usort($results, function($a, $b) {  //sort ascending order by obtainMarkSum
                return $a['obtainMarkAVG'] <=> $b['obtainMarkAVG'];
            });
        }

//        array_splice($results, 10); // get ten people

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                "employeeId" => "ID-" . $result['userId'] . "\n" . $result['name'],
                "value" => round($result['obtainMarkAVG'], 2) // two decimal
            ];
        }

        if ($flag === 'bottom') {
            $data = array_reverse($data);
        }

        return $data ? json_encode($data) : null;


//        $arrayVar = [
//            ["employeeId" => "ID-12345", "value" => 25],
//            ["employeeId" => "ID-12543", "value" => 70],
//        ];
    }

}
