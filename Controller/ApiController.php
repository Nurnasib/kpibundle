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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Repository\LocationSalesTargetRepository;
use Terminalbd\KpiBundle\Service\Api;

class ApiController extends AbstractController
{


    /**
     * @Route("kpi/api/location", name="kpi_api_location")
     */
    public function apiLocation()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $api = new Api();
        $method = 'get';
        $url = "http://www.cashbook.local/api-nourish.php?action=location";
        $get_data = $api->callAPI($method, $url, false);
        $response = json_decode($get_data, true);
        //  $errors = $response['response']['errors'];
        $data = $response;
        //$this->getDoctrine()->getRepository(Location::class)->apiInsert($data);
        return $this->render('@TerminalbdCrm/defult/index.html.twig');
    }


    /**
     * @Route("kpi/api/agent", name="kpi_api_agent")
     */
    public function apiAgent()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $api = new Api();
        $method = 'get';
        $url = "http://www.cashbook.local/api-nourish.php?action=agent";
        $get_data = $api->callAPI($method, $url, false);
        $response = json_decode($get_data, true);
        $this->getDoctrine()->getRepository(Agent::class)->apiInsert($response);
        return $this->render('@TerminalbdCrm/defult/index.html.twig');
    }


    /**
     * @Route("kpi/api/agent", name="crm_api_order")
     */

    public function apiOrder()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $api = new Api();
        $method = 'get';
        $url = "http://www.cashbook.local/api-nourish.php?action=agent";
        $get_data = $api->callAPI($method, $url, false);
        $response = json_decode($get_data, true);
        //  $errors = $response['response']['errors'];
        $data = $response;
        echo "<pre>";
        var_dump($data);
        exit;
        //  return $this->render('@TerminalbdCrm/defult/index.html.twig');
    }

    /**
     * @Route("/api/agent-order", name="crm_api_order_item")
     */

    public function apiOrderItem()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        $api = new Api();
        $method = 'get';
        $url = "http://www.cashbook.local/api-nourish.php?action=order-item";
        $get_data = $api->callAPI($method, $url, false);
        $response = json_decode($get_data, true);
        $this->getDoctrine()->getRepository(LocationSalesTarget::class)->apiInsert($response);
        //  return $this->render('@TerminalbdCrm/defult/index.html.twig');
    }

}