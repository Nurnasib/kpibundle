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

use App\Entity\Core\Setting;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Form\MarkChartFormType;


/**
 * @Route("/kpi/mark-chart")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_DOMAIN')")
 */
class MarkChartController extends AbstractController
{
    /**
     * @Route("/", methods={"GET"}, name="kpi_markchart")
     */
    public function index(): Response
    {
        $entities = $this->getDoctrine()->getRepository(MarkChart::class)->findAll();
        return $this->render('@TerminalbdKpi/markchart/index.html.twig',['entities' => $entities]);
    }

    /**
     * @Route("/new", methods={"GET", "POST"}, name="kpi_markchart_new")
     * @param Request $request
     * @return Response
     */
    public function new(Request $request): Response
    {

        $entity = new MarkChart();
        $terminal = $this->getUser()->getTerminal();
        $markRepo = $this->getDoctrine()->getRepository(MarkChart::class);
        $form = $this->createForm(MarkChartFormType::class, $entity,array('terminal' => $terminal,'markRepo' => $markRepo))
            ->add('SaveAndCreate', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity->setTerminal($this->getUser()->getTerminal()->getId());
            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'post.created_successfully');
            if ($form->get('SaveAndCreate')->isClicked()) {
                return $this->redirectToRoute('kpi_markchart_new');
            }
            return $this->redirectToRoute('kpi_markchart');
        }
        return $this->render('@TerminalbdKpi/markchart/new.html.twig', [
            'entity' => $entity,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing Post entity.
     *
     * @Route("/{id}/edit", methods={"GET", "POST"}, name="kpi_markchart_edit")
     * @param Request $request
     * @param MarkChart $entity
     * @return Response
     */
    public function edit(Request $request, MarkChart $entity): Response
    {
        $data = $request->request->all();
        $terminal = $this->getUser()->getTerminal();
        $markRepo = $this->getDoctrine()->getRepository(MarkChart::class);
        $form = $this->createForm(MarkChartFormType::class, $entity,array('terminal' => $terminal,'markRepo' => $markRepo))
            ->add('SaveAndCreate', SubmitType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'post.updated_successfully');
             if ($form->get('SaveAndCreate')->isClicked()) {
                return $this->redirectToRoute('kpi_markchart');
            }
            return $this->redirectToRoute('kpi_markchart');
        }
        return $this->render('@TerminalbdKpi/markchart/new.html.twig', [
            'entity' => $entity,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/delete", methods={"GET"}, name="kpi_markchart_delete")
     * @param $id
     * @return Response
     */
    public function delete($id): Response
    {
        $entity = $this->getDoctrine()->getRepository(MarkChart::class)->find($id);
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
        $this->addFlash('success', 'post.deleted_successfully');
        return new Response('Success');
    }

    /**
     *
     * @Route("/markchart-matrix", methods={"GET"}, name="kpi_markchart_matrix")
     * @param Request $request
     * @return Response
     */
    public function markChartMatrix(Request $request)
    {
        $format = $request->query->get('slug');
        $report = $this->getDoctrine()->getRepository(Setting::class)->findOneBy(['slug'=> $format]);
        $result = $this->getDoctrine()->getRepository(MarkChart::class)->groupReportMode($format);
        $arrayData = [];
        /* @var MarkChart $boardAttribute */
        foreach ($result as $boardAttribute) {
            if ($boardAttribute->getParent()->getParent()){
                $arrayData[$boardAttribute->getParent()->getParent()->getId()][$boardAttribute->getParent()->getId()][] = $boardAttribute;
            }
        }
        $entities = $this->getDoctrine()->getRepository(Setting::class)->getChildRecords('report-mode');
        return $this->render('@TerminalbdKpi/markchart/kpi-format.html.twig',[
            'arrayData' => $arrayData,
            'modes' => $entities,
            'report' => $report
        ]);

    }


    /**
     *
     * @Route("/{id}/update-ordering", methods={"GET"}, name="kpi_markchart_format_ordering")
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function updateOrdering(Request $request, $id)
    {
        $sortValue = $request->query->get('position');
        $entity = $this->getDoctrine()->getRepository(MarkChart::class)->findOneBy(['id' => $id]);
        $entity->setOrdering((int)$sortValue);
        $em = $this->getDoctrine()->getManager();

        $em->persist($entity);
        $em->flush();

        return new JsonResponse(array('sotring'=>$entity->getOrdering()));
    }


}
