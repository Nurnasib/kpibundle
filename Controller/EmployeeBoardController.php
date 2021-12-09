<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Terminalbd\KpiBundle\Controller;

use App\Entity\Core\Agent;
use App\Entity\User;
use App\Repository\UserRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Mpdf\Tag\Th;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Terminalbd\KpiBundle\Entity\AgentCategory;
use Terminalbd\KpiBundle\Entity\AgentDocSaleCollection;
use Terminalbd\KpiBundle\Entity\AgentDocSaleCollectionForCustomFormat;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\AgentOutstanding;
use Terminalbd\KpiBundle\Entity\AgentOutstandingForCustomFormat;
use Terminalbd\KpiBundle\Entity\DistrictOrder;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeBoardSubAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeDistrictHistory;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Entity\SetupMatrix;
use Terminalbd\KpiBundle\Form\EmployeeBoardFormType;
use Terminalbd\KpiBundle\Form\KpiBoardSearchFilterFormType;


/**
 * @Route("/kpi/employee-board")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class EmployeeBoardController extends AbstractController
{
    private function paginate(Request $request ,$entities)
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
     * @Route("/", methods={"GET"}, name="kpi_employee_board", options={"expose"=true})
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */

    public function index(Request $request, UserRepository $userRepository): Response
    {
        $lineManagers = $userRepository->getLineManager();
        $user = $this->getUser();
        $entities = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getEmployeeBoardList($user);
        $form = $this->createForm(KpiBoardSearchFilterFormType::class,null, ['user' => $user, 'lineManagers' => $lineManagers]);
        $form->handleRequest($request);
        if ($form->isSubmitted()){
            $filterBy = $form->getData();
            if (count(array_keys($filterBy, null)) == count($filterBy)){
                $entities = [];
            }else{
                $entities = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getEmployeeBoardListFilterBy($user, $filterBy);
            }
        }
        $data = $this->paginate($request, $entities);
        return $this->render('@TerminalbdKpi/employeeboard/index.html.twig',[
            'entities' => $data,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/new/{format}", methods={"GET", "POST"}, name="kpi_board_new", defaults={"format" = null})
     * @param Request $request
     * @param $format
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */

    public function new(Request $request, $format): Response
    {
        $parameters = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(['level' => 1,'status' => 1]);
        $board = new EmployeeBoard();

        $form = $this->createForm(EmployeeBoardFormType::class , $board,['user'=>$this->getUser(), 'format' => $format])
            ->add('monthYear', TextType::class,['attr'=>['class'=>'inputMonth','autocomplete'=>'off'],'mapped'=>false])
            ->add('SaveAndCreate', SubmitType::class);
        $form->handleRequest($request);
        $data = $request->request->all();
        if ($form->isSubmitted() && $form->isValid()) {
//            $employee = $data['employee_board_form']['employee'];
            if (isset($data['self_kpi']) && $data['self_kpi'] === 'on'){
                $emp = $this->getUser();
            }else{
                $emp = $form['employee']->getData();
            }
            $monthYear = explode(',', $data['employee_board_form']['monthYear']);
            $month = $monthYear[0];
            $year = $monthYear[1];

            $totalTeamMembers = $this->getDoctrine()->getRepository(User::class)->findBy(['lineManager' => $emp]);
            $generatedTeamMember = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getGeneratedTeamMember($totalTeamMembers, $month, $year);
            if (count($totalTeamMembers) != count($generatedTeamMember)){
                $this->addFlash('error',  "{$emp->getName()} has not generated all team members KPI of {$month}, {$year} under him. So you won't be able to generate KPI of {$emp->getName()} for this month.");
                if ($format == 'custom-format'){
                    return $this->redirectToRoute('kpi_board_new',['format' => $format]);
                }
                return $this->redirectToRoute('kpi_board_new');
            }

            $exist = $this->getDoctrine()->getRepository(EmployeeBoard::class)->findOneBy(
                array('employee' => $emp,'month'=>$month, 'year'=>$year, 'reportMode'=>$emp->getReportMode())
            );

            if (empty($exist)) {
                $employeeDistrictHistory = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->findOneBy(['employee' => $emp, 'year' => $year, 'month' => $month]);
                $districts = $employeeDistrictHistory ? $employeeDistrictHistory->getDistrict() : '';

                if (!$districts){
                    $this->addFlash('error', 'Districts not found!');
                    return $this->redirectToRoute('kpi_board_new', [$format]);
                }

                $em = $this->getDoctrine()->getManager();
                $board->setYear($year);
                $board->setMonth($month);
                $board->setProcess('created');
                $board->setReportMode($emp->getReportMode());
                $board->setEmployee($emp);
                $board->setCreatedBy($this->getUser());
                $board->setDistrict($districts);
                $board->setCreated(new \DateTime());
                $board->setUpdated(new \DateTime());

                $em->persist($board);
                $em->flush();

                if ($format == 'custom-format'){
                    $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->insertMarkDistributionForCustomFormat($board,$parameters);
                    return $this->redirectToRoute('kpi_employee_board_edit_custom_format',array('id' => $board->getId()));
                }else{
                    $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->insertMarkDistribution($board, $parameters);
                    return $this->redirectToRoute('kpi_employee_board_edit',array('id' => $board->getId()));
                }
            }else{
                if ($format == 'custom-format'){
                    return $this->redirectToRoute('kpi_employee_board_edit_custom_format',array('id' => $exist->getId()));
                }else{
                    return $this->redirectToRoute('kpi_employee_board_edit',array('id' => $exist->getId()));
                }
            }
        }
        return $this->render('@TerminalbdKpi/employeeboard/create.html.twig', [
            'setupEntity' => $board,
            'form' => $form->createView(),
            'format' => $format
        ]);
    }


    /**
     * @Route("/{setup}/kpi-generate/", methods={"GET", "POST"}, name="kpi_employee_board_generate")
     * @param Request $request
     * @param EmployeeSetup $setup
     * @return Response
     */
    public function generate(Request $request, EmployeeSetup $setup): Response
    {
        $month = "January";
        $year = 2020;
        $entities = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(['level' => 1,'status' => 1]);
        $em = $this->getDoctrine()->getManager();
        $exist = $this->getDoctrine()->getRepository(EmployeeBoard::class)->findOneBy(array('employeeSetup' => $setup,'month'=>$month,'year'=>$year));
        if(empty($exist)){
            $entity = new EmployeeBoard();
            $entity->setEmployeeSetup($setup);
            $entity->setMonth((string)$month);
            $entity->setYear((string)$year);
            $em->persist($entity);
            $em->flush();
            $em->getRepository(EmployeeBoardAttribute::class)->insertMarkDistribution($setup,$entity,$entities);
            return $this->redirectToRoute('kpi_employee_board_edit',array('id'=>$entity->getId()));
        }else{
            return $this->redirectToRoute('kpi_employee_board_edit',array('id'=>$exist->getId()));
        }

    }


    /**
     * Displays a form to edit an existing Post entity.
     *
     * @Route("/{id}/edit/", methods={"GET", "POST"}, name="kpi_employee_board_edit")
     * @param Request $request
     * @param EmployeeBoard $entity
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */

    public function edit(Request $request, EmployeeBoard $entity): Response
    {
        if ($entity->getApprovedBy()){
            return $this->redirectToRoute('kpi_employee_board');
        }
        $em = $this->getDoctrine()->getManager();
        $parameters = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(['level' => 1,'status' => 1]);

        $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->insertMarkDistribution($entity,$parameters);

        $boardAttributes = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->EmployeeBoardMarks($entity);
        $arrayData=[];
        /* @var EmployeeBoardAttribute $boardAttribute*/
        foreach ($boardAttributes as $boardAttribute){
            $arrayData[$boardAttribute->getParameter()->getId()][$boardAttribute->getActivity()->getId()][]=$boardAttribute;
        }

        $districtsHistory = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->findOneBy(['employee' => $entity->getEmployee(), 'month' => $entity->getMonth(), 'year' => $entity->getYear()]);

        $entity->setDistrict($districtsHistory ? $districtsHistory->getDistrict() : null);
        $em->persist($entity);
        $em->flush();
        return $this->render('@TerminalbdKpi/employeeboard/new.html.twig', [
            'board' => $entity,
            'arrayData' => $arrayData,
        ]);
    }

    /**
     * Displays a form to edit an existing Post entity.
     *
     * @Route("/{id}/edit/custom-format", methods={"GET", "POST"}, name="kpi_employee_board_edit_custom_format")
     * @param Request $request
     * @param EmployeeBoard $board
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */

    public function editCustomFormat(Request $request, EmployeeBoard $board): Response
    {
        if ($board->getApprovedBy()){
            return $this->redirectToRoute('kpi_employee_board');
        }
        $feedAndGrowth = $this->getDoctrine()->getRepository(EmployeeBoardSubAttribute::class)->findBy(['employeeBoard' => $board]);
        $feedAndGrowthArray = [];
        foreach ($feedAndGrowth as $item) {
            $feedAndGrowthArray[$item->getMarkDistribution()->getId()]['targetQuantity'] = $item->getTargetQuantity();
            $feedAndGrowthArray[$item->getMarkDistribution()->getId()]['salesQuantity'] = $item->getSalesQuantity();
        }
        $outstanding = $this->getDoctrine()->getRepository(AgentOutstandingForCustomFormat::class)->findOneBy(['employeeBoard' => $board]);
        $docSale = $this->getDoctrine()->getRepository(AgentDocSaleCollectionForCustomFormat::class)->findOneBy(['employeeBoard' => $board]);
//        $customerDevelopment = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'activity']);
//        $skills = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findBy(['employeeBoard' => $board]);

        $boardAttributes = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->EmployeeBoardMarks($board);
        $arrayData = [];
        /* @var EmployeeBoardAttribute $boardAttribute*/
        foreach ($boardAttributes as $boardAttribute){
            $arrayData[$boardAttribute->getParameter()->getId()][$boardAttribute->getActivity()->getId()][]=$boardAttribute;
        }
/*        $districts = null;
        foreach ($board->getEmployee()->getDistrict() as $key => $district) {
            $districts[$district->getId()] = $district->getName();
        }*/

/*        $employeeDistrictHistory = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->findOneBy(['employee' => $board->getEmployee(), 'year' => $board->getYear(), 'month' => $board->getMonth()]);

        $districts = $employeeDistrictHistory ? $employeeDistrictHistory->getDistrict() : '';


        $board->setDistrict($districts);
        $this->getDoctrine()->getManager()->persist($board);
        $this->getDoctrine()->getManager()->flush();*/

        return $this->render('@TerminalbdKpi/employeeboard/custom-format/new.html.twig', [
            'board' => $board,
            'arrayData' => $arrayData,
            'feedAndGrowth' => $feedAndGrowthArray,
            'outstanding' => $outstanding,
            'docSale' => $docSale,
        ]);
    }

    /**
     * @Route("/{id}/preview", methods={"GET"}, name="kpi_employee_board_preview")
     * @param Request $request
     * @param EmployeeBoard $entity
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */
    public function detailsPreview(Request $request, EmployeeBoard $entity): Response
    {

        $boardAttributes = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->EmployeeBoardMarks($entity);

//        $boardAttributes = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->find($entity);
        $arrayData=[];
        /* @var EmployeeBoardAttribute $boardAttribute*/
        foreach ($boardAttributes as $boardAttribute){
            $arrayData[$boardAttribute->getParameter()->getId()][$boardAttribute->getActivity()->getId()][]=$boardAttribute;
        }
        $mode = $_REQUEST['mode'];
        if($mode == "print"){
            return $this->render('@TerminalbdKpi/employeeboard/report/print.html.twig', [
                'board' => $entity,
                'arrayData' => $arrayData,
            ]);
        }else{
            //Need to collect values here to pass the pdf view($entities)

            // Configure Dompdf according to your needs
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial');

            // Instantiate Dompdf with our options
            $dompdf = new Dompdf($pdfOptions);

            // Retrieve the HTML generated in our twig file
            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/pdf.html.twig', ['board' => $entity,'arrayData' => $arrayData]);

            // Load HTML to Dompdf
            $dompdf->loadHtml($html);

            // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
            $dompdf->setPaper('legal', 'landscape');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser (force download)
            $dompdf->stream("kpi" . ".pdf", [
                "Attachment" => false
            ]);
        }


    }

    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/delete", methods={"GET"}, name="kpi_employee_board_delete", options={"expose" = true})
     * @param EmployeeBoard $board
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */
    public function delete(EmployeeBoard $board): Response
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($board);
        $em->flush();
//        $this->addFlash('success', 'post.deleted_successfully');
//        return $this->redirectToRoute('kpi_employee_board');
        return new JsonResponse([
            'status' => 200,
            'message' => 'success'
        ]);
    }

    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/attribute-update", methods={"GET"}, name="kpi_employee_board_attribute_update")
     * @param EmployeeBoardAttribute $boardAttribute
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */
    public function attributeUpdate(EmployeeBoardAttribute $boardAttribute): Response
    {
        $board = $boardAttribute->getEmployeeBoard();
        $mark = $_REQUEST['mark'];
        if($mark){
            $attribute = $this->getDoctrine()->getRepository(MarkChart::class)->find($mark);

            $em = $this->getDoctrine()->getManager();
            $boardAttribute->setMarkDistribution($attribute);

            if ($board->getEmployee()->getId() === $this->getUser()->getId()){
                $boardAttribute->setSelfMark($attribute->getMark());
            }else{
                $boardAttribute->setMark($attribute->getMark());
            }

            $em->flush();
            $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->gradeUpdate($board);
            return new Response($attribute->getMark());
        }
        return new Response(0);

    }

    /**
     * @Route("/{id}/report-details", methods={"GET"}, name="kpi_details_report")
     * @param $id
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */
    public function reportDetails($id): Response
    {
        $entity = $this->getDoctrine()->getRepository(EmployeeBoard::class)->find($id);
/*        $employeeDistricts = $entity->getEmployee()->getDistrict();

        $districtsId = [];

        foreach ($employeeDistricts as $employeeDistrict){
            $districtsId[]=$employeeDistrict->getId();
        }*/

        $districtHistory = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->findOneBy(['employee' => $entity->getEmployee(), 'month' => $entity->getMonth(), 'year' => $entity->getYear()]);
        $districts = $districtHistory ? $districtHistory->getDistrict() : '';
        $districtsId = $districts ? array_keys(json_decode($districts, true)) : [];

        $category = $this->getDoctrine()->getRepository(AgentCategory::class)->getPreviousYearCategory($districtsId);

        $boardAttributes = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->EmployeeBoardMarks($entity);

//        $boardAttributes = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->find($entity);
        $arrayData=[];
        /* @var EmployeeBoardAttribute $boardAttribute*/
        foreach ($boardAttributes as $boardAttribute){
            $arrayData[$boardAttribute->getParameter()->getId()][$boardAttribute->getActivity()->getId()][]=$boardAttribute;
        }
        return $this->render('@TerminalbdKpi/employeeboard/report/details.html.twig', [
            'board' => $entity,
            'arrayData' => $arrayData,
        ]);

    }

    /**
     * @Route("/{id}/report-summary", methods={"GET"}, name="kpi_summary_report")
     * @param EmployeeBoard $board
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */
    public function reportSummary(EmployeeBoard $board): Response
    {
        $totalObtainMark = 0;
        $totalActualMark = 0;
        $totalSelfMark = 0;
        $marks = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->employeeBoardSummaryReport($board);
        foreach ($marks as $parameter => $mark) {
            foreach ($mark as $item) {
                if ($parameter === 'Core Responsibilities'){
                    $totalSelfMark += $item['mark'];
                }
                $totalObtainMark += $item['mark'];
                $totalActualMark += $item['actualMark'];
                $totalSelfMark += $item['selfMark'];
            }
        }
        return $this->render('@TerminalbdKpi/employeeboard/report/summary.html.twig', [
            'board' => $board,
            'entities' => $marks,
            'totalObtainMark' => $totalObtainMark,
            'totalActualMark' => $totalActualMark,
            'totalSelfMark' => $totalSelfMark,
        ]);

    }

    /**
     * @Security("is_granted('ROLE_USER')")
     * @Route("/{id}/report-summary-print", methods={"GET"}, name="kpi_summary_report_print")
     * @param EmployeeBoard $board
     * @return Response
     */
    public function reportSummaryPdf(EmployeeBoard $board): Response
    {
        $totalObtainMark = 0;
        $totalActualMark = 0;
        $marks = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->employeeBoardSummaryReport($board);
        foreach ($marks as $mark) {
            foreach ($mark as $item) {
                $totalObtainMark += $item['mark'];
                $totalActualMark += $item['actualMark'];
            }
        }
        return $this->render('@TerminalbdKpi/employeeboard/report/summary-print.html.twig', [
            'board' => $board,
            'entities' => $marks,
            'totalObtainMark' => $totalObtainMark,
            'totalActualMark' => $totalActualMark,
        ]);

    }

    /**
     * @Security("is_granted('ROLE_USER')")
     * @Route("/{id}/report-sales-achievement/{mode}", defaults={"mode" = null}, methods={"GET"}, name="kpi_report_sales_achievement")
     * @param EmployeeBoard $entity
     * @param $mode
     * @param Request $request
     * @return Response
     */
    public function salesAchievementSummary(EmployeeBoard $entity, $mode, Request $request): Response
    {
//        $locations = $entity->getEmployee()->getDistrict();
        $districtHistory = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->findOneBy(['employee' => $entity->getEmployee(), 'month' => $entity->getMonth(), 'year' => $entity->getYear()]);
        $districts = $districtHistory ? $districtHistory->getDistrict() : '';
        $districtsId = $districts ? array_keys(json_decode($districts, true)) : [];
/*        $locationsId = array();
        if(!empty($locations)){
            foreach ($locations as $location){
                $locationsId[] = $location->getId();
            }
        }*/

        $employee = $entity->getEmployee();

        $getEmployeesByLineManager = $this->getDoctrine()->getRepository(User::class)->findBy(['lineManager'=>$employee, 'enabled'=>1]);

        $employeeArrs = [];
        foreach ($getEmployeesByLineManager as $childEmployee){
            if(!empty($childEmployee)){
                $employeeArrs[] = $childEmployee->getId();
            }
        }
        $parameter = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(array('slug'=>'core-responsibilities','status'=>1));

        $feedAndGrowth = $this->getDoctrine()->getRepository(EmployeeBoardSubAttribute::class)->getKpiSummaryForFeedAndGrowth($entity);

        $outstanding = $this->getDoctrine()->getRepository(AgentOutstanding::class)->getLocationWiseOutstanding($districtsId, $entity);

        $docSale = $this->getDoctrine()->getRepository(AgentDocSaleCollection::class)->getLocationWiseDocSales($districtsId, $entity);
        $individualTeamMemberMarks = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getIndividualTeamMemberMarks($employeeArrs, $parameter, $entity);

        $dCategoryUpgrade = $this->getDoctrine()->getRepository(AgentCategory::class)->getAgentWithDInDecember($entity,$districtsId);
        $cCategoryUpgrade = $this->getDoctrine()->getRepository(AgentCategory::class)->getAgentWithCInDecember($entity,$districtsId);
        $growthAgentSalesDetails = $this->getDoctrine()->getRepository(AgentOrder::class)->getGrowthAgentSalesDetails($entity, $districtsId);

        if ($mode == 'pdf'){

            // Configure Dompdf according to your needs
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial');

            // Instantiate Dompdf with our options
            $dompdf = new Dompdf($pdfOptions);

            // Retrieve the HTML generated in our twig file
            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/salesDetailsPdf.html.twig', [
                'entity' => $entity,
                'feedAndGrowth' => $feedAndGrowth,
                'outstanding' => $outstanding,
                'dCategoryUpgrade' => $dCategoryUpgrade,
                'cCategoryUpgrade' => $cCategoryUpgrade,
                'growthAgentSalesDetails' => $growthAgentSalesDetails,
                'docSale' => $docSale,
                'individualTeamMemberMarks' => $individualTeamMemberMarks,

            ]);

            // Load HTML to Dompdf
            $dompdf->loadHtml($html);

            // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
            $dompdf->setPaper('legal', 'portrait');

            // Render the HTML as PDF
            $dompdf->render();
            $fileName = $request->get('_route') . '_' . $entity->getEmployee()->getName() . '_' . $entity->getMonth() . '_' . $entity->getYear() . '_' . time();
            // Output the generated PDF to Browser (force download)
            $dompdf->stream($fileName . ".pdf", [
                "Attachment" => false
            ]);

        }elseif ($mode == 'excel'){
            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/salesDetailsExcel.html.twig', [
                'entity' => $entity,
                'feedAndGrowth' => $feedAndGrowth,
//            'attributes' => $attributes,
                'outstanding' => $outstanding,
                'dCategoryUpgrade' => $dCategoryUpgrade,
                'cCategoryUpgrade' => $cCategoryUpgrade,
                'growthAgentSalesDetails' => $growthAgentSalesDetails,
                'docSale' => $docSale,
                'individualTeamMemberMarks' => $individualTeamMemberMarks,

            ]);

            $fileName = $request->get('_route') . '_' . $entity->getEmployee()->getName() . '_' . $entity->getMonth() . '_' . $entity->getYear() . '_' . time() . '.xls';


            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=$fileName");

            echo $html;
            die();

        }else{
            return $this->render('@TerminalbdKpi/employeeboard/report/salesDetails.html.twig', [
                'entity' => $entity,
                'feedAndGrowth' => $feedAndGrowth,
//            'attributes' => $attributes,
                'outstanding' => $outstanding,
                'dCategoryUpgrade' => $dCategoryUpgrade,
                'cCategoryUpgrade' => $cCategoryUpgrade,
                'growthAgentSalesDetails' => $growthAgentSalesDetails,
                'docSale' => $docSale,
                'individualTeamMemberMarks' => $individualTeamMemberMarks,

            ]);
        }

    }

    /**
     *
     * @Route("/{id}/report-sales-achievement-custom-format/{mode}", defaults={"mode" = null}, methods={"GET"}, name="kpi_report_sales_achievement_custom_format")
     * @param EmployeeBoard $board
     * @param $mode
     * @param Request $request
     * @return Response
     * @Security("is_granted('ROLE_USER')")
     */
    public function salesAchievementSummaryForCustomFormat(EmployeeBoard $board, $mode, Request $request): Response
    {
        $parameter = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(array('slug'=>'core-responsibilities','status'=>1));

        $feedAndGrowth = $this->getDoctrine()->getRepository(EmployeeBoardSubAttribute::class)->getKpiSummaryForFeedAndGrowth($board);

        $outstanding = $this->getDoctrine()->getRepository(AgentOutstandingForCustomFormat::class)->findBy(['employeeBoard' => $board]);
        $findOutstandingAttribute = $this->getDoctrine()->getRepository(MarkChart::class)->findOneBy(['slug' => 'outstanding-limit-vs-actual-feed']);
        if ($findOutstandingAttribute){
            $outstandingBoardAttribute = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $findOutstandingAttribute]);
            if ($outstandingBoardAttribute){
                $outstandingMark = $outstandingBoardAttribute->getMark();
            }
        }

        $docSale = $this->getDoctrine()->getRepository(AgentDocSaleCollectionForCustomFormat::class)->findBy(['employeeBoard' => $board]);
        $findDocSaleAttribute = $this->getDoctrine()->getRepository(MarkChart::class)->findOneBy(['slug' => 'doc-sales-collection']);
        if ($findDocSaleAttribute){
            $docSaleBoardAttribute = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $findDocSaleAttribute]);
            if ($docSaleBoardAttribute){
                $docSaleMark = $docSaleBoardAttribute->getMark();
            }
        }
        if ($mode == 'pdf'){

            // Configure Dompdf according to your needs
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial');

            // Instantiate Dompdf with our options
            $dompdf = new Dompdf($pdfOptions);

            // Retrieve the HTML generated in our twig file
            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/salesDetailsCustomFormatPdf.html.twig', [
                'board' => $board,
                'feedAndGrowth' => $feedAndGrowth,
                'outstanding' => $outstanding,
                'outstandingMark' => $outstandingMark,
                'docSale' => $docSale,
                'docSaleMark' => $docSaleMark,
            ]);

            // Load HTML to Dompdf
            $dompdf->loadHtml($html);

            // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
            $dompdf->setPaper('legal', 'portrait');

            // Render the HTML as PDF
            $dompdf->render();
            $fileName = $request->get('_route') . '_' . $board->getEmployee()->getName() . '_' . $board->getMonth() . '_' . $board->getYear() . '_' . time();
            // Output the generated PDF to Browser (force download)
            $dompdf->stream($fileName . ".pdf", [
                "Attachment" => false
            ]);

        }elseif ($mode == 'excel'){
            $html = $this->renderView('@TerminalbdKpi/employeeboard/report/salesDetailsCustomFormatExcel.html.twig', [
                'board' => $board,
                'feedAndGrowth' => $feedAndGrowth,
                'outstandingMark' => $outstandingMark,
                'outstanding' => $outstanding,
                'docSale' => $docSale,
                'docSaleMark' => $docSaleMark,
            ]);

            $fileName = $request->get('_route') . '_' . $board->getEmployee()->getName() . '_' . $board->getMonth() . '_' . $board->getYear() . '_' . time() . '.xls';


            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=$fileName");

            echo $html;
            die();

        }
        return $this->render('@TerminalbdKpi/employeeboard/report/salesDetailsCustomFormat.html.twig', [
            'board' => $board,
            'feedAndGrowth' => $feedAndGrowth,
            'outstanding' => $outstanding,
            'outstandingMark' => $outstandingMark,
            'docSale' => $docSale,
            'docSaleMark' => $docSaleMark,
        ]);

    }


    /**
     * @Route("/{id}/approve", methods={"GET"}, name="kpi_approve")
     * @param EmployeeBoard $employeeBoard
     * @return Response
     * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_KPI_LINE_MANAGER') or is_granted('ROLE_DOMAIN')")
     */
    public function approve(EmployeeBoard $employeeBoard): Response
    {

        $parameters = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(['level' => 1,'status' => 1]);

        $em = $this->getDoctrine()->getManager();

        $em->getRepository(EmployeeBoardAttribute::class)->insertMarkDistribution($employeeBoard,$parameters); //update Actual mark & obtain mark

        $employeeBoard->setApprovedBy($this->getUser());
        $em->persist($employeeBoard);
        $em->flush();
        return $this->redirectToRoute('kpi_employee_board');
    }


    /**
     * @param EmployeeBoardAttribute $boardAttribute
     * @param Request $request
     * @return JsonResponse
     * @Route("/{id}/update-customer-development", name="kpi_employee_update_customer_development")
     * @Security("is_granted('ROLE_USER')")
     */
    public function updateCustomerDevelopment(EmployeeBoardAttribute $boardAttribute, Request $request)
    {
        $board = $boardAttribute->getEmployeeBoard();
        $em = $this->getDoctrine()->getManager();
        $mark = $request->query->get('mark');

        $markOneAttributeSlug = [
            'monthly-less-costing-model-farm-develop',
            'monthly-farmers-training-program-10-15-farmers',
            'monthly-farmers-training-program-10-15-farmer',
            'monthly-model-farm-develop',
            'monthly-model-cattle-farm-develop'

        ];
        $markTwoAttributeSlug = [
            'monthly-broiler-sonali-before-sale-report-layer-performance-report',
            'monthly-broiler-sonali-life-cycle-report-layer-life-cycle-report',
            'monthly-antibiotic-free-farm-develop',
            'life-cycle-report-culture-after-sale',
            'company-species-wise-avg-fcr-report-culture-after-sale',
            'monthly-new-fish-farmer-introduce-to-nourish-family',
            'monthly-fish-farm-information-survey-report',
            'monthly-farm-visit-report',
            'monthly-dairy-fattening-life-cycle-report',
            'monthly-new-agent-or-sub-agent-introduce-to-nourish-family',
            'monthly-new-fish-farmer-introduce-to-nourish-family',
            'monthly-new-agent-or-sub-agent-creation',
            'monthly-disease-mapping-report',
            'monthly-broiler-sonali-before-sale-report-layer-performance-report-lab-service',
            'monthly-new-agent-introduce-to-nourish-family-1-per-month',
        ];
        $markThreeAttributeSlug = [
            'monthly-new-poultry-farm-introduce-to-nourish-feed',
            'monthly-3-cattle-introduce-to-nourish-feed',
            'fish-agents-sales-20-growth-only-for-permanent-agents',
            'monthly-new-cattle-farm-introduce-to-nourish-feed',
            'agent-upgradation',
            '5-cattle-included-per-month-in-your-head',
        ];
        $markFourAttributeSlug = [
            'monthly-broiler-sonali-fcr-report-after-sale',
            'monthly-dairy-fattening-feed-performance-report',
            'monthly-broiler-sonali-fcr-report-after-sale-lab-service'
        ];
        if (in_array($boardAttribute->getAttribute()->getSlug(), $markFourAttributeSlug)){
            if ($mark >= 4){
                $boardAttribute->setMark(4);
            }else{
                $boardAttribute->setMark($mark);
            }
        }elseif (in_array($boardAttribute->getAttribute()->getSlug(), $markTwoAttributeSlug)){
            if ($mark >= 2){
                $boardAttribute->setMark(2);
            }else{
                $boardAttribute->setMark($mark);
            }
        }elseif (in_array($boardAttribute->getAttribute()->getSlug(), $markOneAttributeSlug)){
            if ($mark >= 1){
                $boardAttribute->setMark(1);
            }else{
                $boardAttribute->setMark($mark);
            }
        }elseif (in_array($boardAttribute->getAttribute()->getSlug(), $markThreeAttributeSlug)){
            if ($mark >= 3){
                $boardAttribute->setMark(3);
            }else{
                $boardAttribute->setMark($mark);
            }
        }
        $em->persist($boardAttribute);
        $em->flush();
        $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->gradeUpdate($board);

        return new JsonResponse($boardAttribute->getMark());
    }

    /**
     * @param Request $request
     * @param EmployeeBoard $board
     * @Route("/{board}/custom-format/new", name="custom_format_kpi")
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @Security("is_granted('ROLE_USER')")
     */
    public function customFormatSubmit(Request $request, EmployeeBoard $board)
    {
        $data = $request->request->all();
        $em = $this->getDoctrine()->getManager();
        foreach ($data as $key => $item) {
            if ($key === 'sales'){
                foreach ($item as $attributeId => $sale) {
                    $findAttribute = $this->getDoctrine()->getRepository(MarkChart::class)->find($attributeId);
                    if ($findAttribute){
                        $subAttribute = new EmployeeBoardSubAttribute();
                        $exist = $this->getDoctrine()->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(['employeeBoard' => $board, 'markDistribution' => $attributeId]);
                        if ($exist){
                            $subAttribute = $exist;
                        }
                        $subAttribute->setEmployeeBoard($board);
                        $subAttribute->setMarkDistribution($findAttribute);
                        $subAttribute->setTargetQuantity($sale['target'] ?: 0);
                        $subAttribute->setSalesQuantity($sale['sales'] ?: 0);

                        $mark = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->salesTargetCalculation($sale['target'], $sale['sales']);
                        $subAttribute->setMark($mark);

                        $em->persist($subAttribute);
                        $em->flush();

                        $boardAttribute = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $findAttribute]);
                        if ($boardAttribute) {
                            $boardAttribute->setMark($mark);
                            $em->persist($boardAttribute);
                            $em->flush();
                        }
                    }

                }
            }elseif ($key === 'growth'){
                foreach ($item as $attributeId => $growth) {
                    $findAttribute = $this->getDoctrine()->getRepository(MarkChart::class)->find($attributeId);
                    if ($findAttribute){
                        $subAttribute = new EmployeeBoardSubAttribute();
                        $exist = $this->getDoctrine()->getRepository(EmployeeBoardSubAttribute::class)->findOneBy(['employeeBoard' => $board, 'markDistribution' => $attributeId]);
                        if ($exist){
                            $subAttribute = $exist;
                        }
                        $subAttribute->setEmployeeBoard($board);
                        $subAttribute->setMarkDistribution($findAttribute);
                        $subAttribute->setTargetQuantity($growth['previous'] ?: 0);
                        $subAttribute->setSalesQuantity($growth['current'] ?: 0);

                        $slug = explode('-', $findAttribute->getSlug());
                        $mark = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->salesGrowthCalculation($slug[1], $growth['previous'] ?: 0, $growth['current'] ?: 0)[$findAttribute->getSlug()];

                        $subAttribute->setMark($mark);

                        $em->persist($subAttribute);
                        $em->flush();

                        $boardAttribute = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $findAttribute]);
                        if ($boardAttribute) {
                            $boardAttribute->setMark($mark);
                            $em->persist($boardAttribute);
                            $em->flush();
                        }
                    }

                }

            }elseif ($key === 'outstanding'){
                $findOutstanding = $this->getDoctrine()->getRepository(AgentOutstandingForCustomFormat::class)->findOneBy(['employeeBoard' => $board]);
                $newOutstanding = $findOutstanding ?: new AgentOutstandingForCustomFormat();

                $newOutstanding->setEmployeeBoard($board);
                $newOutstanding->setActualAmount($item['actual-amount'] ?: 0);
                $newOutstanding->setLimitAmount($item['limit-amount'] ?: 0);
                $newOutstanding->setOutstanding(($item['limit-amount'] ?: 0) - ($item['actual-amount'] ?: 0));
                $this->getDoctrine()->getManager()->persist($newOutstanding);
                $this->getDoctrine()->getManager()->flush();

                $outstanding = $this->getDoctrine()->getRepository(AgentOutstandingForCustomFormat::class)->findOneBy(['employeeBoard' => $board]);
                $outstandingMark = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->outstandingLimitCalculation($board, $outstanding->getOutstanding());

                $outstandingAttribute = $em->getRepository(MarkChart::class)->findOneBy(['slug' => 'outstanding-limit-vs-actual-feed']);
                $findOutstandingAttribute = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $outstandingAttribute]);

                if ($findOutstandingAttribute) {
                    $findOutstandingAttribute->setMark($outstandingMark);
                    $em->persist($findOutstandingAttribute);
                    $em->flush();
                }

            }elseif ($key === 'docSale'){
                $findDocSale = $this->getDoctrine()->getRepository(AgentDocSaleCollectionForCustomFormat::class)->findOneBy(['employeeBoard' => $board]);

                $newDocSale = $findDocSale ?: new AgentDocSaleCollectionForCustomFormat();

                $newDocSale->setEmployeeBoard($board);
                $newDocSale->setSales($item['sale'] ?: 0);
                $newDocSale->setCollection($item['collection'] ?: 0);
                $this->getDoctrine()->getManager()->persist($newDocSale);
                $this->getDoctrine()->getManager()->flush();

                $docSale = $this->getDoctrine()->getRepository(AgentDocSaleCollectionForCustomFormat::class)->getTotalDocSale($board);
                $docSaleMark = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->docSalesCollectionCalculation($docSale['totalCollection'], $docSale['totalSales']);

                $docSaleAttribute = $em->getRepository(MarkChart::class)->findOneBy(['slug' => 'doc-sales-collection']);
                $findDocSaleAttribute = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $docSaleAttribute]);
                if ($findDocSaleAttribute) {
                    $findDocSaleAttribute->setMark($docSaleMark);
                    $em->persist($findDocSaleAttribute);
                    $em->flush();
                }

            }elseif ($key === 'customerDevelopment'){
                foreach ($item as $attributeId => $agentNumber) {
                    $agentNumber['upgradeAgent'] = $agentNumber['upgradeAgent'] ?: 0;
                    $agentNumber['totalAgents'] = $agentNumber['totalAgents'] ?: 0;
                    $findAttribute = $this->getDoctrine()->getRepository(MarkChart::class)->find($attributeId);
                    if ($findAttribute){
                        $boardAttribute = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $findAttribute]);
                        if ($boardAttribute){
                            if ($boardAttribute->getAttribute()->getSlug() == 'agent-upgradation'){
                                if ($agentNumber['totalAgents'] > 0){
                                    $agentNumberWithPercentage = ($agentNumber['upgradeAgent'] * 100) / $agentNumber['totalAgents'];
                                    if ($agentNumberWithPercentage >= 20) {
                                        $mark = 3;
                                    } elseif ($agentNumberWithPercentage >= 10 && $agentNumberWithPercentage < 20) {
                                        $mark = 2;
                                    } elseif ($agentNumberWithPercentage >= 1 && $agentNumberWithPercentage < 10) {
                                        $mark = 1;
                                    } else {
                                        $mark = 1;
                                    }
                                }
                            }else{
                                $fiftyPercentAgents = $agentNumber['totalAgents'] /  2; //50% agents
                                if ($fiftyPercentAgents > 0){
                                    $percentage = round(($agentNumber['upgradeAgent'] * 100) / $fiftyPercentAgents);
                                    if($percentage >= 100){
                                        $mark = 5;
                                    }elseif ($percentage < 100 && $percentage >= 80){
                                        $mark = 4;
                                    }elseif ($percentage < 80 && $percentage >= 70){
                                        $mark = 3;
                                    }elseif ($percentage < 70 && $percentage >= 60){
                                        $mark = 2;
                                    }elseif ($percentage < 60 && $percentage > 0){
                                        $mark = 1;
                                    }else{
                                        $mark = 0;
                                    }
                                }
                            }
                            $boardAttribute->setTargetAmount($agentNumber['totalAgents']);
                            $boardAttribute->setTargetAchievement($agentNumber['upgradeAgent']);
                            $boardAttribute->setMark($mark);
                            $em->persist($boardAttribute);
                            $em->flush();
                        }
                    }
                }
            }elseif ($key === 'skills'){
                foreach ($item as $attributeId => $markDistributionId) {
                    $findAttribute = $this->getDoctrine()->getRepository(MarkChart::class)->find($attributeId);
                    if ($findAttribute){
                        $boardAttribute = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $findAttribute]);

                        if ($boardAttribute){
                            $findMarkDistribution = $this->getDoctrine()->getRepository(MarkChart::class)->find($markDistributionId);
                            if ($findMarkDistribution){
                                $boardAttribute->setMarkDistribution($findMarkDistribution);
                                if ($board->getEmployee()->getId() === $this->getUser()->getId()){
                                    $boardAttribute->setSelfMark($findMarkDistribution->getMark());
                                }else{
                                    $boardAttribute->setMark($findMarkDistribution->getMark());
                                }

                                $em->persist($boardAttribute);
                                $em->flush();
                            }

                        }
                    }
                }
            }elseif ($key === 'values'){
                foreach ($item as $attributeId => $markDistributionId) {
                    $findAttribute = $this->getDoctrine()->getRepository(MarkChart::class)->find($attributeId);
                    if ($findAttribute){
                        $boardAttribute = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findOneBy(['employeeBoard' => $board, 'attribute' => $findAttribute]);
                        if ($boardAttribute){
                            $findMarkDistribution = $this->getDoctrine()->getRepository(MarkChart::class)->find($markDistributionId);
                            if ($findMarkDistribution){
                                $boardAttribute->setMarkDistribution($findMarkDistribution);
                                if ($board->getEmployee()->getId() === $this->getUser()->getId()){
                                    $boardAttribute->setSelfMark($findMarkDistribution->getMark());
                                }else{
                                    $boardAttribute->setMark($findMarkDistribution->getMark());
                                }

                                $em->persist($boardAttribute);
                                $em->flush();
                            }
                        }
                    }
                }
            }
