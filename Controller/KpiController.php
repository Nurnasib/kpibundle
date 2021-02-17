<?php
/**
 * Created by PhpStorm.
 * User: shafiq
 * Date: 9/29/19
 * Time: 9:09 PM
 */

namespace Terminalbd\KpiBundle\Controller;


use App\Entity\Admin\Location;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class KpiController extends AbstractController
{


    /**
     * @Route("kpi/mark", name="kpi_mark_index")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function index() {
        return $this->render('@TerminalbdKpi/defult/index.html.twig');
    }

    /**

     * @Route("kpi/location-mark-chart", name="kpi_mark_location")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function locationMark() {
        $entities = $this->getDoctrine()->getRepository(Location::class)->findBy(array('level'=> 4),array('parent' => 'ASC'));
        $products = $this->getDoctrine()->getRepository(MarkChart::class)->getChildRecords('product-wise-sales-achievement');
        $locationMarks = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->processLocationPrice($entities,$products);
        return $this->render('@TerminalbdKpi/markchart/location.html.twig',['entities' => $entities,'products' => $products , 'matrixArr' => $locationMarks]);
    }

    /**
     * updateLocationSales a LocationSalesTarget entity.
     *
     * @Route("/{id}/update-location-sales-target", methods={"GET"}, name="kpi_markchart_location_sales_target")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     */
    public function updateLocationSales($id) : Response
    {
        $entity = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->find($id);
        $em = $this->getDoctrine()->getManager();
        $amount = $_REQUEST['amount'];
        if($entity){
            $entity->setAmount($amount);
            $em->flush();
            return new Response('Success');
        }
        return new Response('Failed');
    }




}