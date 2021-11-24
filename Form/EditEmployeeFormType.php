<?php
/**
 * Created by PhpStorm.
 * User: hasan
 * Date: 9/8/19
 * Time: 4:43 PM
 */

namespace Terminalbd\KpiBundle\Form;

use App\Entity\Admin\Bank;
use App\Entity\Admin\Location;
use App\Entity\Admin\Terminal;
use App\Entity\Core\Setting;
use App\Entity\User;
use App\Repository\Admin\LocationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class EditEmployeeFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {


        $builder
            ->add('enabled',CheckboxType::class,[
                'required' => false,
                'attr' => [
                    'class' => 'checkboxToggle',
                    'data-toggle' => "toggle",
                    'data-style' => "slow",
                    'data-offstyle' => "warning",
                    'data-onstyle'=> "info",
                    'data-on' => "Enabled",
                    'data-off'=> "Disabled"
                ],
            ])
            ->add('name', TextType::class, [
                'attr' => [
                    'autofocus' => true],
                    'required' => true,
            ])

            ->add('email', EmailType::class, [
                'attr' => ['autofocus' => true],
                'required' => true,
            ])
            ->add('lineManager', EntityType::class, array(
                'required'    => true,
                'class' => User::class,
                'placeholder' => 'Choose a line manager',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap select2'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->where("e.enabled =1")
                        ->orderBy('e.name', 'ASC');
                },
            ))
            ->add('mobile', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'mobileLocal'],
                'required' => true,
            ])

            ->add('phone', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>''],
                'required' => false,
            ])


            ->add('joiningDate', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'datePicker col-md-6', 'placeholder' => 'Joining date'],
                'required' => false,

            ])
            ->add('lastPromotionDate', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'datePicker col-md-6', 'placeholder' => 'Last Promotion Date', 'autocomplete' => 'off'],
                'required' => false,

            ])


            ->add('resignDate', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'datePicker col-md-6', 'placeholder' => 'Resign Date', 'autocomplete' => 'off'],
                'required' => false,

            ])


            ->add('userId', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'col-md-12', 'placeholder' => 'Enter user ID'],
                'required' => false,

            ])

            ->add('vehicleNo', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'col-md-6', 'placeholder' => 'Enter vehicle no'],
                'required' => false,

            ])
            ->add('typeOfVehicle', ChoiceType::class, [
                'choices'  => [
                    'Car' => 'car',
                    'Motorcycle' => 'Motorcycle',
                ],
            ])

            ->add('address', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'', 'placeholder' => 'Employee address',],
                'required' => false,
            ])

            ->add('serviceMode', EntityType::class, array(
                'required'    => true,
                'class' => Setting::class,
                'placeholder' => 'Choose a Service Mode',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join("e.settingType","st")
                        ->where("st.slug ='service-mode'")
                        ->orderBy('e.name', 'ASC');
                },
            ))
            ->add('responsibleOf', EntityType::class, array(
                'required'    => true,
                'class' => Setting::class,
                'placeholder' => 'Choose a Responsible',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join("e.settingType","st")
                        ->where("st.slug ='agent-group'")
                        ->orderBy('e.name', 'ASC');
                },
            ))
            ->add('designation', EntityType::class, array(
                'required'    => true,
                'class' => Setting::class,
                'placeholder' => 'Choose a Designation',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join("e.settingType","st")
                        ->where("st.slug ='designation'")
                        ->orderBy('e.name', 'ASC');
                },
            ))

             ->add('reportMode', EntityType::class, array(
                'required'    => true,
                'class' => Setting::class,
                'placeholder' => 'Choose a Report Mode',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join("e.settingType","st")
                        ->where("st.slug ='report-mode'")
                        ->orderBy('e.name', 'ASC');
                },
            ))

            ->add('userGroup', EntityType::class, array(
                'required'    => true,
                'class' => Setting::class,
                'placeholder' => 'Choose an user group',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join("e.settingType","st")
                        ->where("st.slug ='user-group'")
                        ->orderBy('e.name', 'ASC');
                },
            ))
            /*
                        ->add('zonal', EntityType::class, array(
                            'required'    => false,
                            'class' => Location::class,
                            'placeholder' => 'Choose a zonal area',
                            'choice_label' => 'name',
                            'attr'=>array('class'=>'span12 m-wrap select2'),
                            'query_builder' => function(EntityRepository $er){
                                return $er->createQueryBuilder('e')
                                    ->where("e.level = 2")
                                    ->orderBy('e.name', 'ASC');
                            },
                        ))


                        ->add('regional', EntityType::class, array(
                            'required'    => false,
                            'class' => Location::class,
                            'placeholder' => 'Choose a regional area',
                            'choice_label' => 'name',
                            'group_by'  => 'parent.name',
                            'choice_translation_domain' => true,
                            'attr'=>array('class'=>'span12 m-wrap select2'),
                            'query_builder' => function(EntityRepository $er){
                                return $er->createQueryBuilder('e')
                                    ->where("e.level = 3")
                                    ->orderBy('e.name', 'ASC');
                            },
                        ))

                        ->add('area', ChoiceType::class, [
                            'choices'  => [
                                'Zonal' => 'Zonal',
                                'Regional' => 'Regional',
                                'District' => 'District',
                                'Upozila' => 'Upozila',
                            ],
                        ])
                        */
            ->add('district', EntityType::class, [
                'class' => Location::class,
                'multiple' => true,
                'required'    => false,
                'group_by'  => 'parent.name',
                'choice_label'  => 'name',
                'attr'=>['class'=>'span12'],
                'placeholder' => 'Choose a upozila',
                'choice_translation_domain' => true,
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->where("e.level = 4")
                        ->orderBy('e.parent', 'ASC');
                },
            ])
            ->add('transferJoiningDate', TextType::class,[
                'mapped' => false,
                'required' => false,
                'attr' => ['class'=>'datePicker col-md-6', 'placeholder' => 'Transfer Date', 'autocomplete' => 'off'],
            ])

            ->add('roles', ChoiceType::class, [
                'multiple' => true,
                'choices'   => $options['userRepo']->getAccessRoleGroup($options['terminal'])
            ]);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'terminal' => Terminal::class,
            'userRepo' => UserRepository::class,
            'locationRepo' => LocationRepository::class,
        ]);
    }


}