//            elseif ($key === 'customerDevelopment'){
//
//
//            }
        }

        // Customer development
        $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->gradeUpdate($board);

        return $this->redirectToRoute('kpi_details_report', ['id' => $board->getId()]);
    }


    /**
     * @Route("/{board}/agent-outstandig-for-custom-format", name="agent_outstanding_for_custom_format", options={"expose" = true})
     * @param Request $request
     * @param EmployeeBoard $board
     * @return JsonResponse
     */
   /* public function customFormatOutstandingInsert(Request $request, EmployeeBoard $board)
    {
        $data = $request->request->all();
        $findAgent = $this->getDoctrine()->getRepository(Agent::class)->find($data['agentId']);
        if ($findAgent){
            $newOutstanding = new AgentOutstandingForCustomFormat();
            $findOutstanding = $this->getDoctrine()->getRepository(AgentOutstandingForCustomFormat::class)->findOneBy(['employeeBoard' => $board, 'agent' => $findAgent, 'month' => $board->getMonth(), 'year' => $board->getYear()]);

            if ($findOutstanding){
                $newOutstanding = $findOutstanding;
            }
            $newOutstanding->setAgent($findAgent);
            $newOutstanding->setEmployeeBoard($board);
            $newOutstanding->setActualAmount($data['actualAmount'] ?: 0);
            $newOutstanding->setLimitAmount($data['limitAmount'] ?: 0);
            $newOutstanding->setOutstanding($data['limitAmount'] - $data['actualAmount']);
            $newOutstanding->setMonth($board->getMonth());
            $newOutstanding->setYear($board->getYear());
            $this->getDoctrine()->getManager()->persist($newOutstanding);
            $this->getDoctrine()->getManager()->flush();

            return new JsonResponse([
                'status' => 200,
                'data' => [
                    'agent' => $findAgent->getName(),
                    'id' => $newOutstanding->getId(),
                ]
            ]);
        }

        return new JsonResponse([
            'status' => 500,
            'message' => 'failed'
        ]);
    }*/

    /**
     * @Route("/{board}/agent-doc-sale-for-custom-format", name="agent_doc_sale_for_custom_format", options={"expose" = true})
     * @param Request $request
     * @param EmployeeBoard $board
     * @return JsonResponse
     */
 /*   public function customFormatDocSaleInsert(Request $request, EmployeeBoard $board)
    {

        $data = $request->request->all();
        $findAgent = $this->getDoctrine()->getRepository(Agent::class)->find($data['agentId']);
        if ($findAgent){
            $newDocSale = new AgentDocSaleCollectionForCustomFormat();
            $findDocSale = $this->getDoctrine()->getRepository(AgentDocSaleCollectionForCustomFormat::class)->findOneBy(['employeeBoard' => $board, 'agent' => $findAgent, 'month' => $board->getMonth(), 'year' => $board->getYear()]);

            if ($findDocSale){
                $newDocSale = $findDocSale;
            }
            $newDocSale->setAgent($findAgent);
            $newDocSale->setEmployeeBoard($board);
            $newDocSale->setSales($data['actualAmount'] ?: 0);
            $newDocSale->setCollection($data['limitAmount'] ?: 0);
            $newDocSale->setMonth($board->getMonth());
            $newDocSale->setYear($board->getYear());
            $this->getDoctrine()->getManager()->persist($newDocSale);
            $this->getDoctrine()->getManager()->flush();

            return new JsonResponse([
                'status' => 200,
                'data' => [
                    'agent' => $findAgent->getName(),
                    'id' => $newDocSale->getId(),
                ]
            ]);
        }

        return new JsonResponse([
            'status' => 500,
            'message' => 'failed'
        ]);
    }*/

    /**
     * @Route("/{id}/delete-agent-outstanding-for-custom-format", name="delete_agent_outstanding_for_custom_format", options={"expose" = true})
     * @param AgentOutstandingForCustomFormat $id
     * @return JsonResponse
     */
