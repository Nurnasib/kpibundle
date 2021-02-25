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
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
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
     * @Route("/", methods={"GET"}, name="kpi_agent")
     */
    public function index(): Response
    {
        return $this->render('@TerminalbdKpi/agent/index.html.twig');

    }

    /**
     * Lists all Post entities.
     * @Route("/sales", methods={"GET"}, name="kpi_agent_sales")
     */
    public function sales(Request $request): Response
    {
        $data = $_REQUEST;
        if(empty($data)){
            $data = array('month'=>"January",'year'=>'2020');
        }
        $entities = $this->getDoctrine()->getRepository(AgentOrder::class)->findWithAgentSearch($data);
        $pagination = $this->paginate($request,$entities);
        $salesItems = $this->getDoctrine()->getRepository(AgentOrder::class)->findSalesItems($pagination,$data);
        $products = $this->getDoctrine()->getRepository(MarkChart::class)->salesProductItems();
        return $this->render('@TerminalbdKpi/agent/sales.html.twig',
            ['pagination' => $pagination,'salesItems'=>$salesItems,'items'=>$products]
        );
    }

    /**
     * Lists all Post entities.
     * @Route("/{id}/outstanding", methods={"GET"}, name="kpi_agent_outstanding")
     */
    public function agentOutstanding(Agent $agent)
    {
        $find = $this->getDoctrine()->getRepository(AgentOutstanding::class)->findBy(['agent'=>$agent]);
        $em = $this->getDoctrine()->getManager();
        if(empty($find)){
            $products = $this->getDoctrine()->getRepository(MarkChart::class)->salesProductItems();
            foreach ($products as $product){
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
}