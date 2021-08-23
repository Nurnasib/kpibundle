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
use Terminalbd\KpiBundle\Entity\Setting;
use Terminalbd\KpiBundle\Form\SettingFormType;
use Terminalbd\KpiBundle\Form\SettingSearchFilterFormType;

/**
 * @Route("/kpi/setting")
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_DOMAIN')")
 */
class SettingController extends AbstractController
{
    private function paginate(Request $request ,$entities)
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
     * @Route("/", methods={"GET"}, name="kpi_setting")
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $entities = $this->getDoctrine()->getRepository(Setting::class)->findAll();

        $form = $this->createForm(SettingSearchFilterFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()){
            $filterBy = $form->getData();
            if (count(array_keys($filterBy, null)) == count($filterBy)){
                $entities = [];
            }else{
                $entities = $this->getDoctrine()->getRepository(Setting::class)->getSearchedSetting($filterBy);
            }

        }
        $data = $this->paginate($request, $entities);
        return $this->render('@TerminalbdKpi/setting/index.html.twig',[
            'entities' => $data,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/new", methods={"GET", "POST"}, name="kpi_setting_new")
     * @param Request $request
     * @return Response
     */
    public function new(Request $request): Response
    {

        $entity = new Setting();
        $data = $request->request->all();

        $form = $this->createForm(SettingFormType::class , $entity)
            ->add('SaveAndCreate', SubmitType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity->setTerminal($this->getUser()->getTerminal()->getId());
            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'post.created_successfully');
            if ($form->get('SaveAndCreate')->isClicked()) {
                return $this->redirectToRoute('kpi_setting_new');
            }
            return $this->redirectToRoute('kpi_setting');
        }
        return $this->render('@TerminalbdKpi/setting/new.html.twig', [
            'entity' => $entity,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing Post entity.
     *
     * @Route("/{id}/edit", methods={"GET", "POST"}, name="kpi_setting_edit")
     * @param Request $request
     * @param Setting $entity
     * @return Response
     */

    public function edit(Request $request, Setting $entity): Response
    {
        $data = $request->request->all();
        $form = $this->createForm(SettingFormType::class, $entity)
            ->add('SaveAndCreate', SubmitType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'post.updated_successfully');
            $this->getDoctrine()->getRepository(ItemKeyValue::class)->insertSettingKeyValue($entity,$data);
            if ($form->get('SaveAndCreate')->isClicked()) {
                return $this->redirectToRoute('kpi_setting_edit', ['id' => $entity->getId()]);
            }
            return $this->redirectToRoute('kpi_setting');
        }
        return $this->render('@TerminalbdKpi/setting/new.html.twig', [
            'entity' => $entity,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Deletes a Setting entity.
     *
     * @Route("/{id}/delete", methods={"GET"}, name="kpi_setting_delete")
     * @param $id
     * @return Response
     */
    public function delete($id): Response
    {
        $entity = $this->getDoctrine()->getRepository(Setting::class)->find($id);
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
        $this->addFlash('success', 'post.deleted_successfully');
        return new Response('Success');
    }
}
