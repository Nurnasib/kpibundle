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
     * @Route("/", name="agent_category_index")
     */
    public function index()
    {
        $agentCategoryByMonthYear = $this->getDoctrine()->getRepository(AgentCategory::class)->getAllAgentWithCategory();
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
//        dd($this->avgQuantity());

        $agentOrders = $this->getDoctrine()->getRepository(AgentOrder::class)->getAgentWiseTotalProductSales($monthName, $year);

        foreach ($agentOrders as $agentOrder){
            $findAgent = $this->getDoctrine()->getRepository(Agent::class)->findOneBy(['id' => $agentOrder['agentId']]);
            $agentCategory = new AgentCategory();

            $agentCategory->setAgent($findAgent?$findAgent:null);
            $agentCategory->setQuantity($agentOrder['totalQty']);
            $agentCategory->setMonth(new \DateTime($month));
            $agentCategory->setYear($year);
            $agentCategory->setGradeStandard($this->getGradeObj($agentOrder['totalQty']));

            $em->persist($agentCategory);
            $em->flush();
            $addedId = $agentCategory->getId();
        }
        if ($addedId){
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

    private function avgQuantity()
    {
        $prevTotal = $this->getDoctrine()->getRepository(AgentCategory::class)->getPreviousAllMonthTotalQuantity();
        dd($prevTotal);

        $sql = "SELECT quantity FROM `kpi_agent_category` WHERE month = 'January' AND year=2021 AND agent_id=1838";
/*
        "SELECT quantity FROM kpi_agent_category 
WHERE 
MONTH(created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
AND agent_id = 1838"
        "SELECT SUM(quantity) AS totalQuantity FROM kpi_agent_category 
WHERE created_at BETWEEN 01-01-2021 AND MONTH(created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
"*/

/*        SELECT id, grade_standard_id, agent_id, month, year, COUNT(id), SUM(quantity) AS totalQty
FROM `kpi_agent_category`
WHERE agent_id = 1838
    AND MONTH(month) < MONTH(CURRENT_DATE)*/


    }

    /**
     * @Route("/grade-change")
     */
    public function gradeChangeMonthWise(Request $request)
    {
        $agentPrevYearTotalQuantity = [];
        $searchForm = $this->createForm(SearchFilterFormType::class);
        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted()){

        }
        $agentPrevYearTotalQuantity = $this->getDoctrine()->getRepository(AgentCategory::class)->getAgentGradeMonthWise();

        return $this->render('@TerminalbdKpi/agentCategory/agent-grade-change.html.twig', [
            'form' => $searchForm,
            'agentPrevYearTotalQuantity' => $agentPrevYearTotalQuantity,
        ]);
    }

}