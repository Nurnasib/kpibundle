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
use Terminalbd\KpiBundle\Entity\EmployeeBoardSubAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeDistrictHistory;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Form\DistrictHistorySearchFilterFormType;
use Terminalbd\KpiBundle\Form\SalesReportFilterFormType;
use Terminalbd\KpiBundle\Form\TeamMemberSummaryFilterFormType;

/**
 * Class KpiReportController
 * @package Terminalbd\KpiBundle\Controller\kpiReport
 * @Route("/kpi/report")
 * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_LINE_MANAGER')")
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
        set_time_limit(0);
        ini_set('memory_limit', '5000M');
        
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

        $months = $this->monthRange($StartDate, $StopDate);

        $activitiesName = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getActivities();
        $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $this->getUser());

        $filterForm = $this->createForm(TeamMemberSummaryFilterFormType::class, null, ['user' => $this->getUser(), 'lineManagers' => $lineManagers])->remove('kpiFormat');
        $filterForm->handleRequest($request);
        if ($filterForm->isSubmitted()) {
            $filterBy = $filterForm->getData();
            $StartDate = @strtotime($filterBy['startMonth'] . ' ' . $filterBy['year']);
            $StopDate = @strtotime($filterBy['endMonth'] . ' ' . $filterBy['year']);
            $months = $this->monthRange($StartDate, $StopDate);
            $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $this->getUser());

            if ($request->query->has('pdf')) {

                // Configure Dompdf according to your needs
                $pdfOptions = new Options();
                $pdfOptions->set('defaultFont', 'Arial, sans-serif');

                // Instantiate Dompdf with our options
                $dompdf = new Dompdf($pdfOptions);

                // Retrieve the HTML generated in our twig file
                $html = $this->renderView('@TerminalbdKpi/employeeboard/report/teamMemberSummary-pdf.html.twig', [
                    'filterBy' => $filterBy,
                    'teamMemberSummary' => $teamMemberSummary,
                    'activitiesName' => $activitiesName,
                ]);

                // Load HTML to Dompdf
                $dompdf->loadHtml($html);

                // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
                $dompdf->setPaper('A4', 'landscape');

                // Render the HTML as PDF
                $dompdf->render();

                // Output the generated PDF to Browser (force download)
                $fileName = $request->get('_route') . '-' . time();
                $dompdf->stream($fileName . ".pdf", [
                    "Attachment" => true
                ]);
                die();

            }
            /*            elseif ($request->query->has('excel')){
            
                            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/teamMemberSummary-excel.html.twig', [
                                'filterBy' => $filterBy,
                                'teamMemberSummary' => $teamMemberSummary,
                                'activitiesName' => $activitiesName,
                            ]);
                            $fileName = $request->get('_route').'_'.time().'.xls';
                            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
                            header("Content-Disposition: attachement; filename=$fileName");
                            echo $html;
                            die();
                        }*/
        }

        return $this->render('@TerminalbdKpi/employeeboard/report/teamMemberSummary.html.twig', [
            'filterBy' => $filterBy,
            'form' => $filterForm->createView(),
            'months' => $months,
            'teamMemberSummary' => $teamMemberSummary,
            'activitiesName' => $activitiesName,
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
        set_time_limit(0);
        ini_set('memory_limit', '5000M');

        $lineManagers = $userRepository->getLineManager();

        $filterBy = [
            "kpiFormat" => null,
            "startMonth" => date('F'),
            "endMonth" => date('F'),
            "year" => (int)date('Y'),
        ];
        $StartDate = @strtotime(date('F') . ' ' . (int)date('Y'));
        $StopDate = @strtotime(date('F') . ' ' . (int)date('Y'));

        $months = $this->monthRange($StartDate, $StopDate);

        $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $this->getUser());
        $activitiesName = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getActivities();

        $filterForm = $this->createForm(TeamMemberSummaryFilterFormType::class, null, ['user' => $this->getUser(), 'lineManagers' => $lineManagers])->remove('lineManager')->remove('employee');
        $filterForm->handleRequest($request);
        if ($filterForm->isSubmitted()) {
            $filterBy = $filterForm->getData();

            $filterBy['kpiFormat'] = $filterBy['kpiFormat'] ? $filterBy['kpiFormat']->getId() : null;
            $StartDate = @strtotime($filterBy['startMonth'] . ' ' . $filterBy['year']);
            $StopDate = @strtotime($filterBy['endMonth'] . ' ' . $filterBy['year']);

            $months = $this->monthRange($StartDate, $StopDate);
            $teamMemberSummary = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getTeamMemberSummary($filterBy, $months, $this->getUser());
            if ($request->query->has('pdf')) {

                // Configure Dompdf according to your needs
                $pdfOptions = new Options();
                $pdfOptions->set('defaultFont', 'Arial, sans-serif');

                // Instantiate Dompdf with our options
                $dompdf = new Dompdf($pdfOptions);

                // Retrieve the HTML generated in our twig file
                $html = $this->renderView('@TerminalbdKpi/employeeboard/report/allTeamMemberSummary-pdf.html.twig', [
                    'filterBy' => $filterBy,
                    'teamMemberSummary' => $teamMemberSummary,
                    'activitiesName' => $activitiesName,
                ]);

                // Load HTML to Dompdf
                $dompdf->loadHtml($html);

                // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
                $dompdf->setPaper('A4', 'landscape');

                // Render the HTML as PDF
                $dompdf->render();

                // Output the generated PDF to Browser (force download)
                $fileName = $request->get('_route') . '-' . time();
                $dompdf->stream($fileName . ".pdf", [
                    "Attachment" => true
                ]);
                die();
            }
            /*            elseif ($request->query->has('excel')){
                            
                            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/allTeamMemberSummary-excel.html.twig', [
                                'filterBy' => $filterBy,
                                'teamMemberSummary' => $teamMemberSummary,
                                'activitiesName' => $activitiesName,
                            ]);
                            $fileName = $request->get('_route').'_'.time().'.xls';
                            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
                            header("Content-Disposition: attachment; filename=$fileName");
                            echo $html;
                            die();
            
                        }*/
        }


        return $this->render('@TerminalbdKpi/employeeboard/report/allTeamMemberSummary.html.twig', [
            'filterBy' => $filterBy,
            'form' => $filterForm->createView(),
            'teamMemberSummary' => $teamMemberSummary,
            'months' => $months,
            'activitiesName' => $activitiesName,
        ]);
    }

    /**
     * @param Request $request
     * @Route("/district-history", name="district_history")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function districtHistory(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '5000M');
        $filterBy = [
            'month' => date('F'),
            'year' => date('Y'),
            'user' => $this->getUser(),
            'employee' => null,
        ];

//        $form = $this->createForm(DistrictHistorySearchFilterFormType::class, null, ['user' => $this->getUser()]);
        $form = $this->createForm(DistrictHistorySearchFilterFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $filterBy = $form->getData();
            $filterBy['user'] = $this->getUser();

            $data = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->getDistrictHistory($filterBy);
            if ($request->query->has('pdf')) {

                // Configure Dompdf according to your needs
                $pdfOptions = new Options();
                $pdfOptions->set('defaultFont', 'Arial, sans-serif');

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
                $dompdf->setPaper('A4', 'portrait');

                // Render the HTML as PDF
                $dompdf->render();

                // Output the generated PDF to Browser (force download)
                $fileName = $request->get('_route') . '-' . time();
                $dompdf->stream($fileName . ".pdf", [
                    "Attachment" => true
                ]);
                die();
            }
        }
        $data = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->getDistrictHistory($filterBy);
        return $this->render('@TerminalbdKpi/employeeboard/report/districtHistory.html.twig', [
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
        set_time_limit(0);
        ini_set('memory_limit', '5000M');
        
        $user = $this->getUser();

        $employees = [];
        $lineManagers = [];

        $selectedYear = $request->query->get('year');
        $selectedLineManager = $request->query->get('lineManager');

        if (!$selectedYear) {
            $selectedYear = date('Y');
        }

        if (in_array('ROLE_KPI_ADMIN', $user->getRoles())) {

            $lineManagers = $this->getDoctrine()->getRepository(User::class)->getLineManagers();
            $lineManagersId = [];
            if ($selectedLineManager) {
                $lineManagersId[] = $selectedLineManager;
            } else {
                foreach ($lineManagers as $lineManager) {
                    $lineManagersId[] = $lineManager['userId'];
                }
            }
            $employees = $this->getDoctrine()->getRepository(User::class)->getLineManagerTeamMember($lineManagersId, []);
        }
        
        $kpiRecords = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getMonthlyStatus($selectedYear, $selectedLineManager, $user);

        return $this->render('@TerminalbdKpi/employeeboard/report/monthlyStatus.html.twig', [
            'kpiRecords' => $kpiRecords,
            'year' => $selectedYear,
            'lineManagers' => $lineManagers,
            'employees' => $employees,
            'selectedLineManager' => $selectedLineManager,
        ]);
    }

    /**
     * @Route("/{mode}/sales-percentage", name="sales_percentage")
     * @param Request $request
     * @param \Symfony\Component\HttpFoundation\Response
     */
    public function salesPercentage(Request $request, $mode)
    {
        set_time_limit(0);
        ini_set('memory_limit', '5000M');

        $startDate = @strtotime(date('F') . ' ' . (int)date('Y'));
        $endDate = @strtotime(date('F') . ' ' . (int)date('Y'));

        $months = $this->monthRange($startDate, $endDate);

        $filterBy = [
            'loggedUser' => $this->getUser(),
            'employee' => null,
            'months' => $months,
            'year' => (int)date('Y'),
            'salesMode' => $mode,
        ];

        $filterForm = $this->createForm(SalesReportFilterFormType::class, null, ['user' => $this->getUser()]);
        $filterForm->handleRequest($request);

        if ($filterForm->isSubmitted()){
            $startDate = @strtotime($filterForm->get('startMonth')->getData() . ' ' . $filterForm->get('year')->getData());
            $endDate = @strtotime($filterForm->get('endMonth')->getData() . ' ' . $filterForm->get('year')->getData());
            $months = $this->monthRange($startDate, $endDate);

            $filterBy['employee'] = $filterForm->get('employee')->getData();
            $filterBy['year'] = (int)$filterForm->get('year')->getData();
            $filterBy['months'] = $this->monthRange($startDate, $endDate);

        }

        $data = $this->getDoctrine()->getRepository(EmployeeBoardSubAttribute::class)->getSalesTargetAchievementReport($filterBy);

        if ($request->query->get('pdf')){

            // Configure Dompdf according to your needs
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial, sans-serif');

            // Instantiate Dompdf with our options
            $dompdf = new Dompdf($pdfOptions);

            // Retrieve the HTML generated in our twig file
            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/salesPercentage-pdf.html.twig', [
                'data' => $data,
                'filterBy' => $filterBy,
                'mode' => $mode,

            ]);

            // Load HTML to Dompdf
            $dompdf->loadHtml($html);

            // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
            $dompdf->setPaper('A3', 'landscape');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser (force download)
            $fileName = $request->get('_route') . '-' . time();
            $dompdf->stream($fileName . ".pdf", [
                "Attachment" => false
            ]);
            die();
        }

        return $this->render('@TerminalbdKpi/employeeboard/report/salesPercentage.html.twig', [
            'filterBy' => $filterBy,
            'form' => $filterForm->createView(),
            'data' => $data,
            'mode' => $mode,
        ]);
    }


    /**
     * @Route("/{mode}/marks-percentage", name="marks_percentage")
     * @param Request $request
     * @param \Symfony\Component\HttpFoundation\Response
     */
    public function marksPercentage(Request $request, $mode)
    {
        set_time_limit(0);
        ini_set('memory_limit', '5000M');

        $startDate = @strtotime(date('F') . ' ' . (int)date('Y'));
        $endDate = @strtotime(date('F') . ' ' . (int)date('Y'));

        $months = $this->monthRange($startDate, $endDate);

        $filterBy = [
            'loggedUser' => $this->getUser(),
            'employee' => null,
            'months' => $months,
            'year' => (int)date('Y'),
            'salesMode' => $mode,
        ];

        $filterForm = $this->createForm(SalesReportFilterFormType::class, null, ['user' => $this->getUser()]);
        $filterForm->handleRequest($request);

        if ($filterForm->isSubmitted()){
            $startDate = @strtotime($filterForm->get('startMonth')->getData() . ' ' . $filterForm->get('year')->getData());
            $endDate = @strtotime($filterForm->get('endMonth')->getData() . ' ' . $filterForm->get('year')->getData());
            $months = $this->monthRange($startDate, $endDate);

            $filterBy['employee'] = $filterForm->get('employee')->getData();
            $filterBy['year'] = (int)$filterForm->get('year')->getData();
            $filterBy['months'] = $this->monthRange($startDate, $endDate);

        }

        $data = $this->getDoctrine()->getRepository(EmployeeBoardSubAttribute::class)->getSalesTargetAchievementMarksReport($filterBy);

        if ($request->query->get('pdf')){

            // Configure Dompdf according to your needs
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial, sans-serif');

            // Instantiate Dompdf with our options
            $dompdf = new Dompdf($pdfOptions);

            // Retrieve the HTML generated in our twig file
            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/salesMarksPercentage-pdf.html.twig', [
                'data' => $data,
                'filterBy' => $filterBy,
                'mode' => $mode,

            ]);

            // Load HTML to Dompdf
            $dompdf->loadHtml($html);

            // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
            $dompdf->setPaper('A3', 'landscape');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser (force download)
            $fileName = $request->get('_route') . '-' . time();
            $dompdf->stream($fileName . ".pdf", [
                "Attachment" => false
            ]);
            die();
        }

        return $this->render('@TerminalbdKpi/employeeboard/report/salesMarksPercentage.html.twig', [
            'filterBy' => $filterBy,
            'form' => $filterForm->createView(),
            'data' => $data,
            'mode' => $mode,
        ]);
    }

    /**
     * @Route("/sales-growth-marks-summary", name="sales_growth_marks_summary")
     * @param Request $request
     * @param \Symfony\Component\HttpFoundation\Response
     */
    public function salesGrowthMarksSummary(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '5000M');

        $startDate = @strtotime(date('F') . ' ' . (int)date('Y'));
        $endDate = @strtotime(date('F') . ' ' . (int)date('Y'));

        $months = $this->monthRange($startDate, $endDate);

        $filterBy = [
            'loggedUser' => $this->getUser(),
            'employee' => null,
            'months' => $months,
            'year' => (int)date('Y'),
            'slugs' => ['sales', 'sales-growth-always-consider-among-same-period'],
        ];

        $filterForm = $this->createForm(SalesReportFilterFormType::class, null, ['user' => $this->getUser()]);
        $filterForm->handleRequest($request);

        if ($filterForm->isSubmitted()){
            $startDate = @strtotime($filterForm->get('startMonth')->getData() . ' ' . $filterForm->get('year')->getData());
            $endDate = @strtotime($filterForm->get('endMonth')->getData() . ' ' . $filterForm->get('year')->getData());
            $months = $this->monthRange($startDate, $endDate);

            $filterBy['employee'] = $filterForm->get('employee')->getData();
            $filterBy['year'] = (int)$filterForm->get('year')->getData();
            $filterBy['months'] = $this->monthRange($startDate, $endDate);

        }


        $data = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getSalesGrowthReport($filterBy);

        if ($request->query->get('pdf')){

            // Configure Dompdf according to your needs
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial, sans-serif');

            // Instantiate Dompdf with our options
            $dompdf = new Dompdf($pdfOptions);

            // Retrieve the HTML generated in our twig file
            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/salesGrowthMarksSummary-pdf.html.twig', [
                'data' => $data,
                'filterBy' => $filterBy,

            ]);

            // Load HTML to Dompdf
            $dompdf->loadHtml($html);

            // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
            $dompdf->setPaper('A3', 'landscape');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser (force download)
            $fileName = $request->get('_route') . '-' . time();
            $dompdf->stream($fileName . ".pdf", [
                "Attachment" => false
            ]);
            die();
        }

        return $this->render('@TerminalbdKpi/employeeboard/report/salesGrowthMarksSummary.html.twig', [
            'filterBy' => $filterBy,
            'form' => $filterForm->createView(),
            'data' => $data,
        ]);
    }
    
    /**
     * Gets list of months between two dates
     * @param int $start Unix timestamp
     * @param int $end Unix timestamp
     * @return array
     */
    private function monthRange($start, $end)
    {

        $current = $start;
        $data = [];
        while ($current <= $end) {

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