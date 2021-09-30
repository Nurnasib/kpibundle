<?php
namespace Terminalbd\KpiBundle\Controller\kpiReport;

use App\Entity\User;
use App\Repository\UserRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeDistrictHistory;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Form\DistrictHistorySearchFilterFormType;
use Terminalbd\KpiBundle\Form\TeamMemberSummaryFilterFormType;

/**
 * Class KpiReportController
 * @package Terminalbd\KpiBundle\Controller\kpiReport
 * @Route("/kpi/report")
 * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_KPI_LINE_MANAGER')")
 */
class KpiReportController extends AbstractController
{
    /**
     * @Route("/team-member-summary", name="team_member_summary")
     * @param Request $request
     * @param UserRepository $userRepository
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function teamMemberSummary(Request $request, UserRepository $userRepository)
    {
        $lineManagers = $userRepository->getLineManager();
        $teamMemberSummary = [];
        $filterBy = [
            'lineManager' => array_values($lineManagers)[0],
            'startMonth' => date('F'),
            'endMonth' => date('F'),
            'year' => (int)date('Y'),
        ];
        $StartDate = @strtotime(date('F') . ' ' . (int)date('Y'));
        $StopDate = @strtotime(date('F') . ' ' . (int)date('Y'));

        $user = $this->getUser();
        $filterForm = $this->createForm(TeamMemberSummaryFilterFormType::class,null , ['user' => $this->getUser(), 'lineManagers' => $lineManagers])->remove('kpiFormat');
        $filterForm->handleRequest($request);
        if ($filterForm->isSubmitted()){
            $filterBy = $filterForm->getData();
            $StartDate = @strtotime($filterBy['startMonth'] . ' ' . $filterBy['year']);
            $StopDate = @strtotime($filterBy['endMonth'] . ' ' . $filterBy['year']);
            $months = $this->monthRange( $StartDate, $StopDate );
            $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $user);

            if ($request->query->has('pdf')){
                
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
                $fileName = $request->get('_route') . '-' . time();
                $dompdf->stream( $fileName .  ".pdf", [
                    "Attachment" => true
                ]);
                die();

            }elseif ($request->query->has('excel')){

                $html = $this->renderView('@TerminalbdKpi/employeeboard/report/teamMemberSummary-excel.html.twig', [
                    'filterBy' => $filterBy,
                    'teamMemberSummary' => $teamMemberSummary,
                ]);
                $fileName = $request->get('_route').'_'.time().'.xls';
                header("Content-Type: application/vnd.ms-excel; charset=utf-8");
                header("Content-Disposition: attachement; filename=$fileName");
                echo $html;
                die();
            }
        }

        $months = $this->monthRange( $StartDate, $StopDate );
        $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $user);

        return $this->render('@TerminalbdKpi/employeeboard/report/teamMemberSummary.html.twig', [
            'filterBy' => $filterBy,
            'form' => $filterForm->createView(),
            'months' => $months,
            'teamMemberSummary' => $teamMemberSummary,
        ]);

    }

    /**
     * @Route("/all-team-member-summary", name="all_team_member_summary")
     * @param Request $request
     * @param UserRepository $userRepository
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function allTeamMemberSummary(Request $request, UserRepository $userRepository)
    {
        
        $lineManagers = $userRepository->getLineManager();

        $filterBy = [
            "kpiFormat" => null,
            "startMonth" => date('F'),
            "endMonth" => date('F'),
            "year" => (int)date('Y'),
        ];
        $StartDate = @strtotime(date('F') . ' ' . (int)date('Y'));
        $StopDate = @strtotime(date('F') . ' ' . (int)date('Y'));

        $user = $this->getUser();
        $filterForm = $this->createForm(TeamMemberSummaryFilterFormType::class,null , ['user' => $this->getUser(), 'lineManagers' => $lineManagers])->remove('lineManager')->remove('employee');
        $filterForm->handleRequest($request);
        if ($filterForm->isSubmitted()){
            $filterBy = $filterForm->getData();

            $filterBy['kpiFormat'] = $filterBy['kpiFormat'] ? $filterBy['kpiFormat']->getId(): null;
            $StartDate = @strtotime($filterBy['startMonth'] . ' ' . $filterBy['year']);
            $StopDate = @strtotime($filterBy['endMonth'] . ' ' . $filterBy['year']);

            $months = $this->monthRange( $StartDate, $StopDate );
            $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $user);

            if ($request->query->has('pdf')){
                
                // Configure Dompdf according to your needs
                $pdfOptions = new Options();
                $pdfOptions->set('defaultFont', 'Arial');

                // Instantiate Dompdf with our options
                $dompdf = new Dompdf($pdfOptions);

                // Retrieve the HTML generated in our twig file
                $html = $this->renderView('@TerminalbdKpi/employeeboard/report/allTeamMemberSummary-pdf.html.twig', [
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
                $fileName = $request->get('_route') . '-' . time();
                $dompdf->stream( $fileName .  ".pdf", [
                    "Attachment" => true
                ]);
                die();
            }elseif ($request->query->has('excel')){
                
                $html = $this->renderView('@TerminalbdKpi/employeeboard/report/allTeamMemberSummary-excel.html.twig', [
                    'filterBy' => $filterBy,
                    'teamMemberSummary' => $teamMemberSummary,
                ]);
                $fileName = $request->get('_route').'_'.time().'.xls';
                header("Content-Type: application/vnd.ms-excel; charset=utf-8");
                header("Content-Disposition: attachement; filename=$fileName");
                echo $html;
                die();

            }
        }
        $months = $this->monthRange( $StartDate, $StopDate );

        $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $user);
        return $this->render('@TerminalbdKpi/employeeboard/report/allTeamMemberSummary.html.twig', [
            'filterBy' => $filterBy,
            'form' => $filterForm->createView(),
            'teamMemberSummary' => $teamMemberSummary,
            'months' => $months,
        ]);
    }

    /**
     * @param Request $request
     * @Route("/district-history", name="district_history")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function districtHistory(Request $request)
    {
        $filterBy = [
            'month' => date('F'),
            'year' => date('Y'),
            'user' => $this->getUser(),
        ];
        $form = $this->createForm(DistrictHistorySearchFilterFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()){
            $filterBy = $form->getData();
            $filterBy['user'] = $this->getUser();

            $data = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->getDistrictHistory($filterBy);
            if ($request->query->has('pdf')){

                // Configure Dompdf according to your needs
                $pdfOptions = new Options();
                $pdfOptions->set('defaultFont', 'Arial');

                // Instantiate Dompdf with our options
                $dompdf = new Dompdf($pdfOptions);

                // Retrieve the HTML generated in our twig file
                $html = $this->renderView('@TerminalbdKpi/employeeboard/report/districtHistory-pdf.html.twig', [
                    'data' => $data,
                    'filterBy' => $filterBy
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
            }
        }
        $data = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->getDistrictHistory($filterBy);
        return $this->render('@TerminalbdKpi/employeeboard/report/districtHistory.html.twig',[
            'form' => $form->createView(),
            'data' => $data
        ]);
    }

    /**
     * @Route("/monthly-generated-kpi-status", name="monthly_generated_kpi_status")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function monthlyGeneratedKpiStatus(Request $request)
    {
        $user = $this->getUser();
        $members = [];
        $membersId = [];
        $requestYear = $request->query->get('year');
        if(!$requestYear){
            $requestYear = date('Y');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles())){
            $employees = $this->getDoctrine()->getRepository(User::class)->getKpiLineManagers();
        }else{
            $employees = $this->getDoctrine()->getRepository(User::class)->findBy(['userMode' => 'KPI', 'lineManager' => $user]);
        }

        foreach ($employees as $employee) {
            $members[$employee->getUserId()] = [
                'id' => $employee->getId(),
                'userId' => $employee->getUserId(),
                'name' => $employee->getName(),
                'username' => $employee->getUsername(),
            ];
        }
        foreach ($members as $member) {
            $membersId[] = $member['id'];
        }


        $kpiRecords = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getMonthlyStatus($requestYear, $membersId);
        return $this->render('@TerminalbdKpi/employeeboard/report/monthlyStatus.html.twig',[
            'kpiRecords' => $kpiRecords,
            'year' => $requestYear,
            'members' => $members,
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
        while( $current <= $end ){

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
        return false;
/*        $marksDistributions = [];
        $user = $this->getUser();
        $employee = $this->getDoctrine()->getRepository(EmployeeSetup::class)->getEmployeeList($user);
        $products = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->getProductTarget();
        $attributesAndMarks = $this->getDoctrine()->getRepository(MarkChart::class)->getAttributes();

        dd($products);
        foreach ($products as $key => $product){
            dd($product);
            $agentOrder = $this->getDoctrine()->getRepository(AgentOrder::class)->getCompletedAmount($key);

        }*/

        /*        foreach ($attributesAndMarks as $attributesAndMark) {
        //            echo $attributesAndMark['attributesName'] . '<br>';
                    $marksDistributions[$attributesAndMark['attributesName']] = $this->getDoctrine()->getRepository(MarkChart::class)->getMarkDistribution($attributesAndMark['attributesName']);
                }
                foreach ($marksDistributions as $marksDistribution) {
                    $attributesAndMarks['markDistribution'][] = $marksDistribution;
                }
                $marksDistributions = $this->getDoctrine()->getRepository(MarkChart::class)->getMarkDistribution('Poultry');
        //        dd($attributesAndMarks);*/

/*        return $this->render('@TerminalbdKpi/kpiReport/bank-satement.html.twig',[
            'attributesAndMarks'  => $attributesAndMarks
        ]);*/
    }
}