<?php
/**
 * Created by PhpStorm.
 * User: hasan
 * Date: 9/8/19
 * Time: 4:43 PM
 */
namespace Terminalbd\KpiBundle\Form;

use App\Entity\Admin\Location;
use App\Entity\Admin\Terminal;
use App\Entity\Core\Setting;
use App\Entity\User;
use App\Repository\Admin\LocationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
use function Matrix\add;

class EmployeeFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {


        $builder
            ->add('username', null, [
                'attr' =>
                    ['autofocus' => true,
                        'placeholder' => 'Username' ,
                        'data-placement' => 'top' ,
                        'data-toggle' => 'tooltip',
                        'data-trigger'=> "focus",
                    ],
                'invalid_message' => 'The user name must be letter.',
               /* 'help' => 'help.post_content',*/
                'required' => true,
                'constraints' => [
                    new Length([
                        'max' => 20,
                    ]),
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

            ->add('mobile', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'mobileLocal'],
                'required' => true,
            ])

            ->add('phone', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>''],
                'required' => false,
            ])


            ->add('joiningDate', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'datePicker col-md-6', 'placeholder' => 'Joining date', 'autocomplete' => 'off'],
                'required' => false,

            ])


            ->add('userId', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'col-md-12', 'placeholder' => 'Enter user ID'],

            ])

             ->add('dateOfBirth', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'datePicker col-md-6', 'placeholder' => 'Date of Birth'],
                'required' => false,

            ])

            ->add('effectiveDate', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'datePicker col-md-6', 'placeholder' => 'Joining effective date'],
                'required' => false,

            ])

            ->add('leaveStatus', ChoiceType::class, [
                'choices'  => [
                    'Active' => 'Active',
                    'Suspended' => 'Suspended',
                    'In-active' => 'In-active',
                ],
            ])


           /* ->add('educationalQualification', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'col-md-12', 'placeholder' => 'Enter educational qualification'],
                'required' => false,

            ])*/

            /*->add('speciality', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'col-md-12', 'placeholder' => 'Enter speciality'],
                'required' => false,

            ])*/

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

             /*->add('trainingSkill', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'col-md-12', 'placeholder' => 'Enter training & skill'],
                'required' => false,

            ])*/

            ->add('salary', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'col-md-6', 'placeholder' => 'Enter salary'],
                'required' => false,

            ])

            ->add('accountNumber', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'col-md-12', 'placeholder' => 'Enter account no'],
                'required' => false,

            ])

            ->add('address', TextType::class, [
                'attr' => ['autofocus' => true,'class'=>'', 'placeholder' => 'Employee address',],
                'required' => false,
            ])

            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 20,
                    ]),
                ],
            ])
            ->add('serviceMode', EntityType::class, array(
                'required'    => true,
                'class' => Setting::class,
                'placeholder' => 'Choose a  Service Mode',
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
                'placeholder' => 'Choose a  Responsible',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join("e.settingType","st")
                        ->where("st.slug ='agent-group'")
                        ->orderBy('e.name', 'ASC');
                },
            ))

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

            ->add('department', EntityType::class, array(
                'required'    => false,
                'class' => Setting::class,
                'placeholder' => 'Choose a Designation',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join("e.settingType","st")
                        ->where("st.slug ='department'")
                        ->orderBy('e.name', 'ASC');
                },
            ))

            ->add('reportMode', EntityType::class, array(
                'required'    => true,
                'class' => Setting::class,
                'placeholder' => 'Choose a report format',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join("e.settingType","st")
                        ->where("st.slug ='report-mode'")
                        ->orderBy('e.name', 'ASC');
                },
            ))


            ->add('designation', EntityType::class, array(
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

            ->add('userGroup', EntityType::class, array(
                'required'    => true,
                'class' => Setting::class,
                'placeholder' => 'Choose an  user group',
                'choice_label' => 'name',
                'attr'=>array('class'=>'span12 m-wrap'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->join("e.settingType","st")
                        ->where("st.slug ='user-group'")
                        ->orderBy('e.name', 'ASC');
                },
            ))


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
            /*
            ->add('area', ChoiceType::class, [
                'choices'  => [
                    'Zonal' => 'Zonal',
                    'Regional' => 'Regional',
                    'District' => 'District',
                    'Upozila' => 'Upozila',
                ],
            ])
            */
/*
            ->add('district', EntityType::class, array(
                'required'    => false,
                'class' => Location::class,
                'group_by'  => 'parent.name',
                'choice_translation_domain' => true,
                'placeholder' => 'Choose a District',
                'choice_label' => 'name',
                'attr'=>array('class'=>'select2'),
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->where("e.level = 4")
                        ->orderBy('e.name', 'ASC');
                },
            ))*/

            ->add('district', EntityType::class, [
                'class' => Location::class,
                'multiple' => true,
                'required'    => false,
                'group_by'  => 'parent.name',
                'choice_label'  => 'name',
                'attr'=>['class'=>'span12'],
                'placeholder' => 'Choose a District',
                'choice_translation_domain' => true,
                'query_builder' => function(EntityRepository $er){
                    return $er->createQueryBuilder('e')
                        ->where("e.level = 4")
                        ->orderBy('e.parent', 'ASC');
                },
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