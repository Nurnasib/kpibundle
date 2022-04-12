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
use App\Repository\Core\AgentRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\AgentOutstanding;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Form\AgentSearchFilterFormType;
use Terminalbd\KpiBundle\Repository\LocationSalesTargetRepository;
use Terminalbd\KpiBundle\Service\Api;

/**
 * @Route("/kpi/agent")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */

class AgentSalesController extends AbstractController
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
     * @Route("/", name="kpi_agent")
     * @param Request $request
     * @param AgentRepository $repository
     * @return Response
     */
    public function index(Request $request, AgentRepository $repository): Response
    {
        set_time_limit(0);
        ignore_user_abort(true);
        ini_set('memory_limit', '5000M');


        $agents = $repository->getAllAgents();
        $searchForm = $this->createForm(AgentSearchFilterFormType::class);
        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted()){
            $filterBy = $searchForm->getData();
            $filterBy['agentId'] = $filterBy['agentName'] ? $filterBy['agentName']->getId() : null;
            if ($filterBy['agentId'] || $filterBy['agentMobile'] || $filterBy['agentEmail']){
                $agents = $repository->getSearchAgent($filterBy);
            }else{
                $agents = [];
            }
            if ($request->query->has('pdf')){
/*                if (! $filterBy['agentId'] && $filterBy['agentMobile'] && $filterBy['agentEmail']){
                    $agents = $repository->getAllAgents();
                }*/

                // Configure Dompdf according to your needs
                $pdfOptions = new Options();
                $pdfOptions->set('defaultFont', 'Arial, sans-serif');

                // Instantiate Dompdf with our options
                $dompdf = new Dompdf($pdfOptions);

                // Retrieve the HTML generated in our twig file
                $html = $this->renderView('@TerminalbdKpi/agent/agentsPdf.html.twig',[
                    'data' => $agents,
                ]);

                // Load HTML to Dompdf
                $dompdf->loadHtml($html);

                // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
                $dompdf->setPaper('legal', 'landscape');

                // Render the HTML as PDF
                $dompdf->render();

                // Output the generated PDF to Browser (force download)
                $fileName = $request->get('_route') . '-' . time();
                $dompdf->stream( $fileName .  ".pdf", [
                    "Attachment" => true
                ]);
                die();
            }elseif ($request->query->has('excel')){

                $html = $this->renderView('@TerminalbdKpi/agent/agentsExcel.html.twig',[
                    'data' => $agents,
                ]);
                $fileName = $request->get('_route').'_'.time().'.xls';
                header("Content-Type: application/vnd.ms-excel; charset=utf-8");
                header("Content-Disposition: attachement; filename=$fileName");
                echo $html;
                die();

            }
        }
        $data = $this->paginate($request,$agents);
        return $this->render('@TerminalbdKpi/agent/index.html.twig',[
            'data' => $data,
            'searchForm' => $searchForm->createView(),
        ]);

    }

    /**
     * Lists all Post entities.
     * @Route("/sales", methods={"GET"}, name="kpi_agent_sales")
     * @param Request $request
     * @return Response
     */
    public function sales(Request $request): Response
    {
        $allData = $request->query->all();

        $requestData = isset($allData['monthYear']) ? $allData['monthYear'] : '';
        $requestAgentId = isset($allData['agent']) ? $allData['agent'] : '';
        $districtId = isset($allData['district']) ? $allData['district'] : '';
        if ($districtId){
            $district = $this->getDoctrine()->getRepository(Location::class)->find($districtId);
        }else{
            $district = null;
        }
        if ($requestAgentId){
            $agent = $this->getDoctrine()->getRepository(Agent::class)->find($requestAgentId);
        }else{
            $agent = null;
        }
        
        $data = array('month'=>Date('F', strtotime(date('F') . " last month")),'year'=>date('Y'),'agent'=>null, 'district' => null);

        if($requestData){
            $explode= explode(',',$requestData);
            $data = array('month'=>$explode[0],'year'=>$explode[1]);
        }
        if($requestAgentId){
            $data['agent'] = $requestAgentId;
        }
        if($districtId){
            $data['district'] = $districtId;
        }

        $entities = $this->getDoctrine()->getRepository(AgentOrder::class)->findWithAgentSearch($data);

        $agentSalesQty = $this->getDoctrine()->getRepository(AgentOrder::class)->findWithAgentOrderOty($data);
        $pagination = $this->paginate($request,$entities);
        $salesItems = $this->getDoctrine()->getRepository(AgentOrder::class)->findSalesItems($pagination,$data);
        $products = $this->getDoctrine()->getRepository(MarkChart::class)->salesProductItems();
        return $this->render('@TerminalbdKpi/agent/sales.html.twig',
            [
                'pagination' => $pagination,
                'salesItems'=> $salesItems,
                'items'=> $products,
                'agentSalesQty'=> $agentSalesQty,
                'selectedMonthYear'=> $requestData,
                'district'=> $district,
                'agent'=> $agent,
                'data'=> $data,
            ]
        );
    }

    /**
     * Lists all Post entities.
     * @Route("/{id}/outstanding", methods={"GET"}, name="kpi_agent_outstanding")
     * @param Agent $agent
     * @return Response
     */
    public function agentOutstanding(Agent $agent)
    {
        $em = $this->getDoctrine()->getManager();

        $products = $this->getDoctrine()->getRepository(MarkChart::class)->salesProductItems();
        foreach ($products as $product){
            $find = $this->getDoctrine()->getRepository(AgentOutstanding::class)->findOneBy(['agent'=>$agent,'product'=>$product]);
            if(empty($find)){
                $entity = new AgentOutstanding();
                $entity->setAgent($agent);
                $entity->setProduct($product);
                $em->persist($entity);
                $em->flush();
            }
        }
        return $this->render('@TerminalbdKpi/agent/outstanding.html.twig',
            ['agent' => $agent]
        );
    }

    /**
     * @Route("/test-outstanding")
     */
    public function agentOutstandingTest()
    {
        $result = $this->getDoctrine()->getRepository(AgentOrder::class)->getOutstanding();
        dd($result);
    }
}