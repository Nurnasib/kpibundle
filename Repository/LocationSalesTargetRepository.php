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
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
use function Doctrine\ORM\QueryBuilder;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class LocationSalesTargetRepository extends EntityRepository
{

    public function processLocationPrice($locations , $charts )
    {
        $currentYear = date('Y');
        foreach ($charts as $chart){
            $chartId=$chart->getId();

            foreach ($locations as $location ){
                $districtId = $location->getId();
                $regionalId = $location->getParent()->getId();
                $zonalId = $location->getParent()->getParent()->getId();
                $exist = $this->findOneBy(array('markDistribution' => $chart, 'district' => $location, 'year'=>$currentYear));
                if(empty($exist)){
                    for ($m=1; $m<=12; $m++) {
                        $month = date('F', mktime(0,0,0,$m, 1, date('Y')));
                        $year = date('Y', mktime(0,0,0,$m, 1, date('Y')));

                        $exist = $this->findOneBy(array('markDistribution' => $chart, 'district' => $location, 'month'=>$month, 'year'=>$year));
                            $sql ="INSERT INTO kpi_location_sales_target
    (`mark_distribution_id`, `district_id`,`regional_id`, `zone_id`, `month`, `year`, `quantity`) 
    VALUE ($chartId , $districtId , $regionalId , $zonalId , '{$month}', '{$year}', 1000)
    ";
                            $qb = $this->_em->getConnection()->prepare($sql);
                            $qb->execute();
                    }

                }


            }
        }

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','l');
        $qb->join('e.markDistribution','m');
        $qb->select('e.id as matrixId','e.quantity as quantity','e.month as month','e.year as year','l.id as districtId','m.id as markId','m.name as martDistribution');
        $result = $qb->getQuery()->getArrayResult();
        $array = array();
        foreach ($result as $item):
            $id = "{$item['year']}-{$item['month']}-{$item['districtId']}-{$item['markId']}";
            $array[$id] = $item;
        endforeach;
        return $array;

    }

    public function getLocationWiseTotalProductSalesTarget($locations)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.markDistribution','distribution');
        $qb->join('e.district','u');
        $qb->select('distribution.id as id','SUM(e.quantity) as quantity');
        $qb->where('u.id IN (:districts)')->setParameter('districts',$locations);
        $qb->groupBy('distribution.id');
        $result = $qb->getQuery()->getArrayResult();
        $data = array();
        foreach ($result as $row){
            $data[$row['id']] = $row;
        }
        return $data;

    }

    public function apiInsert($data)
    {
        $em = $this->_em;
        foreach ($data as $key => $value){

            $date = new \DateTime($data[$key]['created']);
            $entity = new AgentOrder();
            $entity->setCreated($date);
            $entity->setUpdated($date);
            $entity->setAmount($data[$key]['amount']);
            $entity->setQuantity($data[$key]['quantity']);
            $entity->setMonth($data[$key]['month']);
            $entity->setYear($data[$key]['year']);
            if($data[$key]['itemName']){
                $slug = strtolower($data[$key]['itemName']);
                $product = $em->getRepository(MarkChart::class)->findOneBy(array('slug' => $slug));
                $entity->setProduct($product);
            }
            if($data[$key]['agentId']){
                $agentId = $data[$key]['agentId'];
                $agent = $em->getRepository(Agent::class)->findOneBy(array('oldId' => $agentId));
                if($agent){
                    $entity->setAgent($agent);
                }
            }
            if($data[$key]['upozila']){
                $upozila = trim($data[$key]['upozila']);
                $location = $em->getRepository(Location::class)->findOneBy(array('oldId' => $upozila));
                $entity->setUpozila($location);
            }
            $em->persist($entity);
            $em->flush();
        }
    }

    public function getProductTarget()
    {
        $qb = $this->createQueryBuilder('e');

        $qb->select('e.amount AS targetAmount');
        $qb->addSelect('markDistribution.name AS breedType');

        $qb->where('regional.id = :regionalId')->setParameter('regionalId', 11);
//        $qb->where('district.id = :districtId')->setParameter('districtId', 18);
        $qb->andWhere($qb->expr()->isNotNull('e.amount'));

        $qb->leftJoin('e.markDistribution','markDistribution');
        $qb->leftJoin('e.regional','regional');
        $qb->leftJoin('e.district','district');

        $results = $qb->getQuery()->getArrayResult();

        $data = [];
        foreach ($results as $result){
            $data[$result['breedType']] = $result['targetAmount'];
        }
        return $results;
    }

    public function insertTargetAmount($file, $keys, $allData, $month, $year)
    {
        $data = [];
        $addedId = [];
        $em = $this->_em;
        $keysLength = count($keys);

        foreach ($allData as $value){
            $data[] = array_combine($keys,array_slice($value, null, $keysLength));
        }
        foreach ($data as $record){

            $district = $em->getRepository(Location::class)->findOneBy(['level'=>4,'code' => $record['DistrictId']]);

            unset($record['DistrictId']); //Remove DistrictId From $record
            unset($record['District']); //Remove District From $record

            array_splice($record, -2);   //Remove Last two item(month, year) from $record
            if ($district){
                foreach ($record as $key => $item) {
                    $breedType = $em->getRepository(MarkChart::class)->findOneBy(['name' => $key]);   //Breed Name exists or Not into MarkChart
                    if ($breedType){
                        $districSales = new LocationSalesTarget();

                        $exists = $em->getRepository(LocationSalesTarget::class)->findOneBy(['month' => $month, 'year' => $year, 'markDistribution' => $breedType, 'district'=>$district]);
                        if ($exists){
                            $districSales = $exists;
                        }
                        $districSales->setDistrict($district);
                        $districSales->setMarkDistribution($breedType);
                        $districSales->setQuantity($record[$key]);
                        $districSales->setMonth($month);
                        $districSales->setYear($year);

                        $em->persist($districSales);
                        $em->flush();

                        if ($exists){
                            $addedId['old'][] = $exists->getId();
                        }else{
                            $addedId['new'][] = $districSales->getId();
                        }
                    }
                }
            }
        }
        $file->setStatus(1);

        $em->persist($file);
        $em->flush();
        return $addedId;
    }


}
