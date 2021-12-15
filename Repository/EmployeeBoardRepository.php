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
        $qb->leftJoin('u.lineManager','lm');
        $qb->leftJoin('u.designation','d');
        $qb->leftJoin('s.createdBy', 'createdBy');
        $qb->leftJoin('s.approvedBy', 'approvedBy');
//        $qb->leftJoin('u.reportMode', 'reportMode');
        $qb->leftJoin('s.reportMode', 'reportMode');
        $qb->select('s.id as id','s.month as month','s.year as year','s.status','s.created', 's.district');
        $qb->addSelect('u.name as name','d.name as designation');
        $qb->addSelect('lm.name as lineManager');
        $qb->addSelect('createdBy.name AS createdByName');
        $qb->addSelect('approvedBy.name AS approvedByName');
        $qb->addSelect('reportMode.name AS reportFormat');
        $qb->addSelect('reportMode.slug AS reportFormatSlug');

        if (in_array('ROLE_KPI_LINE_MANAGER', $user->getRoles())){
            $qb->andWhere('u.lineManager = :user')->setParameter('user', $user);
        }elseif (!in_array('ROLE_KPI_LINE_MANAGER', $user->getRoles()) && !in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            $qb->andWhere('s.employee = :user')->setParameter('user', $user);
        }
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
        $qb->leftJoin('u.lineManager','lm');
        $qb->leftJoin('u.designation','d');
        $qb->leftJoin('s.createdBy', 'createdBy');
        $qb->leftJoin('s.approvedBy', 'approvedBy');
        $qb->leftJoin('u.reportMode', 'reportMode');
        $qb->select('s.id as id','s.month as month','s.year as year','s.status','s.created','s.district');
        $qb->addSelect('u.name as name','d.name as designation');
        $qb->addSelect('lm.name as lineManager');
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
        if (array_key_exists('createdBy', $filterBy) && isset($filterBy['createdBy'])){
            $qb->andWhere('createdBy.id = :createdById')->setParameter('createdById', $filterBy['createdBy']);
        }

        if (in_array('ROLE_KPI_LINE_MANAGER', $user->getRoles())){
            $qb->andWhere('u.lineManager = :user')->setParameter('user', $user);
        }elseif (!in_array('ROLE_KPI_LINE_MANAGER', $user->getRoles()) && !in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            $qb->andWhere('s.employee = :user')->setParameter('user', $user);
        }

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

    public function getGeneratedTeamMember($totalTeamMembers, $month, $year)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->where('e.year = :year')->setParameter('year', $year)
            ->andWhere('e.month = :month')->setParameter('month', $month)
            ->andWhere($qb->expr()->isNotNull('e.approvedBy'))
            ->andWhere('e.employee IN (:employee)')->setParameter('employee', $totalTeamMembers)
            ;

        return $qb->getQuery()->getResult();
    }

    public function getMonthlyStatus($year, $selectedLineManager, User $user)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employee', 'employee');
        $qb->join('employee.lineManager', 'lineManager');
        $qb->select('e.month');
        $qb->addSelect('employee.userId', 'employee.name');
        $qb->addSelect('lineManager.userId AS lineManagerUserId', 'lineManager.name AS lineManagerName');
        $qb->where('e.year = :year')->setParameter('year', $year);
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
            foreach ($results as $result) {
                $data[$result['userId']]['name'] = $result['name'];
                $data[$result['userId']]['userId'] = $result['userId'];
                $data[$result['userId']]['status'][$result['month']] = true;
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
        $qb->leftJoin('employee.lineManager', 'lineManager');
        $qb->leftJoin('employee.designation', 'designation');
        $qb->select('board.selfMark', 'board.selfGrade', 'board.obtainMark AS lineManagerMark', 'board.grade AS lineManagerGrade', 'board.month');
        $qb->addSelect('employee.id AS employeeId', 'employee.userId', 'employee.name AS employeeName', 'employee.joiningDate');
        $qb->addSelect('lineManager.name AS lineManagerName');
        $qb->addSelect('designation.name AS designationName');
        $qb->where('board.approvedBy IS NOT NULL');
//        if (!in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            $qb->andWhere('employee.lineManager = :lineManager')->setParameter('lineManager', $user);
//        }
        $qb->andWhere('board.year = :year')->setParameter('year', $year);
        $results = $qb->getQuery()->getResult();
        $data = [];
        foreach ($results as $result) {
            $data[$result['userId']]['employeeId'] = $result['employeeId'];
            $data[$result['userId']]['userId'] = $result['userId'];
            $data[$result['userId']]['employeeName'] = $result['employeeName'];
            $data[$result['userId']]['lineManagerName'] = $result['lineManagerName'];
            $data[$result['userId']]['joiningDate'] = $result['joiningDate'];
            $data[$result['userId']]['designationName'] = $result['designationName'];
            $data[$result['userId']]['month'][$result['month']] = [
                'selfMark' => $result['selfMark'],
                'selfGrade' => $result['selfGrade'],
                'lineManagerMark' => $result['lineManagerMark'],
                'lineManagerGrade' => $result['lineManagerGrade'],
            ];
            if (array_key_exists('totalSelfMark', $data[$result['userId']])){
                $data[$result['userId']]['totalSelfMark'] += $result['selfMark'];
            }else{
                $data[$result['userId']]['totalSelfMark'] = $result['selfMark'];
            }

            if (array_key_exists('totalLineManagerMark', $data[$result['userId']])){
                $data[$result['userId']]['totalLineManagerMark'] += $result['lineManagerMark'];
            }else{
                $data[$result['userId']]['totalLineManagerMark'] = $result['lineManagerMark'];
            }
            $data[$result['userId']]['totalSelfMarkAverage'] = $data[$result['userId']]['totalSelfMark'] / count($data[$result['userId']]['month']);
            $data[$result['userId']]['totalLineManagerMarkAverage'] = $data[$result['userId']]['totalLineManagerMark'] / count($data[$result['userId']]['month']);
            $data[$result['userId']]['selfGrade'] = $this->_em->getRepository(EmployeeBoardAttribute::class)->gradeCalculation($data[$result['userId']]['totalSelfMarkAverage']);
            $data[$result['userId']]['lineManagerGrade'] = $this->_em->getRepository(EmployeeBoardAttribute::class)->gradeCalculation($data[$result['userId']]['totalLineManagerMarkAverage']);
        }
        return $data;
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

}
