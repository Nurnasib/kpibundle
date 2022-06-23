<?php

namespace Terminalbd\KpiBundle\Controller;


use App\Entity\Admin\Location;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
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
 * @Security("is_granted('ROLE_KPI_ADMIN') or is_granted('ROLE_DOMAIN') or is_granted('ROLE_KPI_EXECUTIVE')")
 */
class FileUploadController extends AbstractController
{
    /**
     * @param Request $request
     * @param TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/", name="kpi_file_upload_index", options={"expose" = true})
     */
    public function fileUpload(Request $request, TranslatorInterface $translator)
    {
        set_time_limit(0);
        ini_set('memory_limit', '5000M');
        $uploadFile = new DocumentUpload();
        $allowFileType = ['xlsx'];

        $entities = $this->getDoctrine()->getRepository(DocumentUpload::class)->findBy([], ['createdAt' => 'DESC']);
        $data = [];
        foreach ($entities as $entity) {
            $data[$entity->getMonthYear()][] = $entity;
        }
        $form = $this->createForm(FileUploadFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {

            $uploadedFile = $form['UploadFile']->getData();
            if (in_array($uploadedFile->getClientOriginalExtension(), $allowFileType)){
                $em = $this->getDoctrine()->getManager();
//                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $newFileName = $form['title']->getData() . '_' . $form['monthYear']->getData() . '_upload_date:' . date('d-m-Y') . '-' . time() . '.' . $uploadedFile->getClientOriginalExtension();
                $uploadDir = $this->get('kernel')->getProjectDir() . '/public/uploads/excel/';

                $uploadedFile->move(
                    $uploadDir,
                    $newFileName
                );
                $uploadFile->setFileName($newFileName);
                $uploadFile->setTitle($form['title']->getData());
                $uploadFile->setMonthYear($form['monthYear']->getData());
                $uploadFile->setCreatedAt( new \DateTime('now'));
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
            'entities' => $data,
        ]);
    }

    /**
     * @param DocumentUpload $file
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/{id}/insert-data", name="kpi_file_upload_insert_data")
     */
    public function insertDataFromUploadedFile(DocumentUpload $file, ParameterBagInterface $parameterBag, KernelInterface $kernel)
    {
        set_time_limit(0);
        ini_set('memory_limit', '5000M');

        $monthYear = explode(',', $file->getMonthYear());
        $month = $monthYear[0];
        $year = $monthYear[1];

        //Update agent
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'app:update-agent'
        ]);

        $output = new NullOutput();

        $application->run($input, $output);
        //Update agent END


        //Read uploaded Excel File
        $reader = new Xlsx();
        $reader->setReadDataOnly(true); // remove empty rows
        $reader->setReadEmptyCells(false); // remove empty rows
        $spreadSheet = $reader->load($this->get('kernel')->getProjectDir() . '/public/uploads/excel/' . $file->getFileName());
        $excelSheet = $spreadSheet->getActiveSheet();
        $allData = $excelSheet->toArray();

