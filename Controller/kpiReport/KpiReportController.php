<?php
namespace Terminalbd\KpiBundle\Controller\kpiReport;

use App\Entity\User;
use App\Repository\UserRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Form\TeamMemberSummaryFilterFormType;

/**
 * Class KpiReportController
 * @package Terminalbd\KpiBundle\Controller\kpiReport
 * @Route("/kpi/report")
 */
class KpiReportController extends AbstractController
{
    /**
     * @Route("/team-member-summary/{mode}", defaults={"mode" = null},methods={"GET"}, name="team_member_summary")
     */
    public function teamMemberSummary(Request $request, $mode, UserRepository $userRepository)
    {
//        $lineManager = $userRepository->getLineManager();
        $teamMemberSummary = [];
        $filterBy = [];
        $user = $this->getUser();
        $filterForm = $this->createForm(TeamMemberSummaryFilterFormType::class,null , ['user' => $this->getUser()]);
        $filterForm->handleRequest($request);
        if ($filterForm->isSubmitted()){
            $filterBy = $filterForm->getData();
            $StartDate = @strtotime($filterBy['startMonth'] . ' ' . $filterBy['year']);
            $StopDate = @strtotime($filterBy['endMonth'] . ' ' . $filterBy['year']);

            $months = $this->monthRange( $StartDate, $StopDate );

            $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $user);

            return $this->render('@TerminalbdKpi/employeeboard/report/teamMemberSummary.html.twig', [
                'filterBy' => $filterBy,
                'form' => $filterForm->createView(),
                'teamMemberSummary' => $teamMemberSummary,
                'months' => $months,
//            'activities' => $activities,
            ]);
        }
        $monthYear = [];
//        if ($filterBy != null){
//            $monthYear = explode(',', $filterBy);
//        }

        if ($mode == 'pdf'){

            // Configure Dompdf according to your needs
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial');

            // Instantiate Dompdf with our options
            $dompdf = new Dompdf($pdfOptions);

            // Retrieve the HTML generated in our twig file
            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/teamMemberSummary-pdf.html.twig', [
                'filterBy' => $filterBy,
                'teamMemberSummary' => $teamMemberSummary,
            ]);

            // Load HTML to Dompdf
            $dompdf->loadHtml($html);

            // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
            $dompdf->setPaper('legal', 'landscape');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser (force download)
            $fileName = $monthYear[0] . '-' . $monthYear[1] . '-' . $this->getUser()->getName() . 'team-member-summary' . time();
            $dompdf->stream( $fileName .  ".pdf", [
                "Attachment" => false
            ]);
            die();

        }
        return $this->render('@TerminalbdKpi/employeeboard/report/teamMemberSummary.html.twig', [
            'filterBy' => $filterBy,
            'form' => $filterForm->createView(),
            'teamMemberSummary' => $teamMemberSummary,
//            'activities' => $activities,
        ]);

    }

    /**
     * @Route("/all-team-member-summary/{mode}", defaults={"mode" = null},methods={"GET"}, name="all_team_member_summary")
     */
    public function allTeamMemberSummary(Request $request)
    {
        $filterBy = [];
        $user = $this->getUser();
        $filterForm = $this->createForm(TeamMemberSummaryFilterFormType::class,null , ['user' => $this->getUser()])->remove('employee');
        $filterForm->handleRequest($request);
        if ($filterForm->isSubmitted()){
            $filterBy = $filterForm->getData();
            $StartDate = @strtotime($filterBy['startMonth'] . ' ' . $filterBy['year']);
            $StopDate = @strtotime($filterBy['endMonth'] . ' ' . $filterBy['year']);

            $months = $this->monthRange( $StartDate, $StopDate );

            $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $user);

            return $this->render('@TerminalbdKpi/employeeboard/report/allTeamMemberSummary.html.twig', [
                'filterBy' => $filterBy,
                'form' => $filterForm->createView(),
                'teamMemberSummary' => $teamMemberSummary,
                'months' => $months,
//            'activities' => $activities,
            ]);
        }
        return $this->render('@TerminalbdKpi/employeeboard/report/allTeamMemberSummary.html.twig', [
            'form' => $filterForm->createView(),
        ]);
    }


    /**
     * Gets list of months between two dates
     * @param  int $start Unix timestamp
     * @param  int $end Unix timestamp
     * @return array
     */
    private function monthRange( $start, $end ){

        $current = $start;
        $data = [];
        while( $current < $end ){

//            $next = @date('Y-M-01', $current) . "+1 month";
            $next = @date('Y-M-01', $current);
            $current = @strtotime($next);

            $data[] = date('F', $current);

            $next = @date('Y-M-01', $current) . "+1 month";
            $current = @strtotime($next);
        }
        return $data;
    }
    
    
    
    
    
    
    
    
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