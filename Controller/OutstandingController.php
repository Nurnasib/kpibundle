<?php


namespace Terminalbd\KpiBundle\Controller;


use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\AgentDocSaleCollection;
use Terminalbd\KpiBundle\Entity\AgentOutstanding;

/**
 * @Route("/kpi/outstanding")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class OutstandingController extends AbstractController
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
     * @Route("/", methods={"GET"}, name="kpi_outstanding")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function agentOutstanding(Request $request)
    {
        $requestMonthYear = $request->query->get('monthYear');
        $agentId = $request->query->get('agent');
        $districtId = $request->query->get('district');
        
        if ($agentId){
            $agent = $this->getDoctrine()->getRepository(Agent::class)->find($agentId);
        }else{
            $agent = null;
        }    
        
        if ($districtId){
            $district = $this->getDoctrine()->getRepository(Location::class)->find($districtId);
        }else{
            $district = null;
        }
        
//        $monthYear = array('month'=>Date('F', strtotime(date('F') . " last month")),'year'=>Date('Y', strtotime(date('Y') . " last year")));
        $monthYear = array('month'=>Date('F', strtotime(date('F') . " last month")),'year'=>Date('Y'));

        if($requestMonthYear){
            $explode= explode(',',$requestMonthYear);
            $monthYear = array('month'=>$explode[0],'year'=>$explode[1]);
        }
        $entities = $this->getDoctrine()->getRepository(AgentOutstanding::class)->getMonthYearOutstanding($monthYear, $agentId, $districtId);
        $pagination = $this->paginate($request,$entities);

        return $this->render('@TerminalbdKpi/outstanding/outstanding.html.twig', [
            'entities' => $pagination,
            'monthYear' => $monthYear,
            'selectedMonthYear'=>$requestMonthYear,
            'district'=> $district,
            'agent'=> $agent,
        ]);

    }
}