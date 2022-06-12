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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Defines the form used to create and manipulate blog posts.
 *
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class KpiBoardSearchFilterFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];
        $lineManagers = $options['lineManagers'];

        if (in_array('ROLE_KPI_ADMIN', $user->getRoles())){
            $builder
                ->add('createdBy', ChoiceType::class,[
                    'choices' => $lineManagers,
                    'attr' => [
                        'class' => 'select2',
                    ],
                    'placeholder' => 'Select created by',
                    'required' => false,
                ])
                ->add('lineManager', ChoiceType::class,[
                    'choices' => $lineManagers,
                    'attr' => [
                        'class' => 'select2',
                    ],
                    'placeholder' => 'Select line manager',
                    'required' => false,
                ])
            ;
        }
        $builder
            ->add('employee', EntityType::class, [
                'class' => User::class,
                'choice_label' => function($user){
                    return'( ' . $user->getUserId() . ' ) ' . $user->getName();
                },
                'query_builder' => function (EntityRepository $er) use ($user) {
                    if(in_array('ROLE_KPI_ADMIN', $user->getRoles())){
                        return $er->createQueryBuilder('e')
                            ->join('e.userGroup','ug')
                            ->where('e.enabled =1')
                            ->andWhere("ug.slug =:slug")->setParameter('slug','employee')
                            ->andWhere("e.userMode = 'KPI'")
                            ->andWhere("e.isPermanent = true")
                            ->orderBy('e.name', 'ASC');
                    }else{
                        return $er->createQueryBuilder('e')
                            ->join('e.lineManager','lm')
                            ->where('e.enabled =1')
                            ->andWhere("e.isPermanent = true")
                            ->andWhere("lm.id =:lmId")->setParameter('lmId', $user->getId())
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

            ->add('month', ChoiceType::class,[
                'choices' => [
                    'January' => 'January',
                    'February' => 'February',
                    'March' => 'March',
                    'April' => 'April',
                    'May' => 'May',
                    'June' => 'June',
                    'July' => 'July',
                    'August' => 'August',
                    'September' => 'September',
                    'October' => 'October',
                    'November' => 'November',
                    'December' => 'December',
                ],
                'required' => false,
                'placeholder' => 'Select a month',
            ])
            ->add('year', ChoiceType::class,[
                'choices' => $this->getYears(2020),
                'required' => false,
                'placeholder' => 'Select a year',
            ])
            ->add('process', ChoiceType::class, [
                'choices' => [
                    'Created' => 'created',
                    'In-progress' => 'in-progress',
                    'Approved' => 'approved',
                ],
                'required' => false,
                'placeholder' => 'Select process'
            ])
            ->setMethod('get')
            ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'user' => User::class,
            'lineManagers' => User::class,

        ]);
    }
    
    private function getYears($min, $max='current')
    {
        $years = range($min, ($max === 'current' ? date('Y') : $max));
        return array_combine($years, $years);
    }
}
