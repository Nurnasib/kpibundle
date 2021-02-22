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
use Doctrine\DBAL\DriverManager;
use App\Entity\Admin\Location;
use Doctrine\ORM\EntityRepository;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Entity\SetupMatrix;

/**
 * This custom Doctrine repository contains some methods which are useful when
 * querying for blog post information.
 *
 * See https://symfony.com/doc/current/doctrine/repository.html
 *
 * @author Md Shafiqul islam <shafiqabs@gmail.com>
 */
class SetupMatrixRepository extends EntityRepository
{

    public function processEmployeeSetup(EmployeeSetup $setup)
    {

        $em = $this->_em;
        $products = $em->getRepository(MarkChart::class)->getChildRecords('product-wise-sales-achievement');
        foreach ($setup->getEmployee()->getUpozila() as $upozila):
            foreach ($products as $product):
                $sales = $em->getRepository(LocationSalesTarget::class)->findOneBy(array('upozila' => $upozila,'markDistribution' => $product));
                $exist = $this->findOneBy(array('employeeSetup' => $setup, 'sales' => $sales, 'markDistribution' => $product));
                if(empty($exist)){
                    $entity = new SetupMatrix();
                    $entity->setEmployeeSetup($setup);
                    $entity->setMarkDistribution($product);
                    $entity->setSales($sales);
                    $entity->setUpozila($upozila);
                    $entity->setDistrict($upozila->getParent());
                    $entity->setRegional($upozila->getParent()->getParent());
                    $entity->setZonal($upozila->getParent()->getParent()->getParent());
                    $em->persist($entity);
                    $em->flush();
                }
            endforeach;
        endforeach;

    }

    public function processRegionalEmployeeSetup(EmployeeSetup $setup)
    {

        $em = $this->_em;
        $products = $em->getRepository(MarkChart::class)->getChildRecords('product-wise-sales-achievement');
        foreach ($setup->getEmployee()->getUpozila() as $upozila):
            foreach ($products as $product):
                $sales = $em->getRepository(LocationSalesTarget::class)->findOneBy(array('upozila' => $upozila,'markDistribution' => $product));
                $exist = $this->findOneBy(array('employeeSetup' => $setup, 'sales' => $sales, 'markDistribution' => $product));
                if(empty($exist)){
                    $entity = new SetupMatrix();
                    $entity->setEmployeeSetup($setup);
                    $entity->setMarkDistribution($product);
                    $entity->setSales($sales);
                    $entity->setUpozila($upozila);
                    $entity->setDistrict($upozila->getParent());
                    $entity->setRegional($upozila->getParent()->getParent());
                    $entity->setZonal($upozila->getParent()->getParent()->getParent());
                    $em->persist($entity);
                    $em->flush();
                }
            endforeach;
        endforeach;

    }

    public function  getDistrictMatrix(EmployeeSetup $setup)
    {

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->leftJoin('e.zonal','z');
        $qb->leftJoin('e.regional','r');
        $qb->select('d.id as id','d.name as name','z.name as zonalName','r.name as regionalName','d.name as districtName');
        $qb->groupBy('d.id');
        $qb->where("e.employeeSetup = {$setup->getId()}");
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }


