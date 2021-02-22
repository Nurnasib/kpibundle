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
use Terminalbd\KpiBundle\Entity\EmployeeSetup;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class EmployeeSetupRepository extends EntityRepository
{


    public function getEmployeeList(User $user)
    {

        $area  = $user->getArea();
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.employee','u');
        $qb->leftJoin('u.designation','d');
        $qb->select('e.id as id','e.status as status','u.name as name','d.name as designation');
        if($area == "Zonal"){
            $zonal = $user->getZonal()->getId();
            $qb->where('u.zonal = :zonal')->setParameter('zonal',$zonal);
            $qb->andWhere("u.area= 'regional'");
            $qb->andWhere("u.id != {$user->getId()}");
        }elseif($area == "Regional") {
            $regional = $user->getRegional()->getId();
            $qb->where('u.regional = :regional')->setParameter('regional', $regional);
            $qb->andWhere("u.area = 'upozila'");
            $qb->andWhere("u.id != {$user->getId()}");
        }
        $qb->orderBy('u.name','ASC');
        $result = $qb->getQuery()->getArrayResult();
        return $result;

    }

    public function getSalesAmount()
    {
        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.setupMatrix','p');
        $qb->leftJoin('p.sales','s');
        $qb->select('e.id as id','SUM(s.quantity) as quantity','SUM(s.amount) as amount');
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

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.setupMatrix','p');
        $qb->leftJoin('p.sales','s');
        $qb->join('p.district','u');
        $qb->select('u.id as district','SUM(s.quantity) as quantity','SUM(s.amount) as amount');
        $qb->where('e.id = :entity')->setParameter('entity',$entity);
        $qb->groupBy('u.id');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['district']] = $row;
        }
        return $data;
    }

    public function getItemWiseSalesAmount($setup)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.setupMatrix','p');
        $qb->leftJoin('p.sales','s');
        $qb->leftJoin('s.markDistribution','d');
        $qb->select('d.id as id','d.name as name','SUM(s.quantity) as quantity','SUM(s.amount) as amount');
        $qb->groupBy('d.id');
        $qb->where("e.id = {$setup}");
        $result = $qb->getQuery()->getArrayResult();
        return $result;

    }


    public function getDistrictWiseSalesAmount($districts)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.setupMatrix','p');
        $qb->leftJoin('p.sales','s');
        $qb->leftJoin('p.district','district');
        $qb->leftJoin('s.markDistribution','d');
        $qb->select('d.id as id','d.name as name','SUM(s.quantity) as quantity');
        $qb->groupBy('d.id');
        $qb->where('district.id IN (:ids)')->setParameter('ids', $districts);
        $result = $qb->getQuery()->getArrayResult();
        return $result;

    }

    public function getZonalItemWiseSalesAmount(EmployeeSetup $setup)
    {
        $zonal = $setup->getEmployee()->getZonal()->getId();
        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.setupMatrix','p');
        $qb->leftJoin('p.sales','s');
        $qb->leftJoin('s.markDistribution','d');
        $qb->select('d.id as id','d.name as name','SUM(s.amount) as amount');
        $qb->groupBy('d.id');
        $qb->where("p.zonal = {$zonal}");
        $result = $qb->getQuery()->getArrayResult();
        return $result;

    }

    public function getEmployeeSalesAmount($setup)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.setupMatrix','p');
        $qb->leftJoin('p.sales','s');
        $qb->select('e.id as id','SUM(s.amount) as amount');
        $qb->where("e.id = {$setup}");
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result['amount'];

    }

}
