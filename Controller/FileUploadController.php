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
use Terminalbd\KpiBundle\Entity\AgentDocSaleCollection;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\AgentOutstanding;
use Terminalbd\KpiBundle\Entity\DistrictOrder;
use Terminalbd\KpiBundle\Entity\DocumentUpload;
use Terminalbd\KpiBundle\Entity\LocationSalesTarget;
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
        $keys = array_map('trim', $keys); //remove all spaces from string

        $slug = str_replace(' ', '-', strtolower($file->getTitle()));
        switch ($slug){
            case "agent-sales":
                $flashArray = $this->getDoctrine()->getRepository(AgentOrder::class)->insertAgentSales($file, $keys, $allData, $month, $year);
                if (isset($flashArray['new']) && sizeof($flashArray['new'])>0) {
                    $this->addFlash('success', 'Record inserted successfully into Database!');
                }elseif (isset($flashArray['update']) && sizeof($flashArray['update'])>0){
                    $this->addFlash('error', 'Record already exit');
                }
                else {
                    $this->addFlash('error', 'Something wrong!');
                }
                break;
            case "agent-outstanding":
                $addedId = $this->getDoctrine()->getRepository(AgentOutstanding::class)->insertAgentOutstanding($file, $keys, $allData, $month, $year);
                if($addedId){
                    $this->addFlash('success', 'Data inserted successfully!');
                }else{
                    $this->addFlash('error', 'Something Wrong!');
                }
                break;
            case "doc-sales-collection":
                $addedId = $this->getDoctrine()->getRepository(AgentDocSaleCollection::class)->insertDocSalesCollection($file, $keys, $allData, $month, $year);
                if($addedId){
                    $this->addFlash('success', 'Data inserted successfully!');
                }else{
                    $this->addFlash('error', 'Something Wrong!');
                }
                break;
            case "district-sales-target":
                $addedId = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->insertTargetAmount($file, $keys, $allData, $month, $year);
                if(isset($addedId['new']) && sizeof($addedId['new'])>0){
                    $this->addFlash('success', 'Data inserted successfully!');
                }elseif (isset($addedId['old']) && sizeof($addedId['old'])>0){
                    $this->addFlash('success', 'Data updated successfully!');
                }else{
                    $this->addFlash('error', 'Something Wrong!');
                }
                break;
        }

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

        $salesDate = new \DateTime("01-{$month}-{$year}");

        $agentOrders = $this->getDoctrine()->getRepository(AgentOrder::class)->getDistrictWiseTotalProductSales($month, $year);

        foreach ($agentOrders as $key=> $agentOrder){

            $district = $this->getDoctrine()->getRepository(Location::class)->find($agentOrder['dId']);
            $product = $this->getDoctrine()->getRepository(MarkChart::class)->find($agentOrder['pId']);



            $districtOrder = new DistrictOrder();

            $existingDistrictOrder = $this->getDoctrine()->getRepository(DistrictOrder::class)->findOneBy(array('district'=>$district, 'product'=>$product, 'year'=>$agentOrder['oYear'],'month'=>$agentOrder['oMonth']));
            if($existingDistrictOrder){
                $districtOrder= $existingDistrictOrder;
            }

            $salesTargetQty = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->findOneBy(array('district'=>$district,'markDistribution'=>$product, 'month'=>$month, 'year'=>$year));
            $salesPriviousGrouthQty = $this->getDoctrine()->getRepository(DistrictOrder::class)->getGrouthPreviousProductQty($district, $product, $year, $month);
            $salesCurrentGrouthQty = $this->getDoctrine()->getRepository(DistrictOrder::class)->getGrouthCurrentProductQty($district, $product, $year, $month);

            $targetSalesQty =$salesTargetQty?$salesTargetQty->getQuantity():0;

            $districtOrder->setYear($agentOrder['oYear']);
            $districtOrder->setMonth($agentOrder['oMonth']);
            $districtOrder->setQuantity($agentOrder['totalQty']);
            $districtOrder->setDistrict($district?$district:null);
            $districtOrder->setProduct($product?$product:null);
            $districtOrder->setTargetQuantity($targetSalesQty);
            $districtOrder->setSalesGrouthPreviousQuantity($salesPriviousGrouthQty?$salesPriviousGrouthQty:0);
            $districtOrder->setSalesGrouthCurrentQuantity($salesCurrentGrouthQty?($salesCurrentGrouthQty+$agentOrder['totalQty']):$agentOrder['totalQty']);

            $districtOrder->setSalesMarkPercentage($this->salesTargetPercentageCalculation($targetSalesQty, $agentOrder['totalQty']));
            $districtOrder->setSalesMark($this->salesTargetMarkCalculation($targetSalesQty, $agentOrder['totalQty']));

            $districtOrder->setCreated($salesDate);
            $districtOrder->setUpdated(new \DateTime());
            $districtOrder->setStatus(1);
            $em->persist($districtOrder);
            $em->flush();
        }

        $file->setStatus(2);

        $em->persist($file);
        $em->flush();
        
        $this->addFlash('success', 'Data migrate successfully!');

        return $this->redirectToRoute('kpi_file_upload_index');
    }


    private function salesTargetPercentageCalculation($target,$sales)
    {
        if($target > 0){
            $action = (($sales * 100 )/$target);
            return $action;

        }

        return 0;

    }

    private function salesTargetMarkCalculation($target,$sales)
    {
        if($target > 0){
            $action = (($sales * 100 )/$target);
            if($action >= 100) {
                return 5;
            }elseif ($action < 100 and $action >= 90) {
                return 4;
            }elseif ($action < 90 and $action >= 80) {
                return 3;
            }elseif ($action < 80 and $action >= 70) {
                return 2;
            }elseif ($action < 70 and $action >= 60) {
                return 1;
            }else {
                return 0;
            }

        }

        return 0;

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