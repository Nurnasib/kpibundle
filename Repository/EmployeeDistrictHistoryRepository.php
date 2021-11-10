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

        $query = "SELECT core_user.name, core_user.user_id, kpi_employee_district_history.district FROM kpi_employee_district_history JOIN core_user ON core_user.id = kpi_employee_district_history.employee_id WHERE kpi_employee_district_history.id IN (SELECT MAX(kpi_employee_district_history.id) FROM kpi_employee_district_history GROUP BY employee_id,month,year ) AND month = :month AND year = :year";

        if (!in_array('ROLE_ADMIN', $filterBy['user']->getRoles())){
            $query .= " AND core_user.line_manager_id = :lineManagerId";
        }

        $stmt = $em->getConnection()->prepare($query);
        $stmt->bindValue('month', $filterBy['month']);
        $stmt->bindValue('year', $filterBy['year']);

        if (!in_array('ROLE_ADMIN', $filterBy['user']->getRoles())){
            $stmt->bindValue('lineManagerId', $filterBy['user']->getId());
        }
        $stmt->execute();
        $records =  $stmt->fetchAll();

        $history = [];

        foreach ($records as $record){
            $history[$record['user_id']] = [
                'districts' => $record['district'],
            ];
        }

        $qb = $this->_em->createQueryBuilder();
        $qb->select('u.id','u.userId','u.name AS employeeName', 'district.name AS districtName')
            ->from(User::class, 'u')
            ->leftJoin('u.district', 'district')
            ->where("u.userMode = 'KPI'")
            ->andWhere('u.enabled = 1')
            ->orderBy('u.userId', 'ASC')
        ;
        if (!in_array('ROLE_ADMIN', $filterBy['user']->getRoles())){
            $qb->leftJoin('u.lineManager', 'lineManager')
                ->andWhere('lineManager.id = :lineManagerId')->setParameter('lineManagerId', $filterBy['user']->getId());
        }

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
        $em = $this->_em;

        $query = "SELECT * FROM kpi_employee_district_history WHERE id IN (SELECT MAX(id) FROM kpi_employee_district_history WHERE employee_id = :employeeId GROUP BY month,year)";

        $stmt = $em->getConnection()->prepare($query);
        $stmt->bindValue('employeeId', $employee->getId());
        $stmt->execute();
        $records =  $stmt->fetchAll();
        $data = [];
        foreach ($records as $record) {

            $data[$record['year'].'-'.$record['month']] = $record;
        }
        return $data;
    }
}
