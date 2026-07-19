<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Terminalbd\KpiBundle\Form;


use App\Entity\Admin\Location;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Terminalbd\CrmBundle\Entity\CrmVisit;
use Terminalbd\CrmBundle\Entity\CrmVisitPlan;
use Terminalbd\CrmBundle\Entity\Setting;


/**
 * Defines the form used to create and manipulate blog posts.
 *
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class MonthRangeWiseSummeryReportFormType extends AbstractType
{


    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $userRepo = $options['userRepo'];
        $builder
            //employee 
            ->add('employee', EntityType::class, [
                'class' => User::class,
                'choice_label' => function($user){
                    return '(' . $user->getUserId() . ') ' . $user->getName();
                },
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->join('e.userGroup', 'userGroup')
                        ->andWhere("userGroup.slug = :slug")->setParameter('slug', 'employee')
                        ->andWhere("e.userMode = :userMode")->setParameter('userMode', 'KPI')
                        ->orderBy('e.name', 'ASC');

                },
                'attr'=>[
                    'class'=>'select2'
                ],
                'placeholder' => '- Select Employee -',
                'required' => false,

            ])
            ->add('lineManager', EntityType::class, array(
                'required'    => false,
                'class' => User::class,
                'placeholder' => '- Select Line Manager -',
                'choice_label' => function($lineManager){
                    /** @var User $lineManager */
                    return  '('. $lineManager->getUserId() .') ' . $lineManager->getName();
                },
                'attr'=>array('class'=>'span12 m-wrap select2'),
                'query_builder' => function(EntityRepository $er){
                    $qb = $er->createQueryBuilder('e');
                    $qb->where("e.enabled = 1")
                        ->andWhere('e.isDelete = 0')
                        ->andWhere($qb->expr()->orX(
                            $qb->expr()->like("e.roles", ':lineManager'),
                            $qb->expr()->like("e.roles", ':admin')
                        ))
                        ->setParameters([
                            'lineManager' => '%ROLE_LINE_MANAGER%',
                            'admin' => '%ROLE_KPI_ADMIN%'
                        ])
                        ->orderBy('e.name', 'ASC');
                    return $qb;
                },
            ))
            ->add($builder->create('fromDate', TextType::class, array(
                'label' => 'From Date',
                'required' => true,
//                'mapped' => false,
                'attr' => array(
                    'class' => 'visit_date monthYearPicker',
                    'autocomplete' => 'off',
                    'placeholder' => 'm-Y'
                ),
//                'empty_data' => new \DateTime(),
            ))->addViewTransformer(new DateTimeToStringTransformer(null, null, 'm-Y')))
            ->add($builder->create('toDate', TextType::class, array(
                'label' => 'From Date',
                'required' => true,
//                'mapped' => false,
                'attr' => array(
                    'class' => 'visit_date monthYearPicker',
                    'autocomplete' => 'off',
                    'placeholder' => 'm-Y'
                ),
//                'empty_data' => new \DateTime(),
            ))->addViewTransformer(new DateTimeToStringTransformer(null, null, 'm-Y')))
//            ->add('Save', SubmitType::class)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'userRepo' => UserRepository::class,
        ]);
    }
}