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
    public function getDistricts(User $employee)
    {
        $em = $this->_em;

        $query = "SELECT * FROM kpi_employee_district_history WHERE id IN (SELECT MAX(id) FROM kpi_employee_district_history GROUP BY month,year ) AND employee_id = :employeeId";

        $stmt = $em->getConnection()->prepare($query);
        $stmt->bindValue('employeeId', $employee->getId());
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
