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
use Terminalbd\KpiBundle\Entity\AgentOutstanding;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class AgentOutstandingRepository extends EntityRepository
{
    public function insertAgentOutstanding($file, $keys,$allData, $month, $year)
    {

        $data = [];
        $addedId = [];
        $em = $this->_em;

        foreach ($allData as $value){
            $data[] = array_combine($keys,$value);
        }

        foreach ($data as $record){
            $district = $em->getRepository(Location::class)->findOneBy(['level'=>4,'name' => $record['District']]);
            //Find agent
            $findAgent = $em->getRepository(Agent::class)->findOneBy(['agentId' =>$record['AgentId']]);
            if ($findAgent) {
                $agentOutstanding = new AgentOutstanding();
                $agentOutstanding->setAgent($findAgent);
                $agentOutstanding->setDistrict($district);
                $agentOutstanding->setActualAmount($record['ActualAmount']);
                $agentOutstanding->setOutstanding($record['Outstanding']);
                $agentOutstanding->setCreatedAt(new \DateTime());
                $agentOutstanding->setMonth($month);
                $agentOutstanding->setYear($year);
                $em->persist($agentOutstanding);
                $em->flush();
                $addedId = $agentOutstanding->getId();
            }
        }
        $file->setStatus(1);

        $em->persist($file);
        $em->flush();
        return $addedId;
    }
}
