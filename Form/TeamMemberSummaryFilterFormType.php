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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class TeamMemberSummaryFilterFormType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $user = $options['user'];
        $builder
            ->add('employee', EntityType::class,[
                'class' => User::class,
                'query_builder' => function(EntityRepository $repository) use($user){
                if (in_array('ROLE_ADMIN', $user->getRoles())){
                    return $repository->createQueryBuilder('e')
                        ->where('e.enabled = 1')
                        ->orderBy('e.name', 'ASC');
                }else{
                    return $repository->createQueryBuilder('e')
                        ->join('e.lineManager', 'lineManager')
                        ->where('e.enabled = 1')
                        ->andWhere('lineManager.id = :lineManager')->setParameter('lineManager', $user->getId())
                        ->orderBy('e.name', 'ASC');
                }
                },
                'choice_label' => 'name',
                'placeholder' => 'Select Employee',
                'required' => false,
                'attr' => [
                    'class' => 'select2'
                ]
            ])
            ->add('startMonth', ChoiceType::class,[
                'choices' => [
                    'Select Month' => null,
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
                'required' => true,
            ])
            ->add('endMonth', ChoiceType::class,[
                'choices' => [
                    'Select Month' => null,
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
                'required' => true
            ])
            ->add('year', ChoiceType::class,[
                'choices' => $this->getYears(2020),
                'required' => true,
            ])
            ->setMethod('GET')
//            ->add('Submit', SubmitType::class)
            ;

    }
    private function getYears($min, $max='current')
    {
        $years = range($min, ($max === 'current' ? date('Y') : $max));

        return array_combine($years, $years);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,
            'user' => User::class
        ]);
    }


}