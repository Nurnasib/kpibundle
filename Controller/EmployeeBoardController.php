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
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\AgentOutstanding;
use Terminalbd\KpiBundle\Entity\DistrictOrder;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeBoardSubAttribute;
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
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_KPI') or is_granted('ROLE_DOMAIN')")
     * @Route("/new", methods={"GET", "POST"}, name="kpi_board_new")
     * @param Request $request
     * @return Response
     */
    public function new(Request $request): Response
    {

        $entities = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(['level' => 1,'status' => 1]);
        $entity = new EmployeeBoard();

        $form = $this->createForm(EmployeeBoardFormType::class , $entity,['user'=>$this->getUser()])
            ->add('monthYear', TextType::class,['attr'=>['class'=>'inputMonth','autocomplete'=>'off'],'mapped'=>false])
            ->add('SaveAndCreate', SubmitType::class);
        $form->handleRequest($request);
        $data = $request->request->all();
        if ($form->isSubmitted() && $form->isValid()) {
            $emp = $form['employee']->getData();
            $employee = $data['employee_board_form']['employee'];

            $monthYear = explode(',', $data['employee_board_form']['monthYear']);
            $month = $monthYear[0];
            $year = $monthYear[1];

            $totalTeamMember = $this->getDoctrine()->getRepository(User::class)->findBy(['lineManager' => $emp]);
            $generatedTeamMember = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getGeneratedTeamMember($totalTeamMember, $month, $year);

            if (count($totalTeamMember) != count($generatedTeamMember)){
                $this->addFlash('error',  "{$emp->getName()} has not generated all team members KPI of {$month}, {$year} under him. So you won't be able to generate KPI of {$emp->getName()} for this month.");
                return $this->redirectToRoute('kpi_board_new');
            }

            $exist = $this->getDoctrine()->getRepository(EmployeeBoard::class)->findOneBy(
                array('employee' => $employee,'month'=>$month, 'year'=>$year)
            );

            if (empty($exist)) {
                $em = $this->getDoctrine()->getManager();
                $entity->setYear($year);
                $entity->setMonth($month);
                $entity->setProcess('created');
                $entity->setCreated(new \DateTime());
                $entity->setCreatedBy($this->getUser());
                $entity->setUpdated(new \DateTime());

                $em->persist($entity);
                $em->flush();

                $em->getRepository(EmployeeBoardAttribute::class)->insertMarkDistribution($entity,$entities);
                return $this->redirectToRoute('kpi_employee_board_edit',array('id'=>$entity->getId()));
            }else{
                return $this->redirectToRoute('kpi_employee_board_edit',array('id'=>$exist->getId()));
            }

        }
        return $this->render('@TerminalbdKpi/employeeboard/create.html.twig', [
            'setupEntity' => $entity,
            'form' => $form->createView()
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
     * @Route("/{id}/edit", methods={"GET", "POST"}, name="kpi_employee_board_edit")
     * @param Request $request
     * @param EmployeeBoard $entity
     * @return Response
     */

    public function edit(Request $request, EmployeeBoard $entity): Response
    {
        $data = $request->request->all();
        $em = $this->getDoctrine()->getManager();
        $entities = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(['level' => 1,'status' => 1]);

        $em->getRepository(EmployeeBoardAttribute::class)->insertMarkDistribution($entity,$entities);


/*        $gradeLetters = ['C','D'];
        $categoryUpgradationMark = $this->getDoctrine()->getRepository(AgentCategory::class)->getCategoryUpgradationMarks($entity,$gradeLetters);
        dump($categoryUpgradationMark);*/



        $boardAttributes = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->EmployeeBoardMarks($entity);
        $arrayData=[];
        /* @var EmployeeBoardAttribute $boardAttribute*/
        foreach ($boardAttributes as $boardAttribute){
            $arrayData[$boardAttribute->getParameter()->getId()][$boardAttribute->getActivity()->getId()][]=$boardAttribute;
        }
        return $this->render('@TerminalbdKpi/employeeboard/new.html.twig', [
            'board' => $entity,
//            'marks' => $marks,
//            'entities' => $entities,
            'arrayData' => $arrayData,
        ]);
    }

    /**
     * @Route("/{id}/preview", methods={"GET"}, name="kpi_employee_board_preview")
     * @param Request $request
     * @param EmployeeBoard $entity
     * @return Response
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
     * @Route("/{id}/delete", methods={"GET"}, name="kpi_employee_board_delete")
     * @Security("is_granted('ROLE_KPI') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_ADMIN')")
     * @param EmployeeBoard $board
     * @return Response
     */
    public function delete(EmployeeBoard $board): Response
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($board);
        $em->flush();
        $this->addFlash('success', 'post.deleted_successfully');
        return $this->redirectToRoute('kpi_employee_board');
    }

    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/attribute-update", methods={"GET"}, name="kpi_employee_board_attribute_update")
     * @param EmployeeBoardAttribute $entity
     * @return Response
     */
    public function attributeUpdate(EmployeeBoardAttribute $entity): Response
    {
        $mark = $_REQUEST['mark'];
        if($mark){
            $attribute = $this->getDoctrine()->getRepository(MarkChart::class)->find($mark);

            $em = $this->getDoctrine()->getManager();
            $entity->setMarkDistribution($attribute);
            $entity->setMark($attribute->getMark());
            $em->flush();
            return new Response($attribute->getMark());
        }
        return new Response(0);

    }

    /**
     *
     * @Route("/{id}/report-details", methods={"GET"}, name="kpi_details_report")
     * @param $id
     * @return Response
     */
    public function reportDetails($id): Response
    {
        $entity = $this->getDoctrine()->getRepository(EmployeeBoard::class)->find($id);
        $employeeDistricts = $entity->getEmployee()->getDistrict();

        $districtsId = [];

        foreach ($employeeDistricts as $employeeDistrict){
            $districtsId[]=$employeeDistrict->getId();
        }

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
     *
     * @Route("/{id}/report-summary", methods={"GET"}, name="kpi_summary_report")
     * @param EmployeeBoard $board
     * @return Response
     */
    public function reportSummary(EmployeeBoard $board): Response
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
        return $this->render('@TerminalbdKpi/employeeboard/report/summary.html.twig', [
            'board' => $board,
            'entities' => $marks,
            'totalObtainMark' => $totalObtainMark,
            'totalActualMark' => $totalActualMark,
        ]);

    }

    /**
     *
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
     *
     * @Route("/{id}/report-sales-achivement/{mode}", defaults={"mode" = null}, methods={"GET"}, name="kpi_report_sales_achivement")
     * @param EmployeeBoard $entity
     * @param $mode
     * @param Request $request
     * @return Response
     */
    public function salesAchivementSummary(EmployeeBoard $entity, $mode, Request $request): Response
    {
        $locations = $entity->getEmployee()->getDistrict();
        $locationsId = array();
        if(!empty($locations)){
            foreach ($locations as $location){
                $locationsId[] = $location->getId();
            }
        }

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
        $outstanding = $this->getDoctrine()->getRepository(AgentOutstanding::class)->getLocationWiseOutstanding($locationsId, $entity);
        $docSale = $this->getDoctrine()->getRepository(AgentDocSaleCollection::class)->getLocationWiseDocSales($locationsId, $entity);
        $individualTeamMemberMarks = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getIndividualTeamMemberMarks($employeeArrs, $parameter, $entity);

        $dCategoryUpgrade = $this->getDoctrine()->getRepository(AgentCategory::class)->getAgentWithDInDecember($entity);
        $cCategoryUpgrade = $this->getDoctrine()->getRepository(AgentCategory::class)->getAgentWithCInDecember($entity);
        $twentyPercentGrowthAgentSalesDetails = $this->getDoctrine()->getRepository(AgentOrder::class)->getTwentyPercentGrowthAgentSalesDetails($entity, $locationsId);

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
                'twentyPercentGrowthAgentSalesDetails' => $twentyPercentGrowthAgentSalesDetails,
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
                'twentyPercentGrowthAgentSalesDetails' => $twentyPercentGrowthAgentSalesDetails,
                'docSale' => $docSale,
                'individualTeamMemberMarks' => $individualTeamMemberMarks,

            ]);

            $fileName = $request->get('_route') . '_' . $entity->getEmployee()->getName() . '_' . $entity->getMonth() . '_' . $entity->getYear() . '_' . time() . '.xls';


            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachement; filename=$fileName");

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
                'twentyPercentGrowthAgentSalesDetails' => $twentyPercentGrowthAgentSalesDetails,
                'docSale' => $docSale,
                'individualTeamMemberMarks' => $individualTeamMemberMarks,

            ]);
        }

    }


    /**
     * @Route("/{id}/approve", methods={"GET"}, name="kpi_approve")
     * @param EmployeeBoard $employeeBoard
     * @return Response
     */
    public function approve(EmployeeBoard $employeeBoard): Response
    {

        $entities = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(['level' => 1,'status' => 1]);
        
        $em = $this->getDoctrine()->getManager();
        
        $em->getRepository(EmployeeBoardAttribute::class)->insertMarkDistribution($employeeBoard,$entities); //update Actual mark & obtain mark

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
     */
    public function updateCustomerDevelopment(EmployeeBoardAttribute $boardAttribute, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $numberOfReports = $request->query->get('numberOfReports');

        if ($boardAttribute->getAttribute()->getSlug() === 'poultry-antibiotic-free-farm-report' || $boardAttribute->getAttribute()->getSlug() === 'poultry-less-costing-farm-report' || $boardAttribute->getAttribute()->getSlug() === 'aqua-less-costing-farm-report'){
            if ($numberOfReports >= 4){
                $boardAttribute->setMark(4);
            }elseif ($numberOfReports < 4 && $numberOfReports > 0){
                $boardAttribute->setMark($numberOfReports * 1);
            }else{
                $boardAttribute->setMark(0);
            }
        }elseif ($boardAttribute->getAttribute()->getSlug() === 'monthly-new-farm-introduce-to-nourish-family-5-per-month' || $boardAttribute->getAttribute()->getSlug() ==='5-cattle-included-per-month-in-your-head' || $boardAttribute->getAttribute()->getSlug() === 'monthly-new-fish-farm-introduce-to-nourish-family-5-per-month' || $boardAttribute->getAttribute()->getSlug() === 'cattle-less-costing-farm-report' || $boardAttribute->getAttribute()->getSlug() === 'monthly-new-cattle-farm-introduce-to-nourish-family-5-per-month'){
            if ($numberOfReports >= 5){
                $boardAttribute->setMark(5);
            }elseif ($numberOfReports < 5 && $numberOfReports > 0){
                $boardAttribute->setMark($numberOfReports * 1);
            }else{
                $boardAttribute->setMark(0);
            }
        }elseif ($boardAttribute->getAttribute()->getSlug() === 'monthly-new-fish-agent-sub-agent-introduce-to-nourish-family-1-per-month'){
            if ($numberOfReports >= 2){
                $boardAttribute->setMark(2);
            }elseif ($numberOfReports < 2 && $numberOfReports > 0){
                $boardAttribute->setMark($numberOfReports * 1);
            }else{
                $boardAttribute->setMark(0);
            }
        }elseif ($boardAttribute->getAttribute()->getSlug() === 'monthly-new-cattle-agent-sub-agent-introduce-to-nourish-family-2-per-month'){
            if ($numberOfReports >= 3){
                $boardAttribute->setMark(3);
            }elseif ($numberOfReports < 3 && $numberOfReports > 0){
                $boardAttribute->setMark($numberOfReports * 1);
            }else{
                $boardAttribute->setMark(0);
            }
        }
        $boardAttribute->setAchieveReport($numberOfReports);
        $em->flush();

        return new JsonResponse($boardAttribute->getMark());
    }
}
