<?php


namespace Terminalbd\KpiBundle\Controller;


use App\Entity\Core\Agent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\AgentDocSaleCollection;

/**
 * @Route("/kpi/doc-sales-collection")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class DocSalesCollectionController extends AbstractController
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
     * @Route("/", methods={"GET"}, name="kpi_doc_sales_collection")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function docSalesCollection(Request $request)
    {
        $requestMonthYear = $request->query->get('monthYear');
        $requestAgentId = $request->query->get('agent');
        if ($requestAgentId){
            $agent = $this->getDoctrine()->getRepository(Agent::class)->find($requestAgentId);
        }else{
            $agent = null;
        }

        $data = [
            'month' => Date('F', strtotime(date('F') . " last month")),
            'year' => Date('Y', strtotime(date('Y') . " last year"))
        ];

        if($requestMonthYear){
            $explode= explode(',',$requestMonthYear);
            $data = ['month'=>$explode[0],'year'=>$explode[1]];
        }
        if ($requestAgentId){
            $data['agent'] = $requestAgentId;
        }

        $entities = $this->getDoctrine()->getRepository(AgentDocSaleCollection::class)->getMonthYearSalesCollection($data);
        $pagination = $this->paginate($request,$entities);

        return $this->render('@TerminalbdKpi/docSalesCollection/sales.html.twig', [
            'entities' => $pagination,
//            'monthYear' => $data,
            'selectedMonthYear' => $requestMonthYear,
            'agent' => $agent,
        ]);

    }
}