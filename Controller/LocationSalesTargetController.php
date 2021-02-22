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

/**
 * @Route("/kpi/location-sales")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class LocationSalesTargetController extends AbstractController
{

    /**
     * Lists all Post entities.
     * @Route("/", methods={"GET"}, name="kpi_location_sales")
     */

    public  function location(): Response
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $entities = $this->getDoctrine()->getRepository(Location::class)->findBy(array('level'=> 4),array('parent' => 'ASC'));
        $products = $this->getDoctrine()->getRepository(MarkChart::class)->salesProductItems();
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
        $quantity = $_REQUEST['quantity'];
        if($entity){
            $entity->setQuantity($quantity);
            $em->flush();
            return new Response('Success');
        }
        return new Response('Failed');
    }


}