/*    public function customFormatOutstandingDelete(AgentOutstandingForCustomFormat $id)
    {
        $this->getDoctrine()->getManager()->remove($id);
        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse([
            'status' => 200,
            'message' => 'success'
        ]);
    }*/

    /**
     * @Route("/{id}/delete-agent-doc-sale-for-custom-format", name="delete_agent_doc_sale_for_custom_format", options={"expose" = true})
     * @param AgentDocSaleCollectionForCustomFormat $id
     * @return JsonResponse
     */
/*    public function customFormatDocSaleDelete(AgentDocSaleCollectionForCustomFormat $id)
    {
        $this->getDoctrine()->getManager()->remove($id);
        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse([
            'status' => 200,
            'message' => 'success'
        ]);
    }*/


    /**
     * @Route("/update/report-format", name="update_report_format")
     * @Security("is_granted('ROLE_DEVELOPER')")
     */
//    public function processReportFormat()
//    {
//        $records = $this->getDoctrine()->getRepository(EmployeeBoard::class)->findAll();
//        foreach ($records as $record) {
//            $record->setReportMode($record->getEmployee()->getReportMode());
//            $this->getDoctrine()->getManager()->persist($record);
//            $this->getDoctrine()->getManager()->flush();
//
//        }
//        return $this->redirectToRoute('kpi_employee_board');
//    }


}
