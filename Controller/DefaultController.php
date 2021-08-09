<?php
/**
 * Created by PhpStorm.
 * User: shafiq
 * Date: 9/29/19
 * Time: 9:09 PM
 */

namespace Terminalbd\KpiBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{


    /**
     * @Route("kpi", name="kpi_index")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function index() {
        return $this->render('@TerminalbdKpi/default/index.html.twig');
    }


}