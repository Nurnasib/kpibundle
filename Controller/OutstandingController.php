<?php


namespace Terminalbd\KpiBundle\Controller;


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
    /**
     * @Route("/", methods={"GET"}, name="kpi_outstanding")
     */
    public function docSalesCollection(Request $request)
    {
        $requestMonthYear = $request->get('monthYear');

        $monthYear = array('month'=>Date('F', strtotime(date('F') . " last month")),'year'=>Date('Y', strtotime(date('Y') . " last year")));

        if($requestMonthYear){
            $explode= explode(',',$requestMonthYear);
            $monthYear = array('month'=>$explode[0],'year'=>$explode[1]);
        }

        $entities = $this->getDoctrine()->getRepository(AgentOutstanding::class)->getMonthYearOutstanding($monthYear);

        return $this->render('@TerminalbdKpi/outstanding/outstanding.html.twig', [
            'entities' => $entities,
            'monthYear' => $monthYear,
            'selectedMonthYear'=>$requestMonthYear,
        ]);

    }
}