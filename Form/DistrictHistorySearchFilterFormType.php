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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * Defines the form used to create and manipulate blog posts.
 *
 * @author Md Shafiqul Islam <shafiqabs@gmail.com>
 */
class DistrictHistorySearchFilterFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
//        $user = $options['user'];

        $builder
//            ->add('employee', EntityType::class, [
//                'class' => User::class,
//                'choice_label' => function($user){
//                    return'( ' . $user->getUserId() . ' ) ' . $user->getName();
//                },
//                'query_builder' => function (EntityRepository $er) use ($user) {
//                    if(in_array('ROLE_KPI_ADMIN', $user->getRoles())){
//                        return $er->createQueryBuilder('e')
//                            ->join('e.userGroup','ug')
//                            ->where('e.enabled =1')
//                            ->andWhere("ug.slug =:slug")->setParameter('slug','employee')
//                            ->andWhere("e.userMode = 'KPI'")
//                            ->orderBy('e.name', 'ASC');
//                    }else{
//                        return $er->createQueryBuilder('e')
//                            ->join('e.lineManager','lm')
//                            ->where('e.enabled =1')
//                            ->andWhere("lm.id =:lmId")->setParameter('lmId', $user->getId())
//                            ->orderBy('e.name', 'ASC');
//                    }
//                },
//                'attr'=>[
//                    'class'=>'select2'
//                ],
//                'placeholder' => 'Choose a employee',
//                'required' => false,
//
//            ])
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
                'placeholder' => 'Select a Month',
                'data' => date('F'),
            ])
            ->add('year', ChoiceType::class,[
                'choices' => $this->getYears(2020),
                'required' => false,
                'placeholder' => 'Select Year',
                'data' => date('Y'),
            ])
            ->setMethod('get')
            ;
    }

    private function getYears($min, $max='current')
    {
        $years = range($min, ($max === 'current' ? date('Y') : $max));
        return array_combine($years, $years);

        /*        $b = ['Select Year' => null];
                $yearsArray = array_combine($years, $years) + $b;
                return $yearsArray;*/
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
//            'user' => User::class,

        ]);
    }
}
