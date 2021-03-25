<?php


namespace Terminalbd\KpiBundle\Controller;


use App\Entity\Core\Agent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\CrmBundle\Form\SearchFilterFormType;
use Terminalbd\KpiBundle\Entity\AgentCategory;
use Terminalbd\KpiBundle\Entity\AgentGradeStandard;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\DocumentUpload;

/**
 * Class AgentCategoryController
 * @package Terminalbd\KpiBundle\Controller\AgentCategoryController
 * @Route("/kpi/agent-category", name="")
 */
class AgentCategoryController extends AbstractController
{
    /**
     * @return string
     * @Route("/", name="kpi_agent_category_index")
     */
    public function index()
    {
        $agentCategoryByMonthYear = $this->getDoctrine()->getRepository(AgentCategory::class)->getAllAgentWithGrade();
        return $this->render('@TerminalbdKpi/agentCategory/index.html.twig', [
            'agentCategoryByMonthYear' => $agentCategoryByMonthYear
        ]);

    }
    /**
     * @Route("/{id}/insert-agent-category", name="kpi_insert_agent_category")
     */
    public function insertAgentCategory(DocumentUpload $file)
    {
        $addedId = [];

        $em = $this->getDoctrine()->getManager();
        $monthYear = explode(',', $file->getMonthYear());
        $monthName = $monthYear[0];
        $year = $monthYear[1];
        $date = "01 $monthName $year";
        $month = date('d-m-Y', strtotime($date));

        $returnValue = $this->getDoctrine()->getRepository(AgentCategory::class)->insertAgentOrderInAgentCategory($monthName, $year, $file);
        if($returnValue){
/*            $file->setStatus(3);
            $em->persist($file);
            $em->flush();*/

            $this->addFlash('success', 'Data has been inserted successfully into Database!');
            return $this->redirectToRoute('kpi_file_upload_index');
        }else{
            $this->addFlash('error', 'Something Wrong!');
            return $this->redirectToRoute('kpi_file_upload_index');
        }

    }

    private function getGradeObj($totalQuantity)
    {
        $grades = $this->getDoctrine()->getRepository(AgentGradeStandard::class)->findAll();
        foreach ($grades as $grade){
            if($totalQuantity >= $grade->getQuantity()){
                return $grade;
            }
        }
    }

    /**
     * @Route("/month-wise-agent-grade", name="kpi_month_wise_agent_grade")
     */
    public function monthWiseAgentGrade(Request $request)
    {
        $entities = [];
        $prevYear = date("Y",strtotime("-1 year"));
        $requestData = $request->query->get('monthYear');
        $filterBy = array('month'=>Date('F', strtotime(date('F') . " last month")),'year'=>date('Y'));
        if($requestData){
            $explode= explode(',',$requestData);
            $filterBy = array('month'=>$explode[0],'year'=>$explode[1]);
            $prevYear = $filterBy['year']-1;
        }

        $entities = $this->getDoctrine()->getRepository(AgentCategory::class)->getAgentGradeMonthWise($filterBy);
        $prevYearGradeAndAverage = $this->getDoctrine()->getRepository(AgentCategory::class)->getPreviousYearCategoryAndAverage($prevYear);

        return $this->render('@TerminalbdKpi/agentCategory/month-wise-agent-grade.html.twig', [
            'entities' => $entities,
            'selectedMonthYear' => $requestData,
            'prevYearGradeAndAverage' => $prevYearGradeAndAverage,
        ]);
    }

}