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


use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Terminalbd\KpiBundle\Entity\EmployeeBoard;
use Terminalbd\KpiBundle\Entity\EmployeeSetup;
use Terminalbd\KpiBundle\Entity\Setting;
use Terminalbd\KpiBundle\Entity\SettingType;

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
        $builder
            ->add('employee', EntityType::class, [
                'class' => User::class,
                'required' => true,
                'query_builder' => function (EntityRepository $er) use ($userId, $userGroup) {
                    if($userGroup=='administrator'){
                        return $er->createQueryBuilder('e')
                            ->join('e.userGroup','ug')
                            ->where('e.enabled =1')
                            ->andWhere("ug.slug =:slug")->setParameter('slug','employee')
                            ->orderBy('e.name', 'ASC');
                    }else{
                        return $er->createQueryBuilder('e')
                            ->join('e.lineManager','lm')
                            ->where('e.enabled =1')
                            ->andWhere("lm.id =:lmId")->setParameter('lmId',$userId)
                            ->orderBy('e.name', 'ASC');
                    }
                },
                'attr'=>['class'=>'select2'],
                'choice_label' => function($user){
                    return'(' . $user->getUserId() . ') ' . $user->getName() . ' (' . $user->getDesignation()->getName() . ')';
                },
                'placeholder' => 'Choose a employee',
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
        ]);
    }
}