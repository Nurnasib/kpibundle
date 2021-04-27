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


/**
 * @Route("/kpi/employee-board")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class EmployeeBoardController extends AbstractController
{
    /**
     * @Route("/", methods={"GET"}, name="kpi_employee_board", options={"expose"=true})
     */
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $entities = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getEmployeeBoardList($user);
        return $this->render('@TerminalbdKpi/employeeboard/index.html.twig',['entities' => $entities]);
    }

    /**
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_KPI') or is_granted('ROLE_DOMAIN')")
     * @Route("/new", methods={"GET", "POST"}, name="kpi_board_new")
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
            $employee = $data['employee_board_form']['employee'];
            $monthYear = explode(',', $data['employee_board_form']['monthYear']);
            $month = $monthYear[0];
            $year = $monthYear[1];


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
     */
    public function delete($id): Response
    {
        $entity = $this->getDoctrine()->getRepository(EmployeeBoard::class)->find($id);
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
        $this->addFlash('success', 'post.deleted_successfully');
        return $this->redirectToRoute('kpi_employee_board');
    }

    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/attribute-update", methods={"GET"}, name="kpi_employee_board_attribute_update")
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
     */
    public function reportSummary($id): Response
    {

        $entity = $this->getDoctrine()->getRepository(EmployeeBoard::class)->find($id);
        $marks = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->EmployeeBoardSummaryReport($entity);
        return $this->render('@TerminalbdKpi/employeeboard/report/summary.html.twig', [
            'entity' => $entity,
            'board' => $entity,
            'entities' => $marks,
        ]);

    }
    /**
     *
     * @Route("/{id}/report-summary-print", methods={"GET"}, name="kpi_summary_report_print")
     */
    public function reportSummaryPdf($id): Response
    {

        $entity = $this->getDoctrine()->getRepository(EmployeeBoard::class)->find($id);
        $marks = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->EmployeeBoardSummaryReport($entity);
        return $this->render('@TerminalbdKpi/employeeboard/report/summary-print.html.twig', [
            'entity' => $entity,
            'board' => $entity,
            'entities' => $marks,
        ]);

    }

    /**
     *
     * @Route("/{id}/report-sales-achivement", methods={"GET"}, name="kpi_report_sales_achivement")
     */
    public function salesAchivementSummary(EmployeeBoard $entity): Response
    {
        $reportModes = ['poultry-service','aqua-service','cattle-service'];
        $employeeReportMode = $entity->getEmployee()->getReportMode()->getSlug();
        if (in_array($employeeReportMode, $reportModes)){

            $activityService = $this->getDoctrine()->getRepository(MarkChart::class)->findOneBy(['slug' => 'service']);

            $records = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->findBy(['employeeBoard' => $entity->getId(), 'activity' => $activityService ]);
            return $this->render('@TerminalbdKpi/employeeboard/report/salesDetailsPAC.html.twig',[
                'entity' => $entity,
                'records' => $records,
            ]);
        }



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

        $attributes = $this->getDoctrine()->getRepository(MarkChart::class)->getAttributesForSummary();

        $feedAndGrowth = $this->getDoctrine()->getRepository(EmployeeBoardSubAttribute::class)->getKpiSummaryForFeedAndGrowth($entity);
        $outstanding = $this->getDoctrine()->getRepository(AgentOutstanding::class)->getLocationWiseOutstanding($locationsId, $entity->getYear(), $entity->getMonth());
        $docSale = $this->getDoctrine()->getRepository(AgentDocSaleCollection::class)->getLocationWiseDocSales($locationsId, $entity->getYear(), $entity->getMonth());
        $districtAchievement = $this->getDoctrine()->getRepository(DistrictOrder::class)->getDistrictAchievement($locationsId, $entity->getYear(), $entity->getMonth());
        $regionalAchievement = $this->getDoctrine()->getRepository(DistrictOrder::class)->getRegionalAchievement($locationsId, $entity->getYear(), $entity->getMonth());
        $individualTeamMemberMarks = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->getIndividualTeamMemberMarks($employeeArrs,$parameter, $entity->getYear(), $entity->getMonth());


        $dCategoryUpgrade = $this->getDoctrine()->getRepository(AgentCategory::class)->getAgentWithDInDecember($entity);
        $cCategoryUpgrade = $this->getDoctrine()->getRepository(AgentCategory::class)->getAgentWithCInDecember($entity);
        $twentyPercentGrowthAgentSalesDetails = $this->getDoctrine()->getRepository(AgentOrder::class)->getTwentyPercentGrowthAgentSalesDetails($entity, $locationsId);
        return $this->render('@TerminalbdKpi/employeeboard/report/salesDetails.html.twig', [
            'entity' => $entity,
            'feedAndGrowth' => $feedAndGrowth,
            'attributes' => $attributes,
            'outstanding' => $outstanding,
            'docSale' => $docSale,
            'districtAchievement' => $districtAchievement,
            'regionalAchievement' => $regionalAchievement,
            'individualTeamMemberMarks' => $individualTeamMemberMarks,
            'dCategoryUpgrade' => $dCategoryUpgrade,
            'cCategoryUpgrade' => $cCategoryUpgrade,
            'twentyPercentGrowthAgentSalesDetails' => $twentyPercentGrowthAgentSalesDetails,
        ]);

    }


    /**
     * Update Status.
     * @Route("/{id}/approve", methods={"GET"}, name="kpi_approve")
     */
    public function approve(EmployeeBoard $employeeBoard): Response
    {
        $em = $this->getDoctrine()->getManager();
        $employeeBoard->setApprovedBy($this->getUser());
        $em->persist($employeeBoard);
        $em->flush();
        return $this->redirectToRoute('kpi_employee_board');
    }

}
