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
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeDistrictHistory;
use function Doctrine\ORM\QueryBuilder;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class EmployeeDistrictHistoryRepository extends EntityRepository
{
    public function getDistrictHistory($filterBy)
    {
        $em = $this->_em;

        $query = "SELECT employee.name, employee.user_id, kpi_employee_district_history.district, line_manager.name AS line_manager_name, line_manager.user_id AS line_manager_id
                    FROM kpi_employee_district_history 
                    JOIN core_user employee ON employee.id = kpi_employee_district_history.employee_id 
                    LEFT JOIN core_user line_manager ON line_manager.id = kpi_employee_district_history.line_manager_id 
                    WHERE kpi_employee_district_history.id IN (
                    SELECT MAX(kpi_employee_district_history.id) 
                    FROM kpi_employee_district_history 
                    GROUP BY employee_id,month,year 
                    ) 
                    AND month = :month AND year = :year";

        if (!in_array('ROLE_KPI_ADMIN', $filterBy['user']->getRoles())){
            $query .= " AND kpi_employee_district_history.line_manager_id = :lineManagerId";
        }
//        if ($filterBy['employee']){
//            $query .= " AND employee.id = :employeeId";
//        }

        $stmt = $em->getConnection()->prepare($query);
        $stmt->bindValue('month', $filterBy['month']);
        $stmt->bindValue('year', $filterBy['year']);

        if (!in_array('ROLE_KPI_ADMIN', $filterBy['user']->getRoles())){
            $stmt->bindValue('lineManagerId', $filterBy['user']->getId());
        }
//        if ($filterBy['employee']){
//            $stmt->bindValue('employeeId', $filterBy['employee']->getId());
//        }
        $stmt->execute();
        $records =  $stmt->fetchAll();

        $history = [];

        foreach ($records as $record){
            $districtArray = json_decode($record['district'], true);

            if ($districtArray){
                $history[$record['user_id']]['districts'] = implode(', ', $districtArray);
            }else{
                $history[$record['user_id']]['districts'] = '';
            }
            $history[$record['user_id']]['line_manager'] = $record['line_manager_id'] ? ('(' . $record['line_manager_id'] . ') ' . $record['line_manager_name']) : '';

        }



        $qb = $this->_em->createQueryBuilder();
        $qb->select('u.id','u.userId','u.name AS employeeName', 'district.name AS districtName')
            ->from(User::class, 'u')
            ->leftJoin('u.district', 'district')
            ->where("u.userMode = 'KPI'")
            ->andWhere('u.enabled = 1')
            ->orderBy('u.userId', 'ASC')
        ;
        if (!in_array('ROLE_KPI_ADMIN', $filterBy['user']->getRoles())){
//            $qb->leftJoin('u.lineManager', 'lineManager')
//                ->andWhere('lineManager.id = :lineManagerId')->setParameter('lineManagerId', $filterBy['user']->getId());
        }
//        if ($filterBy['employee']){
//            $qb->andWhere('u.id = :employeeId')->setParameter('employeeId', $filterBy['employee']->getId());
//        }

        $allEmployees = $qb->getQuery()->getArrayResult();

        $allEmployeesArray = [];
        foreach ($allEmployees as $allEmployee) {
            $allEmployeesArray[$allEmployee['userId']]['employeeName']=  $allEmployee['employeeName'];
            $allEmployeesArray[$allEmployee['userId']]['userId']=  $allEmployee['userId'];
            $allEmployeesArray[$allEmployee['userId']]['districts'][]=  $allEmployee['districtName'];
        }
        foreach ($allEmployeesArray as $item) {
            $allEmployeesArray[$item['userId']]['districts'] = implode(', ', $item['districts']);
        }

        return ['allEmployee' => $allEmployeesArray, 'history' => $history];
    }

    public function getDistricts(User $employee)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->join('e.employee', 'employee');
        $qb->leftJoin('e.updatedBy', 'updatedBy');
        $qb->where('employee.id = :employeeId')->setParameter('employeeId', $employee->getId());
        
        $qb->select('e.id', 'e.district', 'e.month', 'e.year', 'e.createdAt', 'e.updatedAt');
        $qb->addSelect('updatedBy.name AS updatedByName');
        $qb->orderBy('e.year', 'DESC');

        $records = $qb->getQuery()->getArrayResult();

        $data = [];

        foreach ($records as $record) {
            $arr = json_decode($record['district'], true);
            $record['district'] = $arr ? implode(', ', $arr) : '';
            $monthNumber = date("m", strtotime($record['month']));

            $data[$record['year']][$monthNumber . '-' . $record['month']] = $record;
            ksort($data[$record['year']]);
        }
        return $data;
    }


    public function getActiveTeamMembers($emp, $month, $year)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->join('e.employee', 'employee');

        $qb->andWhere('e.month = :month')->setParameter('month', $month);
        $qb->andWhere('e.year = :year')->setParameter('year', $year);
        $qb->andWhere("employee.enabled = 1");
        $qb->andWhere("employee.isPermanent = true");
        $qb->andWhere('e.lineManager = :lineManager')->setParameter('lineManager', $emp);
//        $qb->andWhere('employee.permanentDate <= :date')->setParameter('date', date('Y-m-d', strtotime("01-$month-$year")));
        $qb->andWhere($qb->expr()->andX(
            $qb->expr()->isNotNull('employee.permanentDate'),
            $qb->expr()->lte('employee.permanentDate', ':date')
        ))->setParameter('date', date('Y-m-d', strtotime("01-$month-$year")));

        return $qb->getQuery()->getResult();
    }

    public function getTeamMembers($emp, $month, $year)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->join('e.employee', 'employee');

        $qb->andWhere('e.month = :month')->setParameter('month', $month);
        $qb->andWhere('e.year = :year')->setParameter('year', $year);
        $qb->andWhere("employee.enabled = 1");
        $qb->andWhere("employee.isPermanent = true");
        $qb->andWhere('e.lineManager = :lineManager')->setParameter('lineManager', $emp);

        return $qb->getQuery()->getResult();
    }
}
