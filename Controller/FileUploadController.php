<?php

namespace Terminalbd\KpiBundle\Controller;


use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use App\Entity\Core\Setting;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\DistrictOrder;
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
     * @Route("/{id}/insert-data", name="kpi_file_upload_insert_data")
     */
    public function insertDataFromUploadedFile(Request $request, DocumentUpload $file)
    {
        set_time_limit(0);
        $monthYear = explode(',', $file->getMonthYear());
        $month = $monthYear[0];
        $year = $monthYear[1];
        //Read uploaded Excel File
        $reader = new Xlsx();
        $spreadSheet = $reader->load($this->get('kernel')->getProjectDir() . '/public/uploads/excel/' . $file->getFileName());
        $excelSheet = $spreadSheet->getActiveSheet();
        $allData = $excelSheet->toArray();

        //Remove Excel column heading
        $keys = array_shift($allData);

        $breedTypes = [$keys[4], $keys[5], $keys[6], $keys[7], $keys[8]];

        $em = $this->getDoctrine()->getManager();
        $addedId = [];
        $existingId = [];
        foreach ($allData as $data) {
            //Marge Excel heading and value in one array as key and value
            $details = array_combine($keys, $data);
            list($agentIdValue, $agentNameValue, $upozilaValue, $districtValue, $broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue, $monthValue, $yearValue) = $data;

            $breedValues = [$broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue];

            $breedArrays = array_combine($breedTypes, $breedValues);

            $district = $this->getDoctrine()->getRepository(Location::class)->findOneBy(['level'=>4,'name' => $districtValue]);
            $upozila = $this->getDoctrine()->getRepository(Location::class)->findOneBy(['level'=>5,'name' => $upozilaValue]);


            //Find agent
            $findAgent = $this->getDoctrine()->getRepository(Agent::class)->findOneBy(['agentId' =>$agentIdValue]);
            if (!$findAgent) {
                $agent = new Agent();
                $agent->setAgentId($agentIdValue);
                $agent->setUpozila($upozila?$upozila:null);
                $agent->setDistrict($district?$district:null);
                $agent->setName($agentNameValue);
                $agent->setAgentGroup($em->getRepository(Setting::class)->findOneBy(array('slug' => 'feed')));
                $agent->setCreated(new \DateTime());
                $em->persist($agent);
                $em->flush();
                $findAgent = $agent;
            }
            foreach ($breedArrays as $breedType => $value) {

                $product = $this->getDoctrine()->getRepository(MarkChart::class)->findOneBy(['salesMode'=>'feed','name' => $breedType]);

                if ($product) {
                    $exitAgentOrder = $this->getDoctrine()->getRepository(AgentOrder::class)->findOneBy(array('agent'=>$findAgent,'product'=>$product,'month'=>$month,'year'=>$year));
                    if(!$exitAgentOrder){
                        $agentOrder = new AgentOrder();
                        $agentOrder->setAgent($findAgent);
                        $agentOrder->setDistrict($district?$district:null);
                        $agentOrder->setUpozila($upozila?$upozila:null);
                        $agentOrder->setProduct($product);
                        $agentOrder->setQuantity($value);
                        $agentOrder->setCreated(new \DateTime());
                        $agentOrder->setUpdated(new \DateTime());
                        $agentOrder->setMonth($month);
                        $agentOrder->setYear($year);
                        $agentOrder->setDocumentUpload($file);
                        $em->persist($agentOrder);
                        $em->flush();

                        $addedId[] = $agentOrder->getId();
                    }else{
                        $existingId[]=$exitAgentOrder->getId();
                    }

                }
            }
        }
        if ($addedId) {
            $this->addFlash('success', 'Record updated successfully into Database!');
        }elseif ($existingId){
            $this->addFlash('error', 'Record already exit');
        }
        else {
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
     * @Route("/{id}/insert-data-district-wise", name="kpi_insert_data_district_wise")
     */
    public function insertDataDistrictWise(DocumentUpload $file)
    {
        $em = $this->getDoctrine()->getManager();
        $monthYear = explode(',', $file->getMonthYear());
        $month = $monthYear[0];
        $year = $monthYear[1];
        $agentOrders = $this->getDoctrine()->getRepository(AgentOrder::class)->getDistrictWiseTotalProductSales($month, $year);
        
        foreach ($agentOrders as $key=> $agentOrder){

            $district = $this->getDoctrine()->getRepository(Location::class)->find($agentOrder['dId']);
            $product = $this->getDoctrine()->getRepository(MarkChart::class)->find($agentOrder['pId']);



            $districtOrder = new DistrictOrder();

            $existingDistrictOrder = $this->getDoctrine()->getRepository(DistrictOrder::class)->findOneBy(array('district'=>$district, 'product'=>$product, 'year'=>$agentOrder['oYear'],'month'=>$agentOrder['oMonth']));
            if($existingDistrictOrder){
                $districtOrder= $existingDistrictOrder;
            }


            $districtOrder->setYear($agentOrder['oYear']);
            $districtOrder->setMonth($agentOrder['oMonth']);
            $districtOrder->setQuantity($agentOrder['totalQty']);
            $districtOrder->setDistrict($district?$district:null);
            $districtOrder->setProduct($product?$product:null);
            $districtOrder->setCreated(new \DateTime());
            $districtOrder->setUpdated(new \DateTime());
            $districtOrder->setStatus(1);
            $em->persist($districtOrder);
            $em->flush();
        }


        return $this->redirectToRoute('kpi_file_upload_index');
    }


    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/delete-file", name="kpi_file_upload_file_delete")
     */
    public function deleteUploadedFile(Request $request, TranslatorInterface $translator)
    {
        $fileInfo = $request->query->all();
        $file = $this->getDoctrine()->getRepository(DocumentUpload::class)->find($fileInfo['id']);
        $uploadDir = $this->get('kernel')->getProjectDir() . '/public/uploads/excel/';
        unlink($uploadDir.$file->getFileName());

        $em = $this->getDoctrine()->getManager();

        $em->remove($file);
        $em->flush();
        $this->addFlash('success', $translator->trans('File Removed!'));

        return $this->redirectToRoute('kpi_file_upload_index');
    }
}