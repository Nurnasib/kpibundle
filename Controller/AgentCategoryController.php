<?php


namespace Terminalbd\KpiBundle\Controller;


use App\Entity\Core\Agent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
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
     * @Route("/")
     */
    public function index()
    {
        return 'Hello';
    }
    /**
     * @Route("/{id}/insert-agent-category", name="kpi_insert_agent_category")
     */
    public function insertAgentCategory(DocumentUpload $file)
    {
        $addedId = [];

        $em = $this->getDoctrine()->getManager();
        $monthYear = explode(',', $file->getMonthYear());
        $month = $monthYear[0];
        $year = $monthYear[1];

        $agentOrders = $this->getDoctrine()->getRepository(AgentOrder::class)->getAgentWiseTotalProductSales($month, $year);
        foreach ($agentOrders as $agentOrder){
            $findAgent = $this->getDoctrine()->getRepository(Agent::class)->findOneBy(['id' => $agentOrder['agentId']]);
            $agentCategory = new AgentCategory();

            $agentCategory->setAgent($findAgent?$findAgent:null);
            $agentCategory->setQuantity($agentOrder['totalQty']);
            $agentCategory->setMonth($month);
            $agentCategory->setYear($year);
            $agentCategory->setGradeStandard($this->getGradeObj($agentOrder['totalQty']));

            $em->persist($agentCategory);
            $em->flush();
            $addedId = $agentCategory->getId();
        }
        if ($addedId){
            $file->setStatus(3);
            $em->persist($file);
            $em->flush();
            
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
}