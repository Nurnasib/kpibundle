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


use App\Entity\Core\Setting;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use function Sodium\add;

/**
 * Defines the form used to create and manipulate blog posts.
 *
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class EmployeeBoardFormType extends AbstractType
{


    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user=$options['user'];
        $userId = $user->getId();
        $userGroup = $user->getUserGroup()?$user->getUserGroup()->getSlug():'';
        $format = $options['format'];
        $builder
            ->add('employee', EntityType::class, [
                'class' => User::class,
                'required' => true,
                'query_builder' => function (EntityRepository $er) use ($userId, $userGroup, $format) {
                    if($userGroup == 'administrator'){
                        $qb = $er->createQueryBuilder('e');
                        $qb->join('e.userGroup','ug');
                        $qb->join('e.reportMode','reportMode');
                        $qb->where('e.enabled =1');
                        $qb->andWhere("ug.slug =:slug")->setParameter('slug','employee');
                        $qb->andWhere("e.userMode = 'KPI'");
                        $qb->orderBy('e.name', 'ASC');
                        if ($format == 'custom-format'){
                            $qb->andWhere('reportMode.slug IN (:reportMode)')->setParameter('reportMode', ['custom-format-poultry', 'custom-format-cattle', 'custom-format-aqua', 'custom-format-spo']);
                        }else{
                            $qb->andWhere('reportMode.slug NOT IN (:reportMode)')->setParameter('reportMode', ['custom-format-poultry', 'custom-format-cattle', 'custom-format-aqua', 'custom-format-spo']);
                        }
                        return $qb;
                    }else{
                        $qb = $er->createQueryBuilder('e')
                            ->join('e.lineManager','lm')
                            ->leftJoin('e.reportMode','reportMode')

                            ->where('e.enabled =1')
                            ->andWhere("lm.id =:lmId")->setParameter('lmId',$userId)
                            ->orderBy('e.name', 'ASC');
                        if ($format == 'custom-format'){
                            $qb->andWhere('reportMode.slug IN (:reportMode)')->setParameter('reportMode', ['custom-format-poultry', 'custom-format-cattle', 'custom-format-aqua', 'custom-format-spo']);
                        }else{
                            $qb->andWhere('reportMode.slug NOT IN (:reportMode)')->setParameter('reportMode', ['custom-format-poultry', 'custom-format-cattle', 'custom-format-aqua', 'custom-format-spo']);
                        }
                        return $qb;
                    }
                },
                'attr'=>['class'=>'select2'],
                'choice_label' => function($user){
                    return'(' . $user->getUserId() . ') ' . $user->getName() . ' (' . $user->getDesignation()->getName() . ')';
                },
                'placeholder' => '- Select employee -',
            ])
            ->add('status',CheckboxType::class,[
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
            ->add('monthYear', TextType::class,[
                'attr'=>['class'=>'inputMonth','autocomplete'=>'off'],
                'mapped'=>false
            ])
            ->add('SaveAndCreate', SubmitType::class)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmployeeBoard::class,
            'user' => User::class,
            'format' => null,
        ]);
    }
}