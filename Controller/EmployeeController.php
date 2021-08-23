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
use App\Entity\Core\ItemKeyValue;
use App\Entity\Core\Setting;
use App\Entity\User;
use App\Repository\Core\SettingRepository;
use App\Repository\UserRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminalbd\KpiBundle\Entity\EmployeeDistrictHistory;
use Terminalbd\KpiBundle\Entity\EmployeeReportFormatHistory;
use Terminalbd\KpiBundle\Form\EditEmployeeFormType;
use Terminalbd\KpiBundle\Form\EmployeeFilterFormType;
use Terminalbd\KpiBundle\Form\EmployeeFormType;


/**
 * @Route("/kpi/employee")
 */
class EmployeeController extends AbstractController
{
    public function paginate(Request $request ,$entities)
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
     * @Route("/list", methods={"GET"}, name="kpi_employee")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_CRM')")
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     */
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $lineManagers = $userRepository->getLineManager();
        $user = $this->getUser();

        $entities = $this->getDoctrine()->getRepository(User::class)->findBy(['userMode' => 'KPI']);

        $searchForm = $this->createForm(EmployeeFilterFormType::class,null, ['lineManagers' => $lineManagers]);

        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted()){
            $filterBy = $searchForm->getData();
            if (count(array_keys($filterBy, null)) == count($filterBy)){
                $entities = [];
            } else{
                $entities = $this->getDoctrine()->getRepository(User::class)->getSearchedEmployee($filterBy);
            }
        }
        $data = $this->paginate($request, $entities);
        return $this->render('@TerminalbdKpi/employee/index.html.twig',[
            'entities' => $data,
            'form' => $searchForm->createView()
        ]);
    }


    /**
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_CRM')")
     * @Route("/register", methods={"GET", "POST"}, name="kpi_employee_register")
     * @param Request $request
     * @return Response
     */
    public function register(Request $request): Response
    {
//        $passwordEncoder = UserPasswordEncoderInterface::class;
        $user = new User();
        $data = $request->request->all();
        $terminal = $this->getUser()->getTerminal();
        $userRepo = $this->getDoctrine()->getRepository(User::class);
        $locationRepo = $this->getDoctrine()->getRepository(Location::class);
        $form = $this->createForm(EmployeeFormType::class, $user, array('terminal' => $terminal,'userRepo'=>$userRepo , 'locationRepo' => $locationRepo))
            ->add('SaveAndCreate', SubmitType::class);
        $form->handleRequest($request);
        $errors = $this->getErrorsFromForm($form);
        $em = $this->getDoctrine()->getManager();
        if ($form->isSubmitted() && $form->isValid()) {
            $this->get('kpi_bundle.user_manager')->setUserPassword($user, $form->get('password')->getData());
            $user->setUserMode('KPI');
            $user->setTerminal($terminal);
            $em->persist($user);
            $em->flush();
            return $this->redirectToRoute('kpi_employee');
        }
        return $this->render('@TerminalbdKpi/employee/register.html.twig', [
            'id' => 'postForm',
            'post' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_CRM')")
     * @Route("/{id}/edit", methods={"GET", "POST"}, name="kpi_employee_edit")
     * @param Request $request
     * @param User $post
     * @return Response
     */
    public function edit(Request $request ,User $post): Response
    {
        $data = $request->request->all();
//        $post = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id'=> $id]);
        $terminal = $this->getUser()->getTerminal();
        $userRepo = $this->getDoctrine()->getRepository(User::class);
        $form = $this->createForm(EditEmployeeFormType::class, $post, array('terminal' => $terminal,'userRepo' => $userRepo))
            ->add('SaveAndCreate', SubmitType::class);
        $form->remove('phone');
        $form->handleRequest($request);
        //  $errors = $this->getErrorsFromForm($form);
        if ($form->isSubmitted()) {
            $em = $this->getDoctrine()->getManager();
            $lastAssignDistrict = $this->getDoctrine()->getRepository(EmployeeDistrictHistory::class)->findOneBy(['employee' => $post], ['id' => 'DESC']);
            $lastAssignReportFormat = $this->getDoctrine()->getRepository(EmployeeReportFormatHistory::class)->findOneBy(['employee' => $post], ['id' => 'DESC']);
            $districts = $form->getData()->getDistrict();
            $reportFormat = $form->getData()->getReportMode();

            $districtNameArray = [];
            foreach ($districts as $district){
                $districtNameArray[]= $district->getName();
            }
            $districtsName = implode(', ',$districtNameArray);

            if ($lastAssignDistrict == null || $lastAssignDistrict->getDistrict() != $districtsName){
                $districtHistory = new EmployeeDistrictHistory();
                $date = new \DateTime('now');

                $districtHistory->setEmployee($post);
                $districtHistory->setDistrict($districtsName);
                $districtHistory->setMonth($date->format('F'));
                $districtHistory->setYear($date->format('Y'));
                $districtHistory->setUpdatedBy($this->getUser());
                $em->persist($districtHistory);
            }

            if ($lastAssignReportFormat == null || $lastAssignReportFormat->getReportFormat()->getId() != $reportFormat->getId()){
                $reportFormatHistory = new EmployeeReportFormatHistory();
                $date = new \DateTime('now');

                $reportFormatHistory->setEmployee($post);
                $reportFormatHistory->setReportFormat($reportFormat);
                $reportFormatHistory->setMonth($date->format('F'));
                $reportFormatHistory->setYear($date->format('Y'));
                $reportFormatHistory->setUpdatedBy($this->getUser());
                $em->persist($reportFormatHistory);
            }

            $em->flush();

            return $this->redirectToRoute('kpi_employee_edit',array('id'=> $post->getId()));
        }
        return $this->render('@TerminalbdKpi/employee/editRegister.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     * @Route("/{id}/reset-password", methods={"GET", "POST"}, name="kpi_employee_password", options={"expose"=true})
     * @param Request $request
     * @param UserRepository $userRepository
     * @param TranslatorInterface $translator
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param $id
     * @return Response
     */
    public function changeUserPassword(Request $request,UserRepository $userRepository , TranslatorInterface $translator ,UserPasswordEncoderInterface $passwordEncoder,$id): Response
    {
        $user = $this->getUser();

        /* @var $entity User */

        $entity = $userRepository->findOneBy(['terminal'=> $user->getTerminal(),'id'=>$id]);
        if($entity){
            $password = 123456;
            $entity->setPassword(
                $passwordEncoder->encodePassword(
                    $entity,
                    $password
                )
            );
            $this->getDoctrine()->getManager()->flush();
            $message = $translator->trans('data.updated_successfully');
            $this->addFlash('success', $message);
        }
        return $this->redirectToRoute('kpi_employee');

    }


    /**
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     * @Route("/{id}/reset-inline-password", methods={"GET", "POST"}, name="kpi_employee_inline_password", options={"expose"=true})
     * @param Request $request
     * @param UserRepository $userRepository
     * @param TranslatorInterface $translator
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param $id
     * @return Response
     */
    public function changeInlineUserPassword(Request $request,UserRepository $userRepository , TranslatorInterface $translator ,UserPasswordEncoderInterface $passwordEncoder,$id): Response
    {
        $data = $request->request->all();
        $id = $data['pk'];
        $user = $this->getUser();
        
        $entity = $userRepository->findOneBy(['terminal'=> $user->getTerminal(),'id'=>$id]);
        if($entity){
            $password = $data['value'];
            $entity->setPassword(
                $passwordEncoder->encodePassword(
                    $entity,
                    $password
                )
            );
            $this->getDoctrine()->getManager()->flush();
            $message = $translator->trans('data.updated_successfully');
            $this->addFlash('success', $message);
        }
        return new JsonResponse(['status' => 200]);

    }



    private function getErrorsFromForm(FormInterface $form)
    {
        $errors = array();
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }
        return $errors;
    }

    /**
     * @Route("/designation-select", name="kpi_designation_select", options={"expose"=true})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     * @param SettingRepository $settingRepository
     * @return JsonResponse
     */

    public function selectDesignation(SettingRepository $settingRepository)
    {
        $designations = $settingRepository->getDesignations();
        return New JsonResponse($designations);
    }

    /**
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     * @Route("/designation-inline-update/{id}", name="kpi_designation_inline_update", options={"expose"=true})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     */
    public function inlineUpdateDesignation(Request $request, User $user)
    {

        $data = $request->request->all();
//        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['userId' => $userId]);

        if (!$user) {
            throw $this->createNotFoundException('Unable to find User');
        }

        $designation = $this->getDoctrine()->getRepository(Setting::class)->find($data['value']);
        $user->setDesignation($designation);
        $this->getDoctrine()->getManager()->flush();
        return new JsonResponse(['status' => 200]);

    }

    /**
     * @Route("/line-manager-select", name="kpi_line_manager_select", options={"expose"=true})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     * @param UserRepository $repository
     * @return JsonResponse
     */

    public function selectLineManager(UserRepository $repository)
    {
        $lineManagers = $repository->getLineManagerForInlineUpdate();
        return new JsonResponse($lineManagers);
    }

    /**
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     * @Route("/line-manager-inline-update/{id}", name="kpi_line_manager_inline_update", options={"expose"=true})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     */
    public function inlineUpdateLineManager(Request $request, User $user)
    {

        $data = $request->request->all();
//        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['userId' => $userId]);

        if (!$user) {
            throw $this->createNotFoundException('Unable to find User');
        }

        $lineManager = $this->getDoctrine()->getRepository(User::class)->find($data['value']);
        $user->setLineManager($lineManager);
        $this->getDoctrine()->getManager()->flush();
        return new JsonResponse(['status' => 200]);

    }

    /**
     * @Route("/report-mode-select", name="kpi_report_mode_select", options={"expose"=true})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     * @param SettingRepository $repository
     * @return JsonResponse
     */

    public function selectReportMode(SettingRepository $repository)
    {
        $reportModes = $repository->getReportModes();
        return new JsonResponse($reportModes);
    }

    /**
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     * @Route("/report-mode-inline-update/{id}", name="kpi_report_mode_inline_update", options={"expose"=true})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     */
    public function inlineUpdateReportMode(Request $request, User $user)
    {

        $data = $request->request->all();
//        $user = $this->getDoctrine()->getRepository(User::class)->find();

        if (!$user) {
            throw $this->createNotFoundException('Unable to find User');
        }

        $reportMode = $this->getDoctrine()->getRepository(Setting::class)->find($data['value']);

        $lastAssignReportFormat = $this->getDoctrine()->getRepository(EmployeeReportFormatHistory::class)->findOneBy(['employee' => $user], ['id' => 'DESC']);

        if ($lastAssignReportFormat == null || $lastAssignReportFormat->getReportFormat()->getId() != $reportMode->getId()){
            $reportFormatHistory = new EmployeeReportFormatHistory();
            $date = new \DateTime('now');

            $reportFormatHistory->setEmployee($user);
            $reportFormatHistory->setReportFormat($reportMode);
            $reportFormatHistory->setMonth($date->format('F'));
            $reportFormatHistory->setYear($date->format('Y'));
            $reportFormatHistory->setUpdatedBy($this->getUser());
            $em = $this->getDoctrine()->getManager();
            $em->persist($reportFormatHistory);
        }


        $user->setReportMode($reportMode);
        $this->getDoctrine()->getManager()->flush();
        return new JsonResponse(['status' => 200]);

    }

    /**
     * @Route("/hierarchy/{mode}",defaults={"mode" = null}, name="kpi_employee_hierarchy")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     * @param UserRepository $repository
     * @param $mode
     * @param Request $request
     * @return Response
     */

    public function employeeHierarchy(UserRepository $repository, $mode, Request $request)
    {
        $data = $repository->getEmployeeHierarchy();

        if ($mode == 'pdf'){
            // Configure Dompdf according to your needs
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial');

            // Instantiate Dompdf with our options
            $dompdf = new Dompdf($pdfOptions);

            // Retrieve the HTML generated in our twig file
            $html = $this->renderView('@TerminalbdKpi/employee/employeeHierarchy-pdf.html.twig',[
                'data' => $data,
            ]);

            // Load HTML to Dompdf
            $dompdf->loadHtml($html);

            // (Optional) Setup the paper size and orientation 'portrait' or 'landscape'
            $dompdf->setPaper('legal', 'landscape');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser (force download)
            $fileName = $request->get('_route') . '-' . time();
            $dompdf->stream( $fileName .  ".pdf", [
                "Attachment" => true
            ]);
            die();
        }
        return $this->render('@TerminalbdKpi/employee/employeeHierarchy.html.twig',[
            'data' => $data,
        ]);
    }

    /**
     * @Route("/{id}/details", name="kpi_employee_details", options={"expose"=true})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     * @param User $employee
     * @return JsonResponse
     */
    public function employeeDetails(User $employee)
    {

        $html = $this->renderView('@TerminalbdKpi/employee/employeeDetails.twig',[
            'employee' => $employee,
        ]);
        return new JsonResponse(array('html'=>$html));
    }


}
