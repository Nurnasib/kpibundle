<?php
/**
 * Created by PhpStorm.
 * User: hasan
 * Date: 9/8/19
 * Time: 4:43 PM
 */

namespace Terminalbd\KpiBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Terminalbd\KpiBundle\Entity\EvaluationCriteria;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class EvaluationCriteriaFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {


        $builder
            ->add('type', ChoiceType::class,[
                'choices' => [
                    '---Select CSO---' => null,
                    'Poultry' => 'poultry',
                    'Lab' => 'lab'
                ],
                'help' => 'Please select report type'
            ])
            ->add('name', TextType::class,[
                'attr' => [
                    'placeholder' => 'Report title here'
                ]
            ])
            ->add('reportTarget', NumberType::class)
            ->add('marksTarget', NumberType::class)
            ->add('save', SubmitType::class)
            ;

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => EvaluationCriteria::class,
        ]);
    }


}