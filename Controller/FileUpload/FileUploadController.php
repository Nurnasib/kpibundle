<?php

namespace Terminalbd\KpiBundle\Controller\FileUpload;


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
        $allowFileType = ['xlsx'];
//        $data = [];
        $form = $this->createFormBuilder()
            ->add('Title', ChoiceType::class,[
                'choices'=>[
                    'Select Type' => null,
                    'Agent' => 'agent',
                    'Sale' => 'sale',
                    'Employee' => 'employee'
                ],
                'required' => true,
            ])
            ->add('month',TextType::class,[
                'attr' =>[
                    'placeholder'=> 'Select Month',
                    'autocomplete'=> 'off'
                ]
            ])
            ->add('UploadFile', FileType::class,[
                'help' =>'Please upload only excel file!'
            ])
            ->add('Submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);
        if($form->isSubmitted()){
            $formData = $form->getData();
            $monthYear = explode(' ',$formData['month']);
            $month = $monthYear[0];
            $year = $monthYear[1];


            $file = $request->files->get('form')['UploadFile'];
            if ($file){
                $fileExt = $file->getClientOriginalExtension();
                $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) .'_'. date('d-m-Y') . '_' . time() . '.' . $fileExt;

                if (in_array($fileExt, $allowFileType)){
                    $uploadDir = $this->get('kernel')->getProjectDir().'/public/uploads/excel/';
                    $file->move($uploadDir, $fileName);

                    //Read uploaded Excel File
                    $reader = new Xlsx();
                    $spreadSheet = $reader->load($uploadDir.$fileName);
                    $excelSheet = $spreadSheet->getActiveSheet();
                    $allData = $excelSheet->toArray();

                    //Remove Excell column heading
                    $keys = array_shift($allData);

                    $em = $this->getDoctrine()->getManager();
                    $addedId=[];
                    foreach ($allData as $data){

                        //Marge Excel heading and value in one array as key and value
                        $detail = array_combine($keys, $data);

                        //Find agent
                        $findAgent = $this->getDoctrine()->getRepository(Agent::class)->find($detail['Id']);

                        if ($findAgent !== Null){
                            foreach ($detail as $productName => $quantity){
                                if($productName !== 'Id'){
                                    $agentOrder = new AgentOrder();

                                    //Find product
                                    $product = $this->getDoctrine()->getRepository(MarkChart::class)->findOneBy(['name'=>$productName]);
                                    if ($product){
                                        $agentOrder->setAgent($findAgent);
                                        $agentOrder->setProduct($product);
                                        $agentOrder->setQuantity($quantity);
                                        $agentOrder->setMonth($month);
                                        $agentOrder->setYear($year);
                                        $em->persist($agentOrder);
                                        $em->flush();

                                        $addedId[]=$agentOrder->getId();
                                    }
                                }
                            }
                        }
                    }
                    if($addedId){
//                        dd($addedId);
                        $this->addFlash('success', 'Record updated successfully into Database!');
                    }else{
                        $this->addFlash('error', 'Something wrong!');
                    }
                }
            }
        }
        return $this->render('@TerminalbdKpi/fileUpload/index.html.twig',[
            'form' => $form->createView()
        ]);
    }
}