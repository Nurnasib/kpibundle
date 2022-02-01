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

use App\Entity\Admin\Location;
use App\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\EmployeeBoardAttribute;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Entity\SetupMatrix;
use Terminalbd\KpiBundle\Form\EmployeeSetupFormType;

/**
 * @Route("/kpi/employee-setup")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */

class EmployeeSetupController extends AbstractController
{

    /**
     * @Route("/", name="kpi_setup")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function index() {

        /* @var $user User */
        $user = $this->getUser();
        $entities = $this->getDoctrine()->getRepository(EmployeeSetup::class)->getEmployeeList($user);
        return $this->render('@TerminalbdKpi/setup/index.html.twig',['entities'=> $entities]);
    }

    /**
     * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_KPI_USER') or is_granted('ROLE_DOMAIN')")
     * @Route("/new", methods={"GET", "POST"}, name="kpi_setup_new")
     * @param Request $request
     * @return Response
     */
    public function new(Request $request): Response
    {

        $entity = new EmployeeSetup();

        $form = $this->createForm(EmployeeSetupFormType::class , $entity,['user'=>$this->getUser()])
            ->add('SaveAndCreate', SubmitType::class);
        $form->handleRequest($request);
        $data = $request->request->all();
        if ($form->isSubmitted() && $form->isValid()) {
            $employee = $data['employee_setup_form']['employee'];
            $exist = $this->getDoctrine()->getRepository(EmployeeSetup::class)->findOneBy(
                array('employee' => $employee)
            );
            if (empty($exist)) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($entity);
                $em->flush();
                $designation = $entity->getEmployee()->getDesignation()->getSlug();
                if($designation == "sales-force" || $designation == "doctor"){
                    $this->getDoctrine()->getRepository(SetupMatrix::class)->processEmployeeSetup($entity);
                }
                return $this->redirectToRoute('kpi_setup');
            }
        }
        return $this->render('@TerminalbdKpi/setup/new.html.twig', [
            'setupEntity' => $entity,
            'form' => $form->createView()
        ]);
    }

    /**
     * Displays a form to edit an existing Post entity.
     *
     * @Route("/{id}/edit", methods={"GET", "POST"}, name="kpi_setup_edit")
     * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_KPI_USER') or is_granted('ROLE_DOMAIN')")
     * @param Request $request
     * @param EmployeeSetup $entity
     * @return Response
     */
    public function edit(Request $request, EmployeeSetup $entity): Response
    {

        $products = $this->getDoctrine()->getRepository(MarkChart::class)->getChildRecords('product-wise-sales-achievement');
        $workingArea =  $entity->getEmployee()->getArea();
        $entities = "";
        $locationMarks ="";
        $totalMarks = "";

        if($workingArea == "Upozila"){
            $this->getDoctrine()->getRepository(SetupMatrix::class)->processEmployeeSetup($entity);
            $entities = $this->getDoctrine()->getRepository(SetupMatrix::class)->getUpozilaMatrix($entity,'sales');
            $locationMarks = $this->getDoctrine()->getRepository(SetupMatrix::class)->itemWithLocationMatrix($entity,'sales');
            $totalMarks = $this->getDoctrine()->getRepository(SetupMatrix::class)->getUozilaMatrix($entity,'sales');
        }elseif($workingArea == "District"){
            $entities = $this->getDoctrine()->getRepository(SetupMatrix::class)->getUpozilaMatrix($entity,'district');
//            dd($entities);
            $locationMarks = $this->getDoctrine()->getRepository(SetupMatrix::class)->itemWithLocationMatrix($entity,'district');
            $totalMarks = $this->getDoctrine()->getRepository(SetupMatrix::class)->getUozilaMatrix($entity,'district');

        }elseif($workingArea == "Regional"){
            $entities = $this->getDoctrine()->getRepository(SetupMatrix::class)->getUpozilaMatrix($entity,'regional');
            $locationMarks = $this->getDoctrine()->getRepository(SetupMatrix::class)->itemWithLocationMatrix($entity,'regional');
            $totalMarks = $this->getDoctrine()->getRepository(SetupMatrix::class)->getUozilaMatrix($entity,'regional');

        }elseif($workingArea == "Zonal"){
            $entities = $this->getDoctrine()->getRepository(SetupMatrix::class)->getUpozilaMatrix($entity,'zonal');
            $locationMarks = $this->getDoctrine()->getRepository(SetupMatrix::class)->itemWithLocationMatrix($entity,'zonal');
            $totalMarks = $this->getDoctrine()->getRepository(SetupMatrix::class)->getUozilaMatrix($entity,'zonal');

        }

        return $this->render('@TerminalbdKpi/setup/edit.html.twig', [
            'setup' => $entity,
            'entities' => $entities,
            'matrixArr' => $locationMarks,
            'products' => $products,
            'totalMarks' => $totalMarks,
        ]);
    }


    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/delete", methods={"GET"}, name="kpi_setup_delete")
     * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_KPI_USER') or is_granted('ROLE_DOMAIN')")
     * @param $id
     * @return Response
     */
    public function delete($id): Response
    {
        $entity = $this->getDoctrine()->getRepository(EmployeeSetup::class)->find($id);
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
        $this->addFlash('success', 'post.deleted_successfully');
        return new Response('Success');
    }


    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/{upozila}/delete-matrix", methods={"GET"}, name="kpi_setup_delete_matrix")
     * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_KPI_USER') or is_granted('ROLE_DOMAIN')")
     * @param $id
     * @param $upozila
     * @return Response
     */
    public function deleteMatrix($id,$upozila): Response
    {
        $entities = $this->getDoctrine()->getRepository(SetupMatrix::class)->findBy(array('employeeSetup'=>$id,'upozila'=>$upozila));
        foreach ($entities as $entity):
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
        endforeach;
        $this->addFlash('success', 'post.deleted_successfully');
        return new Response('Success');
    }


    /**
     * Status a Setting entity.
     *
     * @Route("/{id}/status", methods={"GET"}, name="kpi_setup_status")
     * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_KPI_USER') or is_granted('ROLE_DOMAIN')")
     * @param $id
     * @return Response
     */
    public function status($id): Response
    {
        $entity = $this->getDoctrine()->getRepository(EmployeeSetup::class)->find($id);
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
        $this->addFlash('success', 'post.status_successfully');
        return new Response('Success');
    }

    /**
     * Status a Setting entity.
     *
     * @Route("/{setup}/{upozila}/sales-matrix", methods={"GET"}, name="kpi_setup_sales_matrix")
     * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_KPI_USER') or is_granted('ROLE_DOMAIN')")
     * @param $setup
     * @param $upozila
     * @return Response
     */
    public function salesMatrix($setup , $upozila): Response
    {
        if($_REQUEST['status'] == 'true'){

            $this->getDoctrine()->getRepository(SetupMatrix::class)->insertUpdateSalesPrice($setup,$upozila);
        }else{
            $this->getDoctrine()->getRepository(SetupMatrix::class)->deleteRow($setup,$upozila);

        }
        $this->addFlash('success', 'post.status_successfully');
        return new Response('Success');
    }

}
