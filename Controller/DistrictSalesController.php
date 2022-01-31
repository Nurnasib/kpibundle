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
use http\QueryString;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
 * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_LINE_MANAGER')")
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
     * @param Request $request
     * @return Response
     */
    public function sales(Request $request): Response
    {
        $requestData = $request->query->get('monthYear');
//        $data = array('month'=>date('F'),'year'=>date('Y'));
        $districtId = $request->query->get('district') ?: null;
        if ($districtId){
            $district = $this->getDoctrine()->getRepository(Location::class)->find($districtId);
        }else{
            $district = null;
        }
        $data = array('month'=>Date('F', strtotime(date('F') . " last month")),'year'=>date('Y'), 'district' => null);
        if($requestData){
            $explode= explode(',',$requestData);
            $data = array('month'=>$explode[0],'year'=>$explode[1]);
        }
        if($districtId){
            $data['district'] = $districtId;
        }
        $entities = $this->getDoctrine()->getRepository(DistrictOrder::class)->findDistrictSearch($data);
        $districtSalesQty = $this->getDoctrine()->getRepository(DistrictOrder::class)->findDistrictOrderOty($data);
        $products = $this->getDoctrine()->getRepository(MarkChart::class)->salesProductItems();
        return $this->render('@TerminalbdKpi/district/sales.html.twig',
            [
                'pagination' => $entities,
                'items'=>$products,
                'districtSalesQty'=>$districtSalesQty,
                'selectedMonthYear'=>$requestData,
                'district'=> $district,
            ]
        );
    }

}