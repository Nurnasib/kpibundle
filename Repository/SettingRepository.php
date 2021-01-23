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
class SettingRepository extends EntityRepository
{

    public function getChildRecords($parent)
    {
        $em = $this->_em;
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.settingType','p');
        $qb->select('e.id as id','e.name as name');
        $qb->where('p.slug = :slug')->setParameter('slug',$parent);
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }

}
