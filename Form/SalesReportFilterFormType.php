<?php
/**
 * Created by PhpStorm.
 * User: hasan
 * Date: 9/8/19
 * Time: 4:43 PM
 */

namespace Terminalbd\KpiBundle\Form;

use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class SalesReportFilterFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $user = $options['user'];

        $builder->add('employee', EntityType::class, [
            'class' => User::class,
            'choice_label' => function ($user) {
                return '( ' . $user->getUserId() . ' ) ' . $user->getName();
            },
            'query_builder' => function (EntityRepository $er) use ($user) {
                if (in_array('ROLE_KPI_ADMIN', $user->getRoles())) {
                    return $er->createQueryBuilder('e')
                        ->join('e.userGroup', 'ug')
                        ->where('e.enabled =1')
                        ->andWhere("ug.slug =:slug")->setParameter('slug', 'employee')
                        ->andWhere("e.userMode = 'KPI'")
                        ->andWhere("e.isPermanent = true")
                        ->orderBy('e.name', 'ASC');
                } else {
                    return $er->createQueryBuilder('e')
                        ->join('e.lineManager', 'lm')
                        ->where('e.enabled =1')
                        ->andWhere("e.isPermanent = true")
                        ->andWhere("lm.id =:lmId")->setParameter('lmId', $user->getId())
                        ->orderBy('e.name', 'ASC');
                }
            },
            'attr' => [
                'class' => 'select2'
            ],
            'placeholder' => '- Select employee -',
            'required' => false,

        ])
            ->add('startMonth', ChoiceType::class, [
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
            ->add('endMonth', ChoiceType::class, [
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
            ->add('year', ChoiceType::class, [
                'choices' => $this->getYears(2020),
                'required' => false,
                'placeholder' => 'Select Year',
                'data' => date('Y'),
            ])
            ->setMethod('GET');
    }

    private function getYears($min, $max = 'current')
    {
        $years = range($min, ($max === 'current' ? date('Y') : $max));
        return array_reverse(array_combine($years, $years), true);

        /*        $b = ['Select Year' => null];
                $yearsArray = array_combine($years, $years) + $b;
                return $yearsArray;*/
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,
            'user' => User::class,
        ]);
    }


}