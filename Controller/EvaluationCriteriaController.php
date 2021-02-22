<?php


namespace Terminalbd\KpiBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
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
            /*            $data= $form->getData();
                        dd($data);*/
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
}