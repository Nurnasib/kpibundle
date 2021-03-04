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
    private function insertDocSalesCollection($file, $keys, $allData, $month, $year)
    {

        $data = [];
        $addedId = [];
        $em = $this->_em;
        foreach ($allData as $value){
            $data[] = array_combine($keys,$value);
        }

        foreach ($data as $record){
//            dump($record);

            $district = $em->getRepository(Location::class)->findOneBy(['level'=>4,'name' => $record['District']]);
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
}
