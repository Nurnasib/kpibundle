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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use function Matrix\add;

class TeamMemberSummaryFilterFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $user = $options['user'];
        $lineManagers = $options['lineManagers'];

        if (in_array('ROLE_ADMIN', $user->getRoles())){
            $builder->add('lineManager', ChoiceType::class,[
                'choices' => $lineManagers,
                'attr' => [
                    'class' => 'select2',
                ],
                'placeholder' => 'Select line Manager',
                'required' => false,
                'data' => array_values($lineManagers)[0],
            ]);
        }else{
            $builder
                ->add('employee', EntityType::class,[
                    'class' => User::class,
                    'query_builder' => function(EntityRepository $repository) use($user){
                            return $repository->createQueryBuilder('e')
                                ->join('e.lineManager', 'lineManager')
                                ->where('e.enabled = 1')
                                ->andWhere('lineManager.id = :lineManager')->setParameter('lineManager', $user->getId())
                                ->orderBy('e.name', 'ASC');
                    },
                    'choice_label' => 'name',
                    'placeholder' => 'Select Team Member',
                    'required' => false,
                    'attr' => [
                        'class' => 'select2'
                    ]
                ]);
        }
        $builder
            ->add('kpiFormat', EntityType::class, [
                'class' => Setting::class,
                'choice_label' => 'name',
                'placeholder' => 'All Formats',
                'required' => false,
                'query_builder' => function(EntityRepository $repository){
                return $repository->createQueryBuilder('e')
                    ->join('e.settingType', 'settingType')
                    ->where('settingType.id = 6');
                }
            ])
            ->add('startMonth', ChoiceType::class,[
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
            ->add('endMonth', ChoiceType::class,[
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
            ->setMethod('GET')
//            ->add('Submit', SubmitType::class)
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

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,
            'user' => User::class,
            'lineManagers' => User::class,
        ]);
    }


}