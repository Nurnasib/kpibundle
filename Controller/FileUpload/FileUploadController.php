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
        $data = [];
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
//            dd($file);
            if ($file){
                $fileExt = $file->getClientOriginalExtension();
                $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) .'_'. date('d-m-Y') . '_' . time() . '.' . $fileExt;
//                dd($fileName);
                if (in_array($fileExt, $allowFileType)){
                    $uploadDir = $this->get('kernel')->getProjectDir().'/public/uploads/excel/';
                    $file->move($uploadDir, $fileName);
                    $reader = new Xlsx();
                    $spreadSheet = $reader->load($uploadDir.$fileName);
                    $excelSheet = $spreadSheet->getActiveSheet();
                    $allData = $excelSheet->toArray();
/*                    $keys = [
                        0 => 'id',
                        1 => 'Broiler',
                        2 => 'Sonali',
                        3 => 'Layer',
                        4 => 'Sinking',
                        5 => 'Floating',
                        6 => 'Total Fish',
                        7 => 'Cattle',
                        8 => 'Total Feed'
                    ];
                    $detail = [];*/

                    $em = $this->getDoctrine()->getManager();

                    foreach ($allData as $key => $data){
                        if ($key>0){
//                            dd($data);
                            $findAgent = $this->getDoctrine()->getRepository(Agent::class)->find($data[0]);

                            if ($findAgent !== Null){
                                foreach ($data as $k=>$d){
                                    if($k>0){
                                        $agentOrder = new AgentOrder();
                                        $agentOrder->setAgent($findAgent);
                                        $agentOrder->setQuantity($d);
                                        $agentOrder->setMonth($month);
                                        $agentOrder->setYear($year);
                                        $em->persist($agentOrder);
                                        $em->flush();
                                    }
                                }
                            }
//                            dd($findAgent);
//                            $detail[]['month'] = $month;
//                            $detail[] = array_combine($keys, $data);
//                            echo '<pre>';
//                            print_r($data);
//                            echo '</pre>';
                        }
                    }
//                    dd($detail);
//                    $findAgent = $this->getDoctrine()->getRepository(Agent::class)->find();
                    $this->addFlash('message', 'Record updated successfully into Database!');
                }
            }
        }
        return $this->render('@TerminalbdKpi/fileUpload/index.html.twig',[
            'form' => $form->createView()
        ]);
    }
}