        //Remove Excel column heading
        $keys = array_shift($allData);
        $keys = array_map('trim', array_filter($keys)); //remove all spaces from string
        $slug = str_replace(' ', '-', strtolower($file->getTitle()));
        switch ($slug){
            case "agent-sales":
                $findDistrictTarget = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->findBy(['month' => $month, 'year' => $year]);
                if (! $findDistrictTarget){
                    $this->addFlash('warning', 'Not found district target for ' . $month . ',' . $year . '. Please set district target first!');
                    return $this->redirectToRoute('kpi_file_upload_index');
                } // Find District target

                $notInsertedData = $this->getDoctrine()->getRepository(AgentOrder::class)->insertAgentSales($file, $keys, $allData, $month, $year);

                if($notInsertedData){
                    $spreadsheet = new Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();

//                    $sheet->setTitle("Problem Agent Sales"); //Sheet name
                    foreach ($notInsertedData as $key => $data) {
                        if ($key === array_key_first($notInsertedData)){ //header
                            $sheet->setCellValue("A1", "AgentId");
                            $sheet->setCellValue("B1", "Agents Name");
                            $sheet->setCellValue("C1", "Thana");
                            $sheet->setCellValue("D1", "DistrictId");
                            $sheet->setCellValue("E1", "District");
                            $sheet->setCellValue("F1", "Broiler");
                            $sheet->setCellValue("G1", "Sonali");
                            $sheet->setCellValue("H1", "Layer");
                            $sheet->setCellValue("I1", "Fish");
                            $sheet->setCellValue("J1", "Cattle");
                            $sheet->setCellValue("K1", "Month");
                            $sheet->setCellValue("L1", "Year");
                        }

                        $cellCoordinate = $key + 2;

                        $sheet->setCellValue("A" . $cellCoordinate, $data['AgentId']);
                        $sheet->setCellValue("B" . $cellCoordinate, $data['Agents Name']);
                        $sheet->setCellValue("C" . $cellCoordinate, $data['Thana']);
                        $sheet->setCellValue("D" . $cellCoordinate, $data['DistrictId']);
                        $sheet->setCellValue("E" . $cellCoordinate, $data['District']);
                        $sheet->setCellValue("F" . $cellCoordinate, $data['Broiler'] ?: 0);
                        $sheet->setCellValue("G" . $cellCoordinate, $data['Sonali'] ?: 0);
                        $sheet->setCellValue("H" . $cellCoordinate, $data['Layer'] ?: 0);
                        $sheet->setCellValue("I" . $cellCoordinate, $data['Fish'] ?: 0);
                        $sheet->setCellValue("J" . $cellCoordinate, $data['Cattle'] ?: 0);
                        $sheet->setCellValue("K" . $cellCoordinate, $data['Month']);
                        $sheet->setCellValue("L" . $cellCoordinate, $data['Year']);

                    }

                    // Create xlsx file
                    $filePath = $parameterBag->get('projectRoot') . '/public/uploads/problem_agent_sales_'.$month . '_' . $year . '_' . date('d-m-Y_H-s-i') .'_.xlsx';
                    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                    $writer->setIncludeCharts(true);
                    $writer->save($filePath);

                    return $this->file($filePath)->deleteFileAfterSend();
                }

                return $this->redirectToRoute('kpi_file_upload_index');

                break;
            case "agent-outstanding":
                $findDistrictTarget = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->findBy(['month' => $month, 'year' => $year]);
                if (! $findDistrictTarget){
                    $this->addFlash('warning', 'Not found district target for ' . $month . ',' . $year . '. Please set district target first!');
                    return $this->redirectToRoute('kpi_file_upload_index');
                } // Find District target

                $notInsertedData = $this->getDoctrine()->getRepository(AgentOutstanding::class)->insertAgentOutstanding($file, $keys, $allData, $month, $year);

                if($notInsertedData){
                    $spreadsheet = new Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();

//                    $sheet->setTitle("Problem Outstanding"); //Sheet name
                    foreach ($notInsertedData as $key => $data) {

                        if ($key === array_key_first($notInsertedData)){ //header
                            $sheet->setCellValue("A1", "AgentId");
                            $sheet->setCellValue("B1", "Limit");
                            $sheet->setCellValue("C1", "Net Outstanding");
                            $sheet->setCellValue("D1", "DistrictId");
                            $sheet->setCellValue("E1", "District");
                            $sheet->setCellValue("F1", "Month");
                            $sheet->setCellValue("G1", "Year");
                        }

                        $cellCoordinate = $key + 2;

                        $sheet->setCellValue("A" . $cellCoordinate, $data['AgentId']);
                        $sheet->setCellValue("B" . $cellCoordinate, $data['Limit'] ?: 0);
                        $sheet->setCellValue("C" . $cellCoordinate, $data['Net Outstanding'] ?: 0);
                        $sheet->setCellValue("D" . $cellCoordinate, $data['DistrictId']);
                        $sheet->setCellValue("E" . $cellCoordinate, $data['District']);
                        $sheet->setCellValue("F" . $cellCoordinate, $data['Month']);
                        $sheet->setCellValue("G" . $cellCoordinate, $data['Year']);

                    }

                    // Create xlsx file
                    $filePath = $parameterBag->get('projectRoot') . '/public/uploads/problem_outstanding_' . $month . '_' . $year . '_' . date('d-m-Y_H-s-i') .'_.xlsx';
                    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                    $writer->setIncludeCharts(true);
                    $writer->save($filePath);

                    return $this->file($filePath)->deleteFileAfterSend();
                }

                return $this->redirectToRoute('kpi_file_upload_index');

                break;
            case "doc-sales-collection":
                $findDistrictTarget = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->findBy(['month' => $month, 'year' => $year]);
                if (! $findDistrictTarget){
                    $this->addFlash('warning', 'Not found district target for ' . $month . ',' . $year . '. Please set district target first!');
                    return $this->redirectToRoute('kpi_file_upload_index');
                } // Find District target

                $notInsertedData = $this->getDoctrine()->getRepository(AgentDocSaleCollection::class)->insertDocSalesCollection($file, $keys, $allData, $month, $year);

                if($notInsertedData){
                    $spreadsheet = new Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();

//                    $sheet->setTitle("Problem Doc Sales Collection"); //Sheet name
                    foreach ($notInsertedData as $key => $data) {
                        if ($key === array_key_first($notInsertedData)){ //header
                                $sheet->setCellValue("A1", "AgentId");
                                $sheet->setCellValue("B1", "Sales");
                                $sheet->setCellValue("C1", "Collection");
                                $sheet->setCellValue("D1", "DistrictId");
                                $sheet->setCellValue("E1", "District");
                                $sheet->setCellValue("F1", "Month");
                                $sheet->setCellValue("G1", "Year");
                        }

                        $cellCoordinate = $key + 2;

                        $sheet->setCellValue("A" . $cellCoordinate, $data['AgentId']);
                        $sheet->setCellValue("B" . $cellCoordinate, $data['Sales']);
                        $sheet->setCellValue("C" . $cellCoordinate, $data['Collection']);
                        $sheet->setCellValue("D" . $cellCoordinate, $data['DistrictId']);
                        $sheet->setCellValue("E" . $cellCoordinate, $data['District']);
                        $sheet->setCellValue("F" . $cellCoordinate, $data['Month']);
                        $sheet->setCellValue("G" . $cellCoordinate, $data['Year']);

                    }
                    
                    // Create xlsx file
                    $filePath = $parameterBag->get('projectRoot') . '/public/uploads/problem_doc_sales_collection_'.$month . '_' . $year . '_' . date('d-m-Y_H-s-i') .'.xlsx';
                    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                    $writer->setIncludeCharts(true);
                    $writer->save($filePath);

                    return $this->file($filePath)->deleteFileAfterSend();

                }
                
                return $this->redirectToRoute('kpi_file_upload_index');

                break;
            case "district-sales-target":

                $notInsertedData = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->insertTargetAmount($file, $keys, $allData, $month, $year);

                if($notInsertedData){
                    $spreadsheet = new Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();

//                    $sheet->setTitle("Problem District Target"); //Sheet name
                    foreach ($notInsertedData as $key => $data) {
                        if ($key === array_key_first($notInsertedData)){ //header
                            $sheet->setCellValue("A1", "DistrictId");
                            $sheet->setCellValue("B1", "District");
                            $sheet->setCellValue("C1", "Broiler");
                            $sheet->setCellValue("D1", "Layer");
                            $sheet->setCellValue("E1", "Fish");
                            $sheet->setCellValue("F1", "Cattle");
                            $sheet->setCellValue("G1", "Sonali");
                            $sheet->setCellValue("H1", "Month");
                            $sheet->setCellValue("I1", "Year");
                        }

                        $cellCoordinate = $key + 2;

                        $sheet->setCellValue("A" . $cellCoordinate, $data['DistrictId']);
                        $sheet->setCellValue("B" . $cellCoordinate, $data['District']);
                        $sheet->setCellValue("C" . $cellCoordinate, $data['Broiler'] ?: 0);
                        $sheet->setCellValue("D" . $cellCoordinate, $data['Layer'] ?: 0);
                        $sheet->setCellValue("E" . $cellCoordinate, $data['Fish'] ?: 0);
                        $sheet->setCellValue("F" . $cellCoordinate, $data['Cattle'] ?: 0);
                        $sheet->setCellValue("G" . $cellCoordinate, $data['Sonali'] ?: 0);
                        $sheet->setCellValue("H" . $cellCoordinate, $data['Month']);
                        $sheet->setCellValue("I" . $cellCoordinate, $data['Year']);

                    }

                    // Create xlsx file
                    $filePath = $parameterBag->get('projectRoot') . '/public/uploads/problem_district_target_'.$month . '_' . $year . '_' . date('d-m-Y_H-s-i') .'_.xlsx';
                    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                    $writer->setIncludeCharts(true);
                    $writer->save($filePath);

                    return $this->file($filePath)->deleteFileAfterSend();
                }
                break;
        }

