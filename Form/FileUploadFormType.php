<?php
/**
 * Created by PhpStorm.
 * User: hasan
 * Date: 9/8/19
 * Time: 4:43 PM
 */

namespace Terminalbd\KpiBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Terminalbd\KpiBundle\Entity\DocumentUpload;
use Terminalbd\KpiBundle\Entity\UploadFile;

class FileUploadFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {


        $builder
            ->add('title', ChoiceType::class,[
                'choices' => [
                    '---Select---' => null,
                    'Agent' => 'agent',
                    'Employee' => 'employee',
                    'Sales' => 'sales'
                ]
            ])
            ->add('monthYear',TextType::class,[
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'Month Year'
                ]
            ])
            ->add('UploadFile', FileType::class, [
                'help' => 'Please upload only excel file!',
                'mapped' => false,
            ])
            ->add('Submit', SubmitType::class)
            ;

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DocumentUpload::class,
        ]);
    }


}