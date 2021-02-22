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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeBoardSubAttribute;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Form\EmployeeBoardFormType;


/**
 * @Route("/kpi/employee-board")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class EmployeeBoardController extends AbstractController
{
    /**
     * @Route("/", methods={"GET"}, name="kpi_employee_board")
     */
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $entities = $this->getDoctrine()->getRepository(EmployeeBoard::class)->getEmployeeBoardList($user);
        return $this->render('@TerminalbdKpi/employeeboard/index.html.twig',['entities' => $entities]);
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
        $marks = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->EmployeeBoardMarks($entity);
        $em->getRepository(EmployeeBoardAttribute::class)->insertMarkDistribution($entity->getEmployeeSetup(),$entity,$entities);
        return $this->render('@TerminalbdKpi/employeeboard/new.html.twig', [
            'entity' => $entity,
            'marks' => $marks,
            'entities' => $entities,
        ]);
    }

    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/delete", methods={"GET"}, name="kpi_employee_board_delete")
     * @Security("is_granted('ROLE_KPI') or is_granted('ROLE_DOMAIN')")
     */
    public function delete($id): Response
    {
        $entity = $this->getDoctrine()->getRepository(EmployeeBoard::class)->find($id);
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
        $this->addFlash('success', 'post.deleted_successfully');
        return new Response('Success');
    }

    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/attribute-update", methods={"GET"}, name="kpi_employee_board_attribute_update")
     * @Security("is_granted('ROLE_KPI') or is_granted('ROLE_DOMAIN')")
     */
    public function attributeUpdate($id): Response
    {
        $mark = $_REQUEST['mark'];
        $attribute = $this->getDoctrine()->getRepository(MarkChart::class)->find($mark);
        $entity = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->find($id);
        $em = $this->getDoctrine()->getManager();
        $entity->setMarkDistribution($attribute);
        $entity->setActualMark($attribute->getMark());
        $em->flush();
        return new Response($attribute->getMark());
    }

    /**
     *
     * @Route("/{id}/report-details", methods={"GET"}, name="kpi_details_report")
     */
    public function reportDetails($id): Response
    {
        $entity = $this->getDoctrine()->getRepository(EmployeeBoard::class)->find($id);
        $entities = $this->getDoctrine()->getRepository(MarkChart::class)->findBy(['level' => 1,'status' => 1]);
        $marks = $this->getDoctrine()->getRepository(EmployeeBoardAttribute::class)->EmployeeBoardMarks($entity);
        return $this->render('@TerminalbdKpi/employeeboard/report/details.html.twig', [
            'entity' => $entity,
            'marks' => $marks,
            'entities' => $entities,
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
            'entities' => $marks,
        ]);

    }

    /**
     *
     * @Route("/{id}/report-sales-achivement", methods={"GET"}, name="kpi_report_sales_achivement")
     */
    public function salesAchivementSummary($id): Response
    {

        $entity = $this->getDoctrine()->getRepository(EmployeeBoard::class)->find($id);
        $marks = $this->getDoctrine()->getRepository(EmployeeBoardSubAttribute::class)->findBy(array('employeeBoard'=>$id));
        return $this->render('@TerminalbdKpi/employeeboard/report/salesDetails.html.twig', [
            'entity' => $entity,
            'entities' => $marks,
        ]);

    }


}
