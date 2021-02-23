<?php

namespace Terminalbd\KpiBundle\Controller;


use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\DocumentUpload;
use Terminalbd\KpiBundle\Entity\MarkChart;
use Terminalbd\KpiBundle\Form\FileUploadFormType;

/**
 * Class FileUploadController
 * @package Terminalbd\KpiBundle\Controller\FileUpload
 * @Route("/kpi/file-upload", name="")
 */
class FileUploadController extends AbstractController
{
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/", name="kpi_file_upload_index")
     */
    public function fileUpload(Request $request, TranslatorInterface $translator)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $uploadFile = new DocumentUpload();
        $allowFileType = ['xlsx'];

        $entities = [];
        $entities = $this->getDoctrine()->getRepository(DocumentUpload::class)->findAll();
//        dd($entities);
        $form = $this->createForm(FileUploadFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {

            $uploadedFile = $form['UploadFile']->getData();
//            dd($uploadedFile->getClientOriginalExtension());
            if (in_array($uploadedFile->getClientOriginalExtension(), $allowFileType)){
                $em = $this->getDoctrine()->getManager();
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $newFileName = $form['title']->getData() . '_' . $originalFilename . '-' . date('d-m-Y') . '-' . time() . '.' . $uploadedFile->getClientOriginalExtension();
                $uploadDir = $this->get('kernel')->getProjectDir() . '/public/uploads/excel/';

                $uploadedFile->move(
                    $uploadDir,
                    $newFileName
                );
                $uploadFile->setFileName($newFileName);
                $uploadFile->setTitle($form['title']->getData());
                $uploadFile->setMonthYear($form['monthYear']->getData());
                $em->persist($uploadFile);
                $em->flush();
                $this->addFlash('success', $translator->trans('File Uploaded Successfully!'));
                return $this->redirectToRoute('kpi_file_upload_index');
            }else{
                $this->addFlash('error', $translator->trans('Invalid File Format!'));
                return $this->redirectToRoute('kpi_file_upload_index');
            }
        }
        return $this->render('@TerminalbdKpi/fileUpload/index.html.twig', [
            'form' => $form->createView(),
            'entities' => $entities,
        ]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/insert-data", name="kpi_file_upload_insert_data")
     */
    public function insertDataFromUploadedFile(Request $request)
    {
//        $fileInfo = $request->query->all();
        $fileId = $request->query->get('id');
//        dd($fileInfo);
        $file = $this->getDoctrine()->getRepository(DocumentUpload::class)->find($fileId);
        $monthYear = explode(',', $file->getMonthYear());
//        dd($monthYear);
        $month = $monthYear[0];
        $year = $monthYear[1];
//        dd($monthYear[1]);
        //Read uploaded Excel File
        $reader = new Xlsx();
        $spreadSheet = $reader->load($this->get('kernel')->getProjectDir() . '/public/uploads/excel/' . $file->getFileName());
        $excelSheet = $spreadSheet->getActiveSheet();
        $allData = $excelSheet->toArray();

        //Remove Excel column heading
        $keys = array_shift($allData);
//        dd($keys);
//        list($agentId, $agentName, $thana, $district, $broiler, $sonali, $layer, $fish, $cattle, $month, $year) = $keys;
//        $breedTypes = [$broiler, $sonali, $layer, $fish, $cattle];
        $breedTypes = [$keys[4], $keys[5], $keys[6], $keys[7], $keys[8]];

        $em = $this->getDoctrine()->getManager();
        $addedId = [];
        foreach ($allData as $data) {
            //Marge Excel heading and value in one array as key and value
            $details = array_combine($keys, $data);
            list($agentIdValue, $agentNameValue, $upozilaValue, $districtValue, $broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue, $monthValue, $yearValue) = $data;

            $breedValues = [$broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue];

            $breedArrays = array_combine($breedTypes, $breedValues);

            //Find agent
            $findAgent = $this->getDoctrine()->getRepository(Agent::class)->findOneBy(['agentId' => (int)$agentIdValue]);
            if ($findAgent == null) {
                $agent = new Agent();
                $agent->setAgentId($agentIdValue);
                $agent->setUpozila($upozila);
                $agent->setDistrict($district);
                $agent->setName($agentNameValue);
                $agent->setCreated(new \DateTime());
                $em->persist($agent);
                $em->flush();
                $findAgent = $this->getDoctrine()->getRepository(Agent::class)->findOneBy(['agentId' => (int)$agentIdValue]);
            }
            foreach ($breedArrays as $breedType => $value) {
                $agentOrder = new AgentOrder();
                $product = $this->getDoctrine()->getRepository(MarkChart::class)->findOneBy(['name' => $breedType]);
                $district = $this->getDoctrine()->getRepository(Location::class)->findOneBy(['name' => $districtValue]);
                $upozila = $this->getDoctrine()->getRepository(Location::class)->findOneBy(['name' => $upozilaValue]);
                if ($product) {
                    $agentOrder->setAgent($findAgent);
                    $agentOrder->setDistrict($district);
                    $agentOrder->setUpozila($upozila);
                    $agentOrder->setProduct($product);
                    $agentOrder->setQuantity($value);
                    $agentOrder->setCreated(new \DateTime());
                    $agentOrder->setUpdated(new \DateTime());
                    $agentOrder->setMonth($month);
                    $agentOrder->setYear($year);
                    $em->persist($agentOrder);
                    $em->flush();

                    $addedId[] = $agentOrder->getId();
                }
            }
        }
        if ($addedId) {
            $this->addFlash('success', 'Record updated successfully into Database!');
        } else {
            $this->addFlash('error', 'Something wrong!');
        }

        $file->setStatus(1);

        $em->persist($file);
        $em->flush();

        return $this->redirectToRoute('kpi_file_upload_index');
    }


    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/delete-file", name="kpi_file_upload_file_delete")
     */
    public function deleteUploadedFile(Request $request, TranslatorInterface $translator)
    {
        $fileId = $request->query->get('id');
        $file = $this->getDoctrine()->getRepository(DocumentUpload::class)->find($fileId);
        $uploadDir = $this->get('kernel')->getProjectDir() . '/public/uploads/excel/';
        unlink($uploadDir.$file->getFileName());

        $em = $this->getDoctrine()->getManager();

        $em->remove($file);
        $em->flush();
        $this->addFlash('success', $translator->trans('File Removed!'));

        return $this->redirectToRoute('kpi_file_upload_index');
    }
}