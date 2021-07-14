<?php
/**
 * Created by PhpStorm.
 * User: hasan
 * Date: 9/8/19
 * Time: 4:43 PM
 */
namespace Terminalbd\KpiBundle\Form;


use App\Entity\Core\Setting;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmployeeFilterFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('employeeId',TextType::class,[
                'required' => false,
                'attr' => [
                    'placeholder' => 'Employee Id',
                    'autocomplete' => 'off'
                ]
            ])
            ->add('employeeName',TextType::class,[
                'required' => false,
                'attr' => [
                    'placeholder' => 'Name',
                    'autocomplete' => 'off'

                ]
            ])
/*            ->add('designation',TextType::class,[
                'required' => false,
                'attr' => [
                    'placeholder' => 'Designation',
                ]
            ])*/
/*            ->add('kpiFormat', EntityType::class,[
                'class' => Setting::class,
                'placeholder' => 'Select Format',
                'required' => false,
                'choice_label' => 'name',
                'query_builder' => function(EntityRepository $repository){
                return $repository->createQueryBuilder('e')
                    ->join('e.settingType', 'settingType')
                    ->where('settingType.id = 6 ');
                }
            ])*/
            ->add('lineManager', TextType::class,[
                'required' => false,
                'attr' => [
                    'placeholder' => 'Line Manager',
                    'autocomplete' => 'off'

                ]
            ])
            ->setMethod('GET')
            ;



     }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }


}