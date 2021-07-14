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

//        $area  = $user->getArea();
        $userRoles = $user->getRoles();
        $qb = $this->createQueryBuilder('s');
//        $qb->join('s.employeeSetup','e');
        $qb->join('s.employee','u');
        $qb->leftJoin('u.lineManager','lm');
        $qb->leftJoin('u.designation','d');
        $qb->leftJoin('s.createdBy', 'createdBy');
        $qb->leftJoin('s.approvedBy', 'approvedBy');
        $qb->leftJoin('u.reportMode', 'reportMode');
        $qb->select('s.id as id','s.month as month','s.year as year','s.status','s.created');
        $qb->addSelect('u.name as name','d.name as designation');
        $qb->addSelect('lm.name as lineManager');
        $qb->addSelect('createdBy.name AS createdByName');
        $qb->addSelect('approvedBy.name AS approvedByName');
        $qb->addSelect('reportMode.name AS reportFormat');
/*        if($area == "Zonal"){
            $zonal = $user->getZonal()->getId();
            $qb->where('u.zonal = :zonal')->setParameter('zonal',$zonal);
            $qb->andWhere("u.area= 'regional'");
            $qb->andWhere("u.id != {$user->getId()}");
        }elseif($area == "Regional") {
            $regional = $user->getRegional()->getId();
            $qb->where('u.regional = :regional')->setParameter('regional', $regional);
            $qb->andWhere("u.area = 'upozila'");
            $qb->andWhere("u.id != {$user->getId()}");
        }*/

        if(!in_array('ROLE_ADMIN', $userRoles)){
            $qb->where('s.createdBy = :user')->setParameter('user', $user);
        }
        $qb->orderBy('s.created','DESC');
        $result = $qb->getQuery()->getArrayResult();
        return $result;

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


}
