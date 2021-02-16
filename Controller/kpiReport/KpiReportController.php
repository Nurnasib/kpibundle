<?php
namespace Terminalbd\KpiBundle\Controller\kpiReport;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;

class KpiReportController extends AbstractController
{
    /**
     * @Route("kpi/kpi-report", name="kpi_report")
     */
    public function generateKpiReport()
    {
        $marksDistributions = [];
        $user = $this->getUser();
        $employee = $this->getDoctrine()->getRepository(EmployeeSetup::class)->getEmployeeList($user);
//        dd($employee);
        $products = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->getProductTarget();
        $attributesAndMarks = $this->getDoctrine()->getRepository(MarkChart::class)->getAttributes();

        dd($products);
        foreach ($products as $key => $product){
            dd($product);
            $agentOrder = $this->getDoctrine()->getRepository(AgentOrder::class)->getCompletedAmount($key);

        }

        /*        foreach ($attributesAndMarks as $attributesAndMark) {
        //            echo $attributesAndMark['attributesName'] . '<br>';
                    $marksDistributions[$attributesAndMark['attributesName']] = $this->getDoctrine()->getRepository(MarkChart::class)->getMarkDistribution($attributesAndMark['attributesName']);
                }
                foreach ($marksDistributions as $marksDistribution) {
                    $attributesAndMarks['markDistribution'][] = $marksDistribution;
                }
                $marksDistributions = $this->getDoctrine()->getRepository(MarkChart::class)->getMarkDistribution('Poultry');
        //        dd($attributesAndMarks);*/

        return $this->render('@TerminalbdKpi/kpiReport/index.html.twig',[
            'attributesAndMarks'  => $attributesAndMarks
        ]);
    }
}