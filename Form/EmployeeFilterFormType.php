<?php
/**
 * Created by PhpStorm.
 * User: hasan
 * Date: 9/8/19
 * Time: 4:43 PM
 */
namespace Terminalbd\KpiBundle\Form;


use App\Entity\Core\Setting;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmployeeFilterFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $lineManagers = $options['lineManagers'];
        $loginUser = $options['loginUser'];

        if (in_array('ROLE_ADMIN', $loginUser->getRoles())){
            $builder->add('lineManager', ChoiceType::class,[
                'choices' => $lineManagers,
                'attr' => [
                    'class' => 'select2',
                ],
                'placeholder' => 'Select line manager',
                'required' => false,
            ]);
        }
        $builder
            ->add('employee', EntityType::class, [
                'class' => User::class,
                'choice_label' => function($user){
                    return '(' . $user->getUserId() . ') ' . $user->getName();
                },
                'query_builder' => function (EntityRepository $er) use($loginUser) {
                if (!in_array('ROLE_ADMIN', $loginUser->getRoles())){
                    return $er->createQueryBuilder('e')
                        ->join('e.lineManager','lineManager')
//                        ->where('e.enabled =1')
                        ->andWhere("e.userMode = 'KPI'")
                        ->andWhere('lineManager.id = :lineManagerId')->setParameter('lineManagerId', $loginUser->getId())
                        ->orderBy('e.name', 'ASC');
                }else{
                    return $er->createQueryBuilder('e')
//                        ->where('e.enabled =1')
                        ->andWhere("e.userMode = 'KPI'")
                        ->orderBy('e.name', 'ASC');
                }

                },
                'attr'=>[
                    'class'=>'select2'
                ],
                'placeholder' => 'Choose a employee',
                'required' => false,

            ])
            ->add('designation', EntityType::class,[
                'class' => Setting::class,
                'placeholder' => 'Select designation',
                'choice_label' => 'name',
                'query_builder' => function(EntityRepository $repository){
                    return $repository->createQueryBuilder('e')
                        ->join('e.settingType', 'settingType')
                        ->where("settingType.slug = 'designation'")
                        ->orderBy('e.name', 'ASC')
                        ;
                },
                'required' => false,

            ])
            ->add('kpiFormat', EntityType::class, [
                'class' => Setting::class,
                'choice_label' => 'name',
                'placeholder' => 'All Formats',
                'query_builder' => function(EntityRepository $repository){
                    return $repository->createQueryBuilder('e')
                        ->join('e.settingType', 'settingType')
                        ->where('settingType.slug = :slug')->setParameter('slug', 'report-mode');
                },
                'required' => false,

            ])
            ->add('employeeMobile', TextType::class,[
                'attr' => [
                    'placeholder' => 'Mobile Number'
                ],
                'required' => false
            ])
            ->add('department', EntityType::class, [
                'class' => Setting::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->join('e.settingType','settingType')
                        ->where("settingType.slug =:slug")->setParameter('slug','department')
                        ->orderBy('e.name', 'ASC');

                },
                'attr'=>[
                    'class'=>'select2'
                ],
                'placeholder' => 'Choose a department',
                'required' => false,

            ])
            ->setMethod('get')
        ;
     }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,
            'lineManagers' => User::class,
            'loginUser' => User::class,
        ]);
    }


}