        return $this->redirectToRoute('kpi_file_upload_index');
    }

    /**
     * @param DocumentUpload $file
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


        $months = [];
        $monthNumber = date('m',strtotime($month));
        for ($i = 1; $i <= $monthNumber; $i++){ // make month name array from January to generated KPI month
            array_push($months, \DateTime::createFromFormat('!m', $i)->format('F'));
        }
        $agentOrders = $this->getDoctrine()->getRepository(AgentOrder::class)->getDistrictWiseTotalProductSales($month, $year);


        foreach ($agentOrders as $key => $agentOrder){

            $district = $this->getDoctrine()->getRepository(Location::class)->find($agentOrder['dId']);
//            $district = $this->getDoctrine()->getRepository(Location::class)->find(96);
            $product = $this->getDoctrine()->getRepository(MarkChart::class)->find($agentOrder['pId']);

            $districtOrder = new DistrictOrder();

            $existingDistrictOrder = $this->getDoctrine()->getRepository(DistrictOrder::class)->findOneBy(array('district'=>$district, 'product'=>$product, 'year'=>$agentOrder['oYear'],'month'=>$agentOrder['oMonth']));
            if($existingDistrictOrder){
                $districtOrder= $existingDistrictOrder;
            }

            $salesTargetQty = $this->getDoctrine()->getRepository(LocationSalesTarget::class)->findOneBy(array('district'=>$district,'markDistribution'=>$product, 'month'=>$month, 'year'=>$year));
            $salesPriviousGrouthQty = $this->getDoctrine()->getRepository(DistrictOrder::class)->getGrouthPreviousProductQty($district, $product, $year, $month);
            $salesCurrentGrouthQty = $this->getDoctrine()->getRepository(DistrictOrder::class)->getGrouthCurrentProductQty($district, $product, $year, $months);

            $targetSalesQty = $salesTargetQty ? $salesTargetQty->getQuantity() : 0;

            $districtOrder->setYear($agentOrder['oYear']);
            $districtOrder->setMonth($agentOrder['oMonth']);
            $districtOrder->setQuantity($agentOrder['totalQty']);
            $districtOrder->setDistrict($district ?: null);
            $districtOrder->setProduct($product ?: null);
            $districtOrder->setTargetQuantity($targetSalesQty);
            $districtOrder->setSalesGrouthPreviousQuantity($salesPriviousGrouthQty ?:0);
//            $districtOrder->setSalesGrouthCurrentQuantity($salesCurrentGrouthQty ? ($salesCurrentGrouthQty + $agentOrder['totalQty']):$agentOrder['totalQty']);
            $districtOrder->setSalesGrouthCurrentQuantity($salesCurrentGrouthQty ?: $agentOrder['totalQty']);

            $districtOrder->setSalesMarkPercentage($this->salesTargetPercentageCalculation($targetSalesQty, $agentOrder['totalQty']));
            $districtOrder->setSalesMark($this->salesTargetMarkCalculation($targetSalesQty, $agentOrder['totalQty']));

            $districtOrder->setCreated($salesDate);
            $districtOrder->setUpdated(new \DateTime());
            $districtOrder->setStatus(2);
            $em->persist($districtOrder);
            $em->flush();

            //Insert cumulative Qty and cumulative Target Qty
            $salesCumulativeTargetQty = $this->getDoctrine()->getRepository(DistrictOrder::class)->getCumulativeTargetQty($district, $product, $year, $months);
            $salesCumulativeQty = $this->getDoctrine()->getRepository(DistrictOrder::class)->getCumulativeQty($district, $product, $year, $months);
            $districtOrder->setCumulativeTargetQuantity($salesCumulativeTargetQty);
            $districtOrder->setCumulativeQuantity($salesCumulativeQty);
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
     * @param TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/delete-file", name="kpi_file_upload_file_delete")
     */
    public function deleteUploadedFile(Request $request, TranslatorInterface $translator)
    {
        $fileInfo = $request->query->all();
        $file = $this->getDoctrine()->getRepository(DocumentUpload::class)->find($fileInfo['id']);
        $uploadDir = $this->get('kernel')->getProjectDir() . '/public/uploads/excel/';
        if (file_exists($uploadDir.$file->getFileName())){
            unlink($uploadDir.$file->getFileName());
        }

        $em = $this->getDoctrine()->getManager();

        $em->remove($file);
        $em->flush();
        $this->addFlash('success', $translator->trans('File Removed!'));

        return $this->redirectToRoute('kpi_file_upload_index');
    }
}