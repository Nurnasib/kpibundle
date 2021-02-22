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
use App\Entity\User;
use App\Repository\UserRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminalbd\KpiBundle\Form\EditEmployeeFormType;
use Terminalbd\KpiBundle\Form\EmployeeFormType;


/**
 * @Route("/kpi/employee")
 */
class EmployeeController extends AbstractController
{
    /**
     * @Route("/", methods={"GET"}, name="kpi_employee")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_CRM')")
     */
    public function index(Request $request): Response
    {
        $entities = $this->getDoctrine()->getRepository(User::class)->findAll();
        return $this->render('@TerminalbdKpi/employee/index.html.twig',['entities' => $entities]);
    }


    /**
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_CRM')")
     * @Route("/register", methods={"GET", "POST"}, name="kpi_employee_register")
     */
    public function register(Request $request): Response
    {

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
     */
    public function edit(Request $request ,$id): Response
    {
        $data = $request->request->all();
        $post = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id'=> $id]);
        $terminal = $this->getUser()->getTerminal();
        $userRepo = $this->getDoctrine()->getRepository(User::class);
        $form = $this->createForm(EditEmployeeFormType::class, $post, array('terminal' => $terminal,'userRepo' => $userRepo))
            ->add('SaveAndCreate', SubmitType::class);
        $form->remove('phone');
        $form->handleRequest($request);
        //  $errors = $this->getErrorsFromForm($form);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('kpi_employee_edit',array('id'=> $post->getId()));
        }
        return $this->render('@TerminalbdKpi/employee/editRegister.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
     * @Route("/{id}/reset-password", methods={"GET", "POST"}, name="kpi_employee_password")
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



}
