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

use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use Doctrine\ORM\EntityRepository;
use Terminalbd\KpiBundle\Entity\AgentDocSaleCollection;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class AgentDocSaleCollectionRepository extends EntityRepository
{
    public function insertDocSalesCollection($file, $keys, $allData, $month, $year)
    {

        $data = [];
        $addedId = [];
        $em = $this->_em;
        foreach ($allData as $value){
            $data[] = array_combine($keys,$value);
        }

        foreach ($data as $record){

            $district = $em->getRepository(Location::class)->findOneBy(['level'=>4,'code' => $record['DistrictId']]);
            //Find agent
            $findAgent = $em->getRepository(Agent::class)->findOneBy(['agentId' =>$record['AgentId']]);
            if ($findAgent) {
                $agentDocSale = new AgentDocSaleCollection();
                $agentDocSale->setAgent($findAgent);
                $agentDocSale->setSales($record['Sales']);
                $agentDocSale->setCollection($record['Collection']);
                $agentDocSale->setDistrict($district?$district:null);
                $agentDocSale->setMonth($month);
                $agentDocSale->setYear($year);
                $agentDocSale->setCreatedAt(new \DateTime());
                $em->persist($agentDocSale);
                $em->flush();

                $addedId = $agentDocSale->getId();
            }
        }
        $file->setStatus(1);

        $em->persist($file);
        $em->flush();
        return $addedId;
    }


    public function getLocationWiseTotalDocSales($locations, $year, $month)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->select('SUM(e.sales) as totalSalesAmount', 'SUM(e.collection) as totalCollectionAmount');
        $qb->where('d.id IN (:districts)')->setParameter('districts',$locations);
        $qb->andWhere('e.year =:year')->setParameter('year',$year);
        $qb->andWhere('e.month =:month')->setParameter('month',$month);
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result;
    }
}
