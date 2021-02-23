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

use Doctrine\ORM\EntityRepository;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class DistrictOrderRepository extends EntityRepository
{

    public function getLocationWiseTotalProductSales($locations)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.product','distribution');
        $qb->join('e.upozila','u');
        $qb->select('distribution.id as distributionId','SUM(e.amount) as amount');
        $qb->where('u.id IN (:upozils)')->setParameter('upozils',$locations);
        $qb->groupBy('distribution.id');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['distributionId']] = $row;
        }
        return $data;

    }


}