    public function  getUpozilaMatrix(EmployeeSetup $setup,$pram)
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','d');
        $qb->leftJoin('e.zonal','z');
        $qb->leftJoin('e.regional','r');
        $qb->select('d.id as id','d.name as name','z.name as zonalName','r.name as regionalName','d.name as districtName');
        $qb->groupBy('d.id');
        if($pram == "sales"){
            $qb->where("e.employeeSetup = {$setup->getId()}");
        }elseif ($pram == "district"){
            $regional = $setup ->getEmployee()->getDistrict()->getId();
            $qb->where("e.district = {$regional}");
         }elseif ($pram == "regional"){
            $regional = $setup ->getEmployee()->getRegional()->getId();
            $qb->where("e.regional = {$regional}");
        }elseif ($pram == "zonal"){
            $zonal = $setup ->getEmployee()->getZonal()->getId();
            $qb->where("e.zonal = {$zonal}");
        }
        $result = $qb->getQuery()->getArrayResult();
        return $result;
    }


    public function itemWithLocationMatrix(EmployeeSetup $setup, $pram = "")
    {
        $qb = $this->createQueryBuilder('e');
        $qb->join('e.sales','s');
        $qb->join('e.district','l');
        $qb->join('e.markDistribution','m');
        $qb->select('s.amount as amount','l.id as districtId','m.id as markId','m.name as martDistribution');
        $qb->where("e.employeeSetup = {$setup->getId()}");
        $result = $qb->getQuery()->getArrayResult();
        $array = array();
        foreach ($result as $item):
            $id = "{$item['districtId']}-{$item['markId']}";
            $array[$id] = $item;
        endforeach;
        return $array;
    }

    public function  getUozilaMatrix(EmployeeSetup $setup,$pram)
    {

        $markIds = array();

        $qb = $this->createQueryBuilder('e');
        $qb->leftJoin('e.sales','s');
        $qb->leftJoin('s.markDistribution','d');
        $qb->select('d.id as markId','SUM(s.quantity) as quantity');
        $qb->groupBy('markId');
        $qb->where("e.employeeSetup = {$setup->getId()}");
        $result1 = $qb->getQuery()->getArrayResult();
        foreach ($result1 as $item):
            $markIds[$item['markId']] = $item;
        endforeach;;

        $qb = $this->createQueryBuilder('e');
        $qb->join('e.district','l');
        $qb->leftJoin('e.sales','s');
        $qb->select('l.id as districtId','SUM(s.amount) as amount');
        $qb->groupBy('upozilaId');
        $qb->where("e.employeeSetup = {$setup->getId()}");
        $result = $qb->getQuery()->getArrayResult();
        foreach ($result as $item):
            $districtIds[$item['districtId']] = $item;
        endforeach;
        return $vars = array('markIds' => $markIds ,'districtIds' => $districtIds );
    }


    public function processUpdateEmployeeSetup(EmployeeSetup $setup , $data )
    {
        $em = $this->_em;
        foreach ($data['district'] as $key => $value ){
            if($data['chartIds'][$value]){
                foreach ($data['chartIds'][$value] as $item => $row){
                    $exist = $this->findOneBy(array('employeeSetup' => $setup, 'markChart' => $row));
                    if(empty($exist)){
                        $sales = $em->getRepository(LocationSalesTarget::class)->find($row);
                        $entity = new SetupMatrix();
                        $entity->setEmployeeSetup($setup);
                        $entity->setSales($sales);
                        $entity->setDistrict($sales->getUpozila());
                        $em->persist($entity);
                        $em->flush();
                    }
                }
            }
        }

    }

    public function insertUpdateSalesPrice($setup , $location )
    {
        $em = $this->_em;
        /* @var $upozila Location */
        $setup =  $em->getRepository(EmployeeSetup::class)->find($setup);
        $upozila =  $em->getRepository(Location::class)->find($location);
        foreach ($upozila->getSalesTarget() as $row){
            $exist = $this->findOneBy(array('employeeSetup' => $setup, 'markChart' => $row));
            if(empty($exist)){
                $entity = new SetupMatrix();
                $entity->setEmployeeSetup($setup);
                $entity->setSales($row);
                $entity->setDistrict($upozila->getParent());
                $entity->setRegional($upozila->getParent()->getParent());
                $entity->setZonal($upozila->getParent()->getParent()->getParent());
                $em->persist($entity);
                $em->flush();
            }
        }
    }

    public function deleteRow($employee,$upozila)
    {
        $em = $this->_em;
        $entities = $em->getRepository(SetupMatrix::class)->findBy(array('employeeSetup'=>$employee,'upozila'=>$upozila));
        foreach ($entities as $entity):
            $em->remove($entity);
            $em->flush();
        endforeach;
    }

}
