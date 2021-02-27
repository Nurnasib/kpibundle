<?php
/**
 * Created by PhpStorm.
 * User: shafiq
 * Date: 9/29/19
 * Time: 9:09 PM
 */

namespace Terminalbd\KpiBundle\Controller;


use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use App\Entity\Core\Setting;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\AgentOutstanding;
use Terminalbd\KpiBundle\Entity\DistrictOrder;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Repository\LocationSalesTargetRepository;
use Terminalbd\KpiBundle\Service\Api;

/**
 * @Route("/kpi/district")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */

class DistrictSalesController extends AbstractController
{

    public function paginate(Request $request ,$entities)
    {

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $entities,
            $request->query->get('page', 1)/*page number*/,
            25  /*limit per page*/
        );
        return $pagination;
    }

    /**
     * Lists all Post entities.
     * @Route("/sales", methods={"GET"}, name="kpi_district_sales")
     */
    public function sales(Request $request): Response
    {
        $data = $_REQUEST;
        if(empty($data)){
            $data = array('month'=>"January",'year'=>'2021');
        }
        $entities = $this->getDoctrine()->getRepository(DistrictOrder::class)->findDistrictSearch($data);
//        dd($entities);
        $districtSalesQty = $this->getDoctrine()->getRepository(DistrictOrder::class)->findDistrictOrderOty($data);
//        dd($districtSalesQty);
        $products = $this->getDoctrine()->getRepository(MarkChart::class)->salesProductItems();
        return $this->render('@TerminalbdKpi/district/sales.html.twig',
            [
                'pagination' => $entities,
                'items'=>$products,
                'districtSalesQty'=>$districtSalesQty,
            ]
        );
    }

}