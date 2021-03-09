<?php


namespace Terminalbd\KpiBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminalbd\CrmBundle\Entity\AntibioticFreeFarm;
use Terminalbd\CrmBundle\Entity\ChickLifeCycle;
use Terminalbd\CrmBundle\Entity\CostBenefitAnalysisForLessCostingFarm;
use Terminalbd\CrmBundle\Entity\DiseaseMapping;
use Terminalbd\CrmBundle\Entity\FarmerTrainingReport;
use Terminalbd\CrmBundle\Entity\FcrDetails;
use Terminalbd\CrmBundle\Entity\LayerLifeCycle;
use Terminalbd\CrmBundle\Entity\LayerPerformanceDetails;
use Terminalbd\CrmBundle\Entity\NewFarmerIntroduce\FarmerIntroduceDetails;
use Terminalbd\CrmBundle\Entity\NewFarmerTouch\FarmerTouchReport;
use Terminalbd\KpiBundle\Entity\EvaluationCriteria;
use Terminalbd\KpiBundle\Form\EvaluationCriteriaFormType;

/**
 * Class EvaluationCriteriaController
 * @package Terminalbd\KpiBundle\Controller
 * @Route("/kpi/evaluation-criteria")
 */
class EvaluationCriteriaController extends AbstractController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/", name="evaluation_criteria_index")
     */
    public function index()
    {
        $entities = [];
        $entities = $this->getDoctrine()->getRepository(EvaluationCriteria::class)->findAll();

        return $this->render('@TerminalbdKpi/evaluationCriteria/index.html.twig',[
            'entities' => $entities
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/create", name="evaluation_criteria_create")
     */
    public function create(Request $request, TranslatorInterface $translator)
    {
        $ec = new EvaluationCriteria();

        $form = $this->createForm(EvaluationCriteriaFormType::class, $ec);
        $form->handleRequest($request);

        if ($form->isSubmitted()){

            $em = $this->getDoctrine()->getManager();

            $ec->setCreatedAt(new \DateTime('now'));
            $em->persist($ec);
            $em->flush();
            $this->addFlash('success', $translator->trans('Data added successfully into Database!'));
            return $this->redirectToRoute('evaluation_criteria_index');

        }
        return $this->render('@TerminalbdKpi/evaluationCriteria/create.html.twig',[
            'form' => $form->createView()
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/edit", name="evaluation_criteria_edit")
     */
    public function edit(Request $request, TranslatorInterface $translator)
    {
        $id = $request->query->get('id');
        $entity = $this->getDoctrine()->getRepository(EvaluationCriteria::class)->find($id);
        $form = $this->createForm(EvaluationCriteriaFormType::class, $entity);

        $form->handleRequest($request);
        if ($form->isSubmitted()){
            $em = $this->getDoctrine()->getManager();

            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', $translator->trans('Record has been updated successfully!'));

            return $this->redirectToRoute('evaluation_criteria_index');
        }
//        dd($entity);
        return $this->render('@TerminalbdKpi/evaluationCriteria/create.html.twig',[
            'form' => $form->createView()
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/delete", name="evaluation_criteria_delete")
     */
    public function delete(Request $request, TranslatorInterface $translator)
    {
        $id = $request->query->get('id');
        $entity = $this->getDoctrine()->getRepository(EvaluationCriteria::class)->find($id);
        $em = $this->getDoctrine()->getManager();

        $em->remove($entity);
        $em->flush();
        $this->addFlash('success', $translator->trans('Record has been deleted!'));

        return $this->redirectToRoute('evaluation_criteria_index');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/evaluation-criteria-monthly-report", name="evaluation_criteria_monthly_report")
     */
    public function monthlyTotalReport()
    {
        $totalReports = [];
        $entities = [];
        $filterBy = [];
        $filterBy['employeeId'] = 21;
        $filterBy['monthStart'] = '2021-02-01';
        $filterBy['monthEnd'] = '2021-02-28';

        $totalReports['totalFcrAfterSale'] = (int) $this->getDoctrine()->getRepository(FcrDetails::class)->getMonthlyFcrAfterSaleTotalReport($filterBy);
        $totalBroilerBeforeSale = (int) $this->getDoctrine()->getRepository(FcrDetails::class)->getMonthlyBroilerBeforeSaleTotalReport($filterBy);
        $totalLayerPerformance = (int) $this->getDoctrine()->getRepository(LayerPerformanceDetails::class)->getMonthlyLayerPerformanceTotalReport($filterBy);

        $totalReports['broilerBeforeSaleAndLayerPerformance'] = $totalBroilerBeforeSale + $totalLayerPerformance;

        $totalBroilerLifeCycle = (int) $this->getDoctrine()->getRepository(ChickLifeCycle::class)->getMonthlyBroilerLifeCycleTotalReport($filterBy);
        $totalLayerLifeCycle = (int) $this->getDoctrine()->getRepository(LayerLifeCycle::class)->getMonthlyLayerLifeCycleTotalReport($filterBy);

        $totalReports['broilerLifeCycleAndLayerLifeCycle'] = $totalBroilerLifeCycle + $totalLayerLifeCycle;

        $totalReports['newFarmInformationOrSurvey'] = (int) $this->getDoctrine()->getRepository(FarmerTouchReport::class)->getMonthlyNewfarmInformationOrSurveyTotalReport($filterBy);
        $totalReports['lessCostingFarmOrSkillFarmDevelop'] = (int) $this->getDoctrine()->getRepository(CostBenefitAnalysisForLessCostingFarm::class)->getMonthlyLessCostingFarmOrSkillFarmDevelopTotalReport($filterBy);
        $totalReports['anitibioticFreeFarm'] = (int) $this->getDoctrine()->getRepository(AntibioticFreeFarm::class)->getMonthlyAntibioticFreeFarmTotalReport($filterBy);
        $totalReports['farmersTrainingProgram'] = (int) $this->getDoctrine()->getRepository(FarmerTrainingReport::class)->getMonthlyfarmersTrainingProgramTotalReport($filterBy);
        $totalReports['newFarmIntroduce'] = (int) $this->getDoctrine()->getRepository(FarmerIntroduceDetails::class)->getMonthlyNewFarmerIntroduceTotalReport($filterBy);
        $totalReports['troubleshootingAndDiseases'] = (int) $this->getDoctrine()->getRepository(DiseaseMapping::class)->getMonthlyTroubleshootingAndDiseasesMappingTotalReport($filterBy);

        $entities = $this->getDoctrine()->getRepository(EvaluationCriteria::class)->getEvaluationCriteria();

       return $this->render('@TerminalbdKpi/evaluationCriteria/report-evaluation-criteria.html.twig',[
           'totalReports' => $totalReports,
           'entities' => $entities,
       ]);
    }
}