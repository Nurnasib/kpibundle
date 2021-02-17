<?php

namespace Terminalbd\KpiBundle\Controller\FileUpload;


use App\Entity\Admin\Location;
use App\Entity\Core\Agent;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Terminalbd\KpiBundle\Entity\AgentOrder;
use Terminalbd\KpiBundle\Entity\MarkChart;

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
    public function fileUpload(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $fileName = '';
        $uploadDir = '';
        $allowFileType = ['xlsx'];
//        $data = [];
        $form = $this->createFormBuilder()
            ->add('Title', ChoiceType::class, [
                'choices' => [
                    'Select Type' => null,
                    'Agent' => 'agent',
                    'Sale' => 'sale',
                    'Employee' => 'employee'
                ],
                'required' => true,
            ])
            ->add('UploadFile', FileType::class, [
                'help' => 'Please upload only excel file!'
            ])
            ->add('Submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $formData = $form->getData();

            $file = $request->files->get('form')['UploadFile'];
            if ($file) {
                $fileExt = $file->getClientOriginalExtension();
                $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '_' . date('d-m-Y') . '_' . time() . '.' . $fileExt;

                if (in_array($fileExt, $allowFileType)) {
                    $uploadDir = $this->get('kernel')->getProjectDir() . '/public/uploads/excel/';
                    $file->move($uploadDir, $fileName);
                }
            }
        }
        return $this->render('@TerminalbdKpi/fileUpload/index.html.twig', [
            'form' => $form->createView(),
            'fileName' => $fileName,
            'uploadDir' => $uploadDir
        ]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/insert-data", name="kpi_file_upload_insert_data")
     */
    public function insertDataFromUploadedFile(Request $request)
    {
        $fileInfo = $request->query->all();
        //Read uploaded Excel File
        $reader = new Xlsx();
        $spreadSheet = $reader->load($fileInfo['uploadDir'] . $fileInfo['fileName']);
        $excelSheet = $spreadSheet->getActiveSheet();
        $allData = $excelSheet->toArray();

        //Remove Excel column heading
        $keys = array_shift($allData);
//        dd($keys);
//        list($agentId, $agentName, $thana, $district, $broiler, $sonali, $layer, $fish, $cattle, $month, $year) = $keys;
//        $breedTypes = [$broiler, $sonali, $layer, $fish, $cattle];
        $breedTypes = [$keys[4], $keys[5], $keys[6], $keys[7], $keys[8]];
//        dd($breedTypes);

        $em = $this->getDoctrine()->getManager();
        $addedId = [];
        foreach ($allData as $data) {
            //Marge Excel heading and value in one array as key and value
            $details = array_combine($keys, $data);
//            dd($details);
            list($agentIdValue, $agentNameValue, $upozilaValue, $districtValue, $broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue, $monthValue, $yearValue) = $data;

            $breedValues = [$broilerValue, $sonaliValue, $layerValue, $fishValue, $cattleValue];

            $breedArrays = array_combine($breedTypes,$breedValues);
//            dd($breedArr);

            //Find agent
            $findAgent = $this->getDoctrine()->getRepository(Agent::class)->findOneBy(['agentId' => (int)$agentIdValue]);
            if ($findAgent !== null){
                foreach ($breedArrays as $breedType => $value){
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
                        $agentOrder->setMonth($monthValue);
                        $agentOrder->setYear($yearValue);
                        $em->persist($agentOrder);
                        $em->flush();

                        $addedId[] = $agentOrder->getId();
                    }
                }
            }
        }
        if ($addedId) {
            $this->addFlash('success', 'Record updated successfully into Database!');
        } else {
            $this->addFlash('error', 'Something wrong!');
        }
        return $this->redirectToRoute('kpi_file_upload_index');
